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
use Filament\Tables\Actions\DeleteAction; // NOVO: Importação do DeleteAction
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
                            // Substitua 'description' pelo nome exato da coluna da sua descrição
                            ->pluck('observation', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($livewire) {
                        if (method_exists($livewire, 'resetTable')) {
                            $livewire->resetTable();
                        }
                    })
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $reqId = $this->data['request_id'] ?? null;

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
            ->headerActions([
                // AÇÃO 1: GERAR LOTE INICIAL
                TableAction::make('generate_pallets')
                    ->label('Gerar Pallets')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(function () {
                        $reqId = $this->data['request_id'] ?? null;
                        if (!$reqId) return false;

                        $hasNoPallets = Pallet::where('request_id', $reqId)->count() === 0;
                        $request = \App\Models\Request::find($reqId);
                        $isLocked = ($request?->is_locked ?? false) || ($request?->settlement?->is_locked ?? false);

                        return $hasNoPallets && !$isLocked;
                    })
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
                    ->action(function (array $data) {
                        $reqId = $this->data['request_id'] ?? null;
                        for ($i = 1; $i <= $data['total_pallets']; $i++) {
                            Pallet::create([
                                'request_id' => $reqId,
                                'pallet_number' => $i,
                                'total_pallets' => $data['total_pallets'],
                                'importer_text' => $data['importer_text'],
                            ]);
                        }
                        Notification::make()->title('Pallets gerados com sucesso!')->success()->send();
                    }),

                // AÇÃO 2: ADICIONAR PALLET EXTRA
                TableAction::make('add_pallet')
                    ->label('Adicionar Pallet Extra')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(function () {
                        $reqId = $this->data['request_id'] ?? null;
                        if (!$reqId) return false;

                        // Avalia dinamicamente a cada renderização da tabela
                        $hasPallets = \App\Models\Pallet::where('request_id', $reqId)->exists();
                        $request = \App\Models\Request::find($reqId);

                        // Nota: Se a solicitação antiga estiver trancada, o botão não aparecerá por segurança.
                        $isLocked = ($request?->is_locked ?? false) || ($request?->settlement?->is_locked ?? false);

                        return $hasPallets && !$isLocked;
                    })
                    ->action(function () {
                        $reqId = $this->data['request_id'] ?? null;
                        $existingPallets = \App\Models\Pallet::where('request_id', $reqId)->orderBy('pallet_number')->get();

                        if ($existingPallets->isEmpty()) return;

                        // Fallback: Se o pallet for antigo e não tiver 'importer_text', aplica um texto padrão para não quebrar a inserção
                        $importerText = $existingPallets->first()->importer_text
                            ?? "IMPORTED BY: GO MINAS DISTRIBUTION LLC\n2042 NW 55TH AVE, MARGATE, FL33063";

                        $newTotal = $existingPallets->count() + 1;

                        \App\Models\Pallet::create([
                            'request_id' => $reqId,
                            'pallet_number' => $newTotal,
                            'total_pallets' => $newTotal,
                            'importer_text' => $importerText,
                        ]);

                        \App\Models\Pallet::where('request_id', $reqId)->update(['total_pallets' => $newTotal]);

                        Notification::make()->title('Pallet extra adicionado com sucesso!')->success()->send();
                    })
            ]);

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
            ->headerActions([
                $generatePalletsAction,
                $addPalletAction // <-- Deve estar aqui para aparecer no topo da tabela
            ])
            ->emptyStateActions([
                $generatePalletsAction
            ])
            ->actions([
                EditAction::make('preencher')
                    ->label('Preencher')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->disabled(function ($record) {
                        $request = $record->request;
                        return ($request?->is_locked ?? false) || ($request?->settlement?->is_locked ?? false);
                    })
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

                // NOVO: Ação de exclusão com reordenação subsequente
                DeleteAction::make()
                    ->label('Excluir')
                    ->visible(function ($record) {
                        // Regra de Integridade: Só exibe o botão se NÃO existir nenhum pallet 
                        // com número superior ao atual para esta mesma solicitação.
                        $isLast = ! \App\Models\Pallet::where('request_id', $record->request_id)
                            ->where('pallet_number', '>', $record->pallet_number)
                            ->exists();

                        return $isLast;
                    })
                    ->disabled(function ($record) {
                        // Mantém a trava de segurança caso o pedido já esteja consolidado/trancado
                        $request = $record->request;
                        return ($request?->is_locked ?? false) || ($request?->settlement?->is_locked ?? false);
                    })
                    ->after(function ($record) {
                        // Após excluir o último, precisamos atualizar o 'total_pallets' dos que ficaram
                        $remainingCount = \App\Models\Pallet::where('request_id', $record->request_id)->count();

                        // Atualiza o campo total_pallets em massa para os registros restantes
                        \App\Models\Pallet::where('request_id', $record->request_id)
                            ->update(['total_pallets' => $remainingCount]);
                    })
            ])
            ->emptyStateHeading('Nenhum pallet gerado')
            ->emptyStateDescription('Selecione uma solicitação e clique no botão abaixo para gerar.')
            ->paginated(false);
    }
}
