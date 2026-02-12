<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Appuntamenti';
    protected static ?string $pluralModelLabel = 'Appuntamenti';
    protected static ?string $modelLabel = 'Appuntamento';

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
                    ->required()
                    ->reactive(),

                Forms\Components\Select::make('dog_id')
                    ->label('Cane')
                    ->options(fn (callable $get) => Dog::where('client_id', $get('client_id'))
                        ->get()
                        ->mapWithKeys(function (Dog $dog): array {
                            $name = trim((string) ($dog->name ?? ''));
                            $breed = trim((string) ($dog->breed ?? ''));
                            $label = $name !== '' ? $name : ($breed !== '' ? $breed : 'Senza nome');

                            return [$dog->id => $label];
                        })
                        ->all())
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('client_id'))),

                Forms\Components\Select::make('services')
                    ->label('Servizi')
                    ->relationship('services', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),

                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Data e ora')
                    ->minDate(now()->startOfMinute())
                    ->seconds(false),

                Forms\Components\Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->label('Stato')
                    ->options([
                        'pending' => 'In attesa',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Cliente')
                    ->formatStateUsing(function (?string $state, Appointment $record): string {
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

                Tables\Columns\TextColumn::make('dog.name')
                    ->label('Cane')
                    ->formatStateUsing(function (?string $state, Appointment $record): string {
                        if ($state !== null && trim($state) !== '') {
                            return trim($state);
                        }

                        $breed = trim((string) ($record->dog?->breed ?? ''));
                        if ($breed !== '') {
                            return $breed;
                        }

                        return 'Senza nome';
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('services.name')
                    ->label('Servizi')
                    ->badge()
                    ->separator(', ')
                    ->limitList(3)
                    ->searchable(),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Data / Ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'In attesa',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                    }),
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
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
