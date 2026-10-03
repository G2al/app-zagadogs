<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCategoryResource\Pages;
use App\Models\ServiceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Categorie servizi';
    protected static ?string $pluralModelLabel = 'Categorie servizi';
    protected static ?string $modelLabel = 'Categoria';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('parent_id')
                    ->label('Categoria madre')
                    ->helperText('Lascia vuoto per una categoria principale (es. Estetica); scegline una per creare una sottocategoria.')
                    ->relationship('parent', 'name', fn (Builder $query) => $query->whereNull('parent_id'))
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoria')
                    ->formatStateUsing(fn (string $state, ServiceCategory $record): string => $record->path())
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Servizi')
                    ->counts('services'),
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
            'index' => Pages\ListServiceCategories::route('/'),
            'create' => Pages\CreateServiceCategory::route('/create'),
            'edit' => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
