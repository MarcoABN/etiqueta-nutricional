<?php

namespace App\Filament\Resources\PctabprTnResource\Pages;

use App\Filament\Resources\PctabprTnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPctabprTn extends EditRecord
{
    protected static string $resource = PctabprTnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
