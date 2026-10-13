<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Vincula os produtos do NX ao código do sistema Listaso (exterior) usando o código de barras.
 *
 * A planilha do Listaso traz "Item Code" + "UPC". Descrição não é confiável, então o
 * cruzamento é feito apenas pelo código de barras normalizado:
 *  1. Comparação direta (somente dígitos, sem zeros à esquerda), o que já cobre
 *     GTIN-14 iniciado por 0 e UPC-A gravado sem o zero inicial.
 *  2. Se não achar, converte DUN-14 (indicador 1-8) para o EAN-13 da unidade e tenta de novo.
 *  3. Se um mesmo código de barras aponta para mais de um Item Code, tenta desempatar pela
 *     quantidade de unidades na caixa ("24x200g" x qtunitcx). Se não resolver, fica vazio.
 */
class ListasoCodeImporter
{
    public const DEFAULT_FILE = 'data/listaso_upc.csv';

    /** @var array<string, array<string, string>> chave normalizada => [item_code => item_name] */
    private array $exactIndex = [];

    /** @var array<string, array<string, string>> */
    private array $derivedIndex = [];

    public function __construct(private ?string $file = null)
    {
        $this->file ??= database_path(self::DEFAULT_FILE);
    }

    /**
     * @return array{matched: array, ambiguous: array, not_found: array, unused: Collection}
     */
    public function import(bool $force = false, bool $dryRun = false): array
    {
        $rows = $this->loadRows();
        $this->buildIndexes($rows);

        $report = ['matched' => [], 'ambiguous' => [], 'not_found' => []];
        $usedCodes = [];

        $products = Product::query()
            ->whereNotNull('barcode')
            ->where('barcode', '<>', '')
            ->when(!$force, fn($q) => $q->where(fn($q) => $q->whereNull('listaso_code')->orWhere('listaso_code', '')))
            ->get(['id', 'codprod', 'barcode', 'product_name', 'qtunitcx', 'listaso_code']);

        foreach ($products as $product) {
            [$candidates, $method] = $this->candidatesFor((string) $product->barcode);

            if (count($candidates) > 1) {
                $resolved = $this->disambiguate($candidates, $product->qtunitcx);
                if ($resolved !== null) {
                    $candidates = [$resolved => $candidates[$resolved]];
                    $method .= ' + qtd. caixa';
                }
            }

            $info = [
                'codprod' => $product->codprod,
                'barcode' => $product->barcode,
                'product_name' => $product->product_name,
            ];

            if (count($candidates) === 0) {
                $report['not_found'][] = $info;
                continue;
            }

            if (count($candidates) > 1) {
                $report['ambiguous'][] = $info + ['candidates' => implode(', ', array_keys($candidates))];
                continue;
            }

            $code = (string) array_key_first($candidates);
            $usedCodes[$code] = true;
            $report['matched'][] = $info + ['listaso_code' => $code, 'method' => $method];

            if (!$dryRun && (string) $product->listaso_code !== $code) {
                // update direto para não disparar observers do Product
                Product::whereKey($product->id)->update(['listaso_code' => $code]);
            }
        }

        $report['unused'] = $rows
            ->reject(fn($row) => isset($usedCodes[$row['item_code']]))
            ->values();

        return $report;
    }

    public static function normalize(?string $barcode): string
    {
        return ltrim(preg_replace('/\D/', '', (string) $barcode), '0');
    }

    /**
     * DUN-14 com indicador de embalagem (1-8) => EAN-13 da unidade (recalcula o dígito verificador).
     */
    public static function dunToEan(?string $barcode): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $barcode);

        if (strlen($digits) !== 14 || !in_array($digits[0], ['1', '2', '3', '4', '5', '6', '7', '8'], true)) {
            return null;
        }

        $body = substr($digits, 1, 12);
        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $body . ((10 - $sum % 10) % 10);
    }

    private function loadRows(): Collection
    {
        if (!is_file($this->file)) {
            throw new \RuntimeException("Arquivo do Listaso não encontrado: {$this->file}");
        }

        $handle = fopen($this->file, 'r');
        $header = fgetcsv($handle, null, ';');
        $rows = [];

        while (($line = fgetcsv($handle, null, ';')) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }
            $row = array_map('trim', array_combine($header, $line));
            if ($row['item_code'] !== '' && $row['upc'] !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return collect($rows);
    }

    private function buildIndexes(Collection $rows): void
    {
        $this->exactIndex = [];
        $this->derivedIndex = [];

        foreach ($rows as $row) {
            $key = self::normalize($row['upc']);
            // Códigos curtos demais (ex: "11554") não são códigos de barras válidos
            if (strlen($key) >= 8) {
                $this->exactIndex[$key][$row['item_code']] = $row['item_name'];
            }

            if ($ean = self::dunToEan($row['upc'])) {
                $this->derivedIndex[self::normalize($ean)][$row['item_code']] = $row['item_name'];
            }
        }
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function candidatesFor(string $barcode): array
    {
        $key = self::normalize($barcode);

        if ($key !== '' && isset($this->exactIndex[$key])) {
            return [$this->exactIndex[$key], 'exato'];
        }

        // DUN-14 no Listaso => EAN-13 no NX
        if ($key !== '' && isset($this->derivedIndex[$key])) {
            return [$this->derivedIndex[$key], 'DUN-14'];
        }

        // DUN-14 no NX => EAN-13 no Listaso
        if (($ean = self::dunToEan($barcode)) && isset($this->exactIndex[self::normalize($ean)])) {
            return [$this->exactIndex[self::normalize($ean)], 'DUN-14'];
        }

        return [[], ''];
    }

    /**
     * @param array<string, string> $candidates item_code => item_name
     */
    private function disambiguate(array $candidates, $qtunitcx): ?string
    {
        $units = (int) round((float) str_replace(',', '.', (string) $qtunitcx));
        if ($units <= 0) {
            return null;
        }

        $hits = array_filter(
            $candidates,
            fn($name) => preg_match('/(\d+)\s*x/i', $name, $m) && (int) $m[1] === $units
        );

        return count($hits) === 1 ? (string) array_key_first($hits) : null;
    }
}
