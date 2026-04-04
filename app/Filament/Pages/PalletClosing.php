<?php

namespace App\Filament\Pages;

use App\Models\Request;
use App\Models\Pallet;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PalletClosing extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Fechamento Pallets';
    protected static ?string $title = 'Fechamento de Pallets';
    protected static string $view = 'filament.pages.pallet-closing';

    protected static ?string $navigationGroup = 'Operação';
    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('request_id')
                    ->label('Selecionar Solicitação')
                    ->placeholder('Selecione pela Descrição do Pedido')
                    ->options(
                        Request::query()
                            ->where('status', 'aberto')
                            ->orWhereHas('pallets')
                            ->orderByDesc('created_at')
                            ->pluck('observation', 'id')
                    )
                    ->searchable()
                    ->live()
                    // Ao trocar a opção, forçamos a tabela a se reconstruir do zero
                    ->afterStateUpdated(fn ($livewire) => $livewire->resetTable())
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        // 1. Snapshot do estado ATUAL do banco de dados (PHP Puro)
        $reqId = $this->data['request_id'] ?? null;
        $hasPallets = false;
        $isLocked = false;
        $maxPalletNumber = 0;

        if ($reqId) {
            $hasPallets = Pallet::where('request_id', $reqId)->exists();
            $maxPalletNumber = Pallet::where('request_id', $reqId)->max('pallet_number') ?? 0;

            $req = Request::find($reqId);
            if ($req) {
                $isLocked = $req->is_locked || optional($req->settlement)->is_locked;
            }
        }

        // 2. Construção dinâmica das ações (O Pulo do Gato)
        $headerActions = [];
        $emptyStateActions = [];

        if ($reqId) {
            if ($hasPallets) {
                // Se TEM pallets, injetamos o botão de adicionar extra no Header
                $headerActions[] = TableAction::make('add_pallet')
                    ->label('Adicionar Pallet Extra')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->disabled($isLocked) // Usa a variável booleana lida ali em cima
                    ->action(function () use ($reqId) {
                        $existingPallets = Pallet::where('request_id', $reqId)->orderBy('pallet_number')->get();
                        if ($existingPallets->isEmpty()) return;

                        $importerText = $existingPallets->first()->importer_text
                            ?? "IMPORTED BY: GO MINAS DISTRIBUTION LLC\n2042 NW 55TH AVE, MARGATE, FL33063";

                        $newTotal = $existingPallets->count() + 1;

                        Pallet::create([
                            'request_id'    => $reqId,
                            'pallet_number' => $newTotal,
                            'total_pallets' => $newTotal,
                            'importer_text' => $importerText,
                        ]);

                        Pallet::where('request_id', $reqId)->update(['total_pallets' => $newTotal]);

                        Notification::make()->title('Pallet extra adicionado!')->success()->send();
                        $this->resetTable();
                    });
            } else {
                // Se NÃO TEM pallets, injetamos o botão de gerar no Empty State
                $emptyStateActions[] = TableAction::make('generate_pallets_empty')
                    ->label('Gerar Pallets')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->disabled($isLocked)
                    ->form([
                        Textarea::make('importer_text')
                            ->label('Dados do Importador')
                            ->default("IMPORTED BY: GO MINAS DISTRIBUTION LLC\n2042 NW 55TH AVE, MARGATE, FL33063")
                            ->required()
                            ->rows(3),

                        TextInput::make('total_pallets')
                            ->label('Total de Pallets')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($reqId) {
                        for ($i = 1; $i <= $data['total_pallets']; $i++) {
                            Pallet::create([
                                'request_id'    => $reqId,
                                'pallet_number' => $i,
                                'total_pallets' => $data['total_pallets'],
                                'importer_text' => $data['importer_text'],
                            ]);
                        }

                        Notification::make()->title('Pallets gerados com sucesso!')->success()->send();
                        $this->resetTable();
                    });
            }
        }

        // 3. Montagem Final da Tabela
        return $table
            ->query(
                Pallet::query()
                    ->when($reqId, fn($q, $id) => $q->where('request_id', $id), fn($q) => $q->whereRaw('1 = 0'))
                    ->orderBy('pallet_number', 'asc')
            )
            ->columns([
                TextColumn::make('pallet_number')
                    ->label('Número')
                    ->formatStateUsing(fn($record) => "{$record->pallet_number} / {$record->total_pallets}")
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('gross_weight')
                    ->label('Peso Total (KG)')
                    ->numeric()
                    ->default('Pendente')
                    ->alignCenter(),

                TextColumn::make('height')
                    ->label('Altura (m)')
                    ->numeric()
                    ->default('Pendente')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn($record) => $record->gross_weight && $record->height ? 'Pronto' : 'Pendente')
                    ->badge()
                    ->color(fn($state) => $state === 'Pronto' ? 'success' : 'warning')
                    ->alignCenter(),
            ])
            ->headerActions($headerActions) // Injeção do array limpo!
            ->emptyStateActions($emptyStateActions) // Injeção do array limpo!
            ->actions([
                EditAction::make('preencher')
                    ->label('Preencher')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->disabled($isLocked) // Compartilha o lock state
                    ->modalHeading(fn($record) => "Dados do Pallet {$record->pallet_number}/{$record->total_pallets}")
                    ->form([
                        TextInput::make('gross_weight')
                            ->label('Peso Total / Gross Weight (KG)')
                            ->numeric()
                            ->required(),

                        TextInput::make('height')
                            ->label('Altura / Height (m)')
                            ->numeric()
                            ->step('0.01')
                            ->live(debounce: 500)
                            ->required(),

                        Placeholder::make('height_alert')
                            ->hiddenLabel()
                            ->content(new HtmlString('<span class="text-warning-600 font-bold dark:text-warning-400">⚠️ Alerta: A altura informada está no limite do padrão (1,59m - 1,60m).</span>'))
                            ->visible(function (Get $get) {
                                $h = number_format((float) $get('height'), 2);
                                return in_array($h, ['1.59', '1.60']);
                            }),

                        Checkbox::make('confirm_height')
                            ->label('Confirmo que a altura está correta e foge do padrão estabelecido.')
                            ->accepted()
                            ->dehydrated(false)
                            ->visible(function (Get $get) {
                                $h = (float) $get('height');
                                return $h > 0 && ($h < 1.50 || $h > 1.60);
                            }),
                    ]),

                TableAction::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->disabled(fn($record) => !$record->gross_weight || !$record->height)
                    ->action(function ($record) {
                        $url = route('print.pallet', ['pallet' => $record->id]);
                        $this->dispatch('print-pallet-event', url: $url);
                    }),

                DeleteAction::make()
                    ->label('Excluir')
                    ->hidden(fn(Pallet $record) => $record->pallet_number !== $maxPalletNumber) // Compara direto com o max() coletado no topo
                    ->disabled($isLocked)
                    ->after(function (Pallet $record) {
                        $remainingCount = Pallet::where('request_id', $record->request_id)->count();
                        Pallet::where('request_id', $record->request_id)->update(['total_pallets' => $remainingCount]);
                        
                        $this->resetTable();
                    }),
            ])
            ->emptyStateHeading('Nenhum pallet gerado')
            ->emptyStateDescription('Selecione uma solicitação e clique no botão acima para gerar.')
            ->paginated(false);
    }
}