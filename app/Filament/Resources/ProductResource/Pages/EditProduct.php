<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Novo botão para ir para a tela de Detalhes
            Actions\Action::make('goToDetails')
                ->label('Editar Detalhes')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('warning')
                ->url(fn () => $this->getResource()::getUrl('edit-details', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}