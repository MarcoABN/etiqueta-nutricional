<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use App\Models\Request;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Substituímos o CreateAction padrão por este Action customizado
            Actions\Action::make('novo_pedido')
                ->label('Nova Solicitação')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    // 1. Cria o registro com os padrões definidos
                    // O Model já tem o evento 'creating' para gerar o ID (SOL-2026...)
                    $record = Request::create([
                        'status' => 'aberto',
                        'created_at' => now(),
                    ]);

                    // 2. Redireciona direto para a tela de Edição (onde estão os itens)
                    return redirect()->to(RequestResource::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}