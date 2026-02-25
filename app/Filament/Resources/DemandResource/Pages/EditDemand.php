<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Filament\Resources\DemandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDemand extends EditRecord
{
    protected static string $resource = DemandResource::class;

    

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
