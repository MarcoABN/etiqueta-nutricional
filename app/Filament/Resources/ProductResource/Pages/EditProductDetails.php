<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditProductDetails extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Detalhes do Produto';

    public function form(Form $form): Form
    {
        return $form->schema(ProductResource::getDetailsFormSchema());
    }

    protected function getHeaderActions(): array
    {
        return [
            // Novo botão para voltar para a tela de Tabela Nutricional
            Actions\Action::make('goToNutritional')
                ->label('Tabela Nutricional')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->url(fn () => $this->getResource()::getUrl('edit', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}