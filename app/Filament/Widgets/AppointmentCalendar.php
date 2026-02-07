<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendar extends FullCalendarWidget
{
    protected static ?string $heading = 'Calendario Appuntamenti';

    protected static ?int $sort = 2;

    public function getModel(): ?string
    {
        return Appointment::class;
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('client_id')
                ->label('Cliente')
                ->relationship('client', 'last_name')
                ->getOptionLabelFromRecordUsing(
                    fn (Client $record): string => trim($record->last_name . ' ' . $record->first_name)
                )
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('dog_id', null))
                ->createOptionForm([
                    Forms\Components\TextInput::make('first_name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Cognome')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefono')
                        ->required()
                        ->unique(table: Client::class, column: 'phone')
                        ->maxLength(255),
                ]),

            Forms\Components\Select::make('dog_id')
                ->label('Cane')
                ->relationship(
                    'dog',
                    'name',
                    modifyQueryUsing: fn (Builder $query, callable $get) => $query->where('client_id', $get('client_id'))
                )
                ->searchable()
                ->preload()
                ->required()
                ->disabled(fn (callable $get) => blank($get('client_id')))
                ->reactive()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome cane')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('breed')
                        ->label('Razza')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('details')
                        ->label('Dettagli particolari')
                        ->columnSpanFull(),
                ])
                ->createOptionUsing(function (array $data, callable $get): int {
                    $data['client_id'] = $get('client_id');

                    return Dog::query()->create($data)->getKey();
                }),

            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data e ora')
                ->seconds(false),

            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->columnSpanFull(),
        ];
    }

    protected function configureAction(Action $action): void
    {
        if (! $action instanceof \Saade\FilamentFullCalendar\Actions\CreateAction) {
            return;
        }

        $action
            ->mountUsing(function (Form $form, array $arguments): void {
                $start = $arguments['start'] ?? null;

                $form->fill([
                    'scheduled_at' => $start,
                ]);
            })
            ->mutateFormDataUsing(function (array $data): array {
                $hasSchedule = filled($data['scheduled_at'] ?? null);

                $data['scheduled_at'] = $hasSchedule ? $data['scheduled_at'] : null;
                $data['status'] = $hasSchedule ? 'confirmed' : 'pending';

                return $data;
            });
    }

    /**
     * Recupera gli eventi da mostrare nel calendario
     */
    public function fetchEvents(array $fetchInfo): array
    {
        return Appointment::query()
            ->where('status', 'confirmed')
            ->whereNotNull('scheduled_at')
            ->with(['client', 'dog'])
            ->get()
            ->map(function (Appointment $appointment) {
                return [
                    'id'    => $appointment->id,
                    'title' => $appointment->client->last_name . ' - ' . $appointment->dog->name,
                    'start' => $appointment->scheduled_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    public function refreshRecords(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
        $this->dispatch('appointments-to-schedule--refresh');
    }
}
