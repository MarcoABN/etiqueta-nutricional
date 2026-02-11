<?php

namespace App\Filament\Resources\PctabprTnResource\Pages;

use App\Filament\Resources\PctabprTnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPctabprTns extends ListRecords
{
    protected static string $resource = PctabprTnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
