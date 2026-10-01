<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AppointmentsToSchedule extends TableWidget
{
    protected static ?string $heading = 'Appuntamenti da programmare';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    // 🔥 DISABILITA IL LAZY LOADING (RISOLVE IL 500)
    protected static bool $isLazy = false;

    protected $listeners = [
        'appointments-to-schedule--refresh' => '$refresh',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->pending()
                    ->with(['client', 'staff'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('client.last_name')
                    ->label('Cliente')
                    ->formatStateUsing(fn (Appointment $record): string =>
                        trim(
                            ($record->client->last_name ?? '') . ' ' .
                            ($record->client->first_name ?? '')
                        )
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->wrap()
                    ->limit(60),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('schedule')
                    ->label('Programma')
                    ->icon('heroicon-o-calendar')
                    ->modalHeading('Programma appuntamento')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Data e ora')
                            ->minDate(now()->startOfMinute())
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(function (array $data, Appointment $record, $livewire): void {
                        // Lo stato diventa "confirmed": la conferma WhatsApp parte da sola (AppointmentObserver).
                        $record->update([
                            'scheduled_at' => $data['scheduled_at'],
                            'status' => 'confirmed',
                        ]);

                        $livewire->dispatch('filament-fullcalendar--refresh');
                        $livewire->dispatch('appointments-to-schedule--refresh');
                    }),
            ])
            ->emptyStateHeading('Nessun appuntamento da programmare');
    }
}
