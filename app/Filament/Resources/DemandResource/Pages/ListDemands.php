<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDemands extends ListRecords
{
    protected static string $resource = DemandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
