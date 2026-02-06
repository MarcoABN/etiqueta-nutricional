<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequest extends CreateRecord
{
    protected static string $resource = RequestResource::class;

    // Removemos o redirecionamento forçado para 'edit'.
    // O padrão do Filament é ir para a lista após criar, o que é bom.
    // Mas se quiser continuar na tela para imprimir, use:
    
    protected function getRedirectUrl(): string
    {
        // Opção A: Vai para a listagem (Padrão)
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);

        // Opção B: Fica na tela para conferir/imprimir
        // return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}