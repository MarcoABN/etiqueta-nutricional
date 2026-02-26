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
            Actions\Action::make('novo_pedido')
                ->label('Nova Solicitação')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    $record = Request::create([
                        'status' => 'aberto',
                        'shipping_type' => 'Maritimo',
                        'created_at' => now(),
                    ]);

                    return redirect()->to(RequestResource::getUrl('edit', ['record' => $record]));
                }),
        ];
    }
}