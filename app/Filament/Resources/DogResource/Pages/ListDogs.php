<?php

namespace App\Filament\Resources\DogResource\Pages;

use App\Filament\Resources\DogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDogs extends ListRecords
{
    protected static string $resource = DogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
