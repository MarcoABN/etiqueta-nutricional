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
                    ->afterStateUpdated(fn ($livewire) => $livewire->resetTable())
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
                // -------------------------------------------------------------------------
                // BOTÃO 1: INICIAR (Aparece apenas quando tem solicitação selecionada e 0 pallets)
                // -------------------------------------------------------------------------
                TableAction::make('create_first_pallet')
                    ->label('Iniciar Fechamento (1º Pallet)')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->hidden(function (PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'] ?? null;
                        if (!$reqId) return true; // Oculta se não houver solicitação
                        
                        // Oculta se JÁ existirem pallets no banco
                        return Pallet::where('request_id', $reqId)->exists();
                    })
                    ->disabled(function (PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'] ?? null;
                        if (!$reqId) return false;
                        $req = Request::find($reqId);
                        return $req && ($req->is_locked || optional($req->settlement)->is_locked);
                    })
                    ->form([
                        Textarea::make('importer_text')
                            ->label('Dados do Importador')
                            ->default("IMPORTED BY: GO MINAS DISTRIBUTION LLC\n2042 NW 55TH AVE, MARGATE, FL33063")
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data, PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'];
                        
                        Pallet::create([
                            'request_id'    => $reqId,
                            'pallet_number' => 1,
                            'total_pallets' => 1,
                            'importer_text' => $data['importer_text'],
                        ]);

                        Notification::make()->title('Primeiro pallet gerado com sucesso!')->success()->send();
                        
                        // Força a atualização da tabela e do componente Livewire
                        $livewire->resetTable();
                        $livewire->dispatch('$refresh');
                    }),

                // -------------------------------------------------------------------------
                // BOTÃO 2: ADICIONAR EXTRA (Aparece apenas quando JÁ existem pallets)
                // -------------------------------------------------------------------------
                TableAction::make('add_extra_pallet')
                    ->label('Adicionar Pallet (+1)')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->hidden(function (PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'] ?? null;
                        if (!$reqId) return true; // Oculta se não houver solicitação
                        
                        // Oculta se NÃO existirem pallets
                        return !Pallet::where('request_id', $reqId)->exists();
                    })
                    ->disabled(function (PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'] ?? null;
                        if (!$reqId) return false;
                        $req = Request::find($reqId);
                        return $req && ($req->is_locked || optional($req->settlement)->is_locked);
                    })
                    ->action(function (PalletClosing $livewire) {
                        $reqId = $livewire->data['request_id'];
                        
                        $existingPallets = Pallet::where('request_id', $reqId)->orderBy('pallet_number')->get();
                        $newNumber = $existingPallets->count() + 1;
                        
                        $importerText = $existingPallets->first()->importer_text 
                            ?? "IMPORTED BY: GO MINAS DISTRIBUTION LLC\n2042 NW 55TH AVE, MARGATE, FL33063";

                        Pallet::create([
                            'request_id'    => $reqId,
                            'pallet_number' => $newNumber,
                            'total_pallets' => $newNumber,
                            'importer_text' => $importerText,
                        ]);

                        Pallet::where('request_id', $reqId)->update(['total_pallets' => $newNumber]);

                        Notification::make()->title("Pallet {$newNumber} adicionado!")->success()->send();
                        
                        // Força a atualização da tabela e do componente Livewire
                        $livewire->resetTable();
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->actions([
                EditAction::make('preencher')
                    ->label('Preencher')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->disabled(function (Pallet $record) {
                        $req = $record->request;
                        return $req && ($req->is_locked || optional($req->settlement)->is_locked);
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

                DeleteAction::make()
                    ->label('Excluir Último')
                    ->hidden(function (Pallet $record) {
                        // Compara com o número máximo real no banco para garantir que é o último
                        $max = Pallet::where('request_id', $record->request_id)->max('pallet_number');
                        return $record->pallet_number < $max;
                    })
                    ->disabled(function (Pallet $record) {
                        $req = $record->request;
                        return $req && ($req->is_locked || optional($req->settlement)->is_locked);
                    })
                    ->after(function (Pallet $record, PalletClosing $livewire) {
                        $remainingCount = Pallet::where('request_id', $record->request_id)->count();
                        Pallet::where('request_id', $record->request_id)->update(['total_pallets' => $remainingCount]);
                        
                        $livewire->resetTable();
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->emptyStateHeading('Nenhum pallet gerado')
            ->emptyStateDescription('Selecione uma solicitação e utilize o botão no cabeçalho da tabela para iniciar.')
            ->paginated(false);
    }
}