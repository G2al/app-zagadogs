<?php

namespace App\Filament\Resources\DogResource\Pages;

use App\Filament\Resources\DogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDog extends CreateRecord
{
    protected static string $resource = DogResource::class;
}
