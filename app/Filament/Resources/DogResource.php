<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DogResource\Pages;
use App\Models\Dog;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DogResource extends Resource
{
    protected static ?string $model = Dog::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'Cani';
    protected static ?string $pluralModelLabel = 'Cani';
    protected static ?string $modelLabel = 'Cane';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'last_name')
                    ->getOptionLabelFromRecordUsing(function (Client $record): string {
                        $firstName = trim((string) ($record->first_name ?? ''));
                        $lastName = trim((string) ($record->last_name ?? ''));
                        $fullName = trim($lastName . ' ' . $firstName);

                        if ($fullName !== '') {
                            return $fullName;
                        }

                        $phone = trim((string) ($record->phone ?? ''));
                        return $phone !== '' ? $phone : 'Cliente senza nome';
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nome cane')
                    ->maxLength(255),

                Forms\Components\TextInput::make('breed')
                    ->label('Razza')
                    ->maxLength(255),

                Forms\Components\Textarea::make('details')
                    ->label('Dettagli particolari')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Cliente')
                    ->formatStateUsing(function (?string $state, Dog $record): string {
                        $firstName = trim((string) ($record->client?->first_name ?? ''));
                        $lastName = trim((string) ($record->client?->last_name ?? ''));
                        $fullName = trim($lastName . ' ' . $firstName);

                        if ($fullName !== '') {
                            return $fullName;
                        }

                        $phone = trim((string) ($record->client?->phone ?? ''));
                        return $phone !== '' ? $phone : 'Cliente senza nome';
                    })
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $terms = array_values(array_filter(preg_split('/\s+/', trim($search))));

                        return $query->whereHas('client', function (Builder $clientQuery) use ($terms): void {
                            foreach ($terms as $term) {
                                $clientQuery->where(function (Builder $inner) use ($term): void {
                                    $like = '%' . $term . '%';
                                    $inner
                                        ->where('first_name', 'like', $like)
                                        ->orWhere('last_name', 'like', $like)
                                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", [$like]);
                                });
                            }
                        });
                    }),

                Tables\Columns\TextColumn::make('breed')
                    ->label('Razza'),

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
            'index' => Pages\ListDogs::route('/'),
            'create' => Pages\CreateDog::route('/create'),
            'edit' => Pages\EditDog::route('/{record}/edit'),
        ];
    }
}
