<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Servizi';
    protected static ?string $pluralModelLabel = 'Servizi';
    protected static ?string $modelLabel = 'Servizio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome servizio')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\ServiceCategory $record): string => $record->path())
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('price')
                    ->label('Prezzo di listino')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99999.99)
                    ->prefix('€')
                    ->helperText('Il prezzo si può cambiare su ogni singolo appuntamento.'),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label('Durata (minuti)')
                    ->helperText("Facoltativa: se vuota, l'appuntamento usa la durata predefinita.")
                    ->numeric()
                    ->integer()
                    ->minValue(5)
                    ->maxValue(720)
                    ->step(5),
                Forms\Components\ColorPicker::make('color')
                    ->label('Colore')
                    ->default('#16a34a'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servizio')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Durata')
                    ->suffix(' min')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Colore'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
