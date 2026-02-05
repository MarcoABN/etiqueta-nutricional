<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Ícone do menu lateral
    protected static ?string $navigationIcon = 'heroicon-o-users';

    // Traduções do Título do Recurso
    protected static ?string $modelLabel = 'Usuário';
    protected static ?string $pluralModelLabel = 'Usuários';
    protected static ?string $navigationLabel = 'Usuários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Usuário')
                    ->description('Preencha as informações de login.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable() // Botão para mostrar/ocultar senha
                            // Regras de segurança (Mínimo 8 chars, letras, números, símbolos)
                            ->rule(Password::min(8)->letters()->mixedCase()->numbers()->symbols())
                            // Obrigatório apenas na criação (create)
                            ->required(fn (string $context): bool => $context === 'create')
                            // Se estiver vazio na edição, não altera a senha atual
                            ->dehydrated(fn ($state) => filled($state))
                            // Criptografa a senha antes de salvar
                            ->mutateDehydratedStateUsing(fn ($state) => Hash::make($state))
                            ->helperText('Na edição, deixe em branco para manter a senha atual.')
                            ->columnSpanFull(), 
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por padrão

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable() // Permite copiar com um clique
                    ->copyMessage('E-mail copiado!')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc') // Ordena pelos mais recentes
            ->filters([
                // Aqui você pode adicionar filtros futuramente
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Excluir'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ])->label('Ações em Massa'),
            ])
            ->emptyStateHeading('Nenhum usuário encontrado')
            ->emptyStateDescription('Crie um novo usuário para começar.');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}