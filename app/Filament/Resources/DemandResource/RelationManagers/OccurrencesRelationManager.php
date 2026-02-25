<?php

namespace App\Filament\Resources\DemandResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class OccurrencesRelationManager extends RelationManager
{
    protected static string $relationship = 'occurrences';

    protected static ?string $title = 'Histórico e Ocorrências';
    protected static ?string $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Nova Ocorrência')
                    ->required()
                    ->placeholder('Descreva o andamento...')
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('attachments')
                    ->label('Anexos')
                    ->directory('demand-occurrences')
                    ->multiple()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(5120)
                    ->panelLayout('grid')
                    ->reorderable(false) // Não precisa reordenar se não vai editar
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            // Layout de Grade (Cards) em vez de tabela
            ->contentGrid([
                'md' => 1,
                'xl' => 1,
            ])
            ->columns([
                Tables\Columns\TextColumn::make('content')
                    ->label('Ocorrência')
                    ->formatStateUsing(function ($state, $record) {
                        // Dados do usuário e data
                        $userName = $record->user->name ?? 'Usuário desconhecido';
                        $date = $record->created_at->format('d/m/Y H:i');
                        
                        // Processamento dos anexos
                        $attachmentsHtml = '';
                        if (!empty($record->attachments)) {
                            $attachmentsHtml = '<div class="mt-3 flex flex-wrap gap-2 pt-3 border-t border-gray-100">';
                            foreach ($record->attachments as $filePath) {
                                $url = Storage::url($filePath);
                                $fileName = basename($filePath);
                                $isPdf = str_ends_with(strtolower($filePath), '.pdf');
                                
                                // Ícone SVG baseado no tipo
                                $icon = $isPdf 
                                    ? '<svg class="w-4 h-4 mr-1 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>'
                                    : '<svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';

                                $attachmentsHtml .= "
                                    <a href='{$url}' target='_blank' class='inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-md hover:bg-gray-100 hover:text-primary-600 transition-colors duration-200'>
                                        {$icon} {$fileName}
                                    </a>
                                ";
                            }
                            $attachmentsHtml .= '</div>';
                        }

                        // Montagem do Card HTML Completo
                        return "
                            <div class='flex flex-col p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 dark:bg-gray-800 dark:border-gray-700'>
                                <div class='flex items-center justify-between mb-2'>
                                    <div class='flex items-center gap-2'>
                                        <div class='w-8 h-8 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-xs'>
                                            " . substr($userName, 0, 2) . "
                                        </div>
                                        <span class='font-semibold text-gray-900 dark:text-gray-100'>{$userName}</span>
                                    </div>
                                    <span class='text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full dark:bg-gray-700 dark:text-gray-400'>
                                        {$date}
                                    </span>
                                </div>
                                
                                <div class='text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed pl-10'>
                                    {$state}
                                </div>

                                <div class='pl-10'>
                                    {$attachmentsHtml}
                                </div>
                            </div>
                        ";
                    })
                    ->html(), // Permite renderizar o HTML acima
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Ocorrência')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Registrar Andamento')
                    ->slideOver() // Abre numa gaveta lateral para ficar mais moderno
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                // NENHUMA AÇÃO AQUI (Removemos Edit/Delete) para garantir imutabilidade
            ])
            ->bulkActions([
                // NENHUMA AÇÃO EM MASSA
            ]);
    }
}