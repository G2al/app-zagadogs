<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Services\WahaService;
use App\Services\WhatsAppService;
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
                    ->with(['client', 'dog'])
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

                Tables\Columns\TextColumn::make('dog.name')
                    ->label('Cane')
                    ->formatStateUsing(function (Appointment $record): string {
                        $name = trim((string) ($record->dog?->name ?? ''));
                        if ($name !== '') {
                            return $name;
                        }

                        $breed = trim((string) ($record->dog?->breed ?? ''));
                        if ($breed !== '') {
                            return $breed;
                        }

                        return 'Senza nome';
                    })
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

                        // Solo senza WAHA: con WAHA configurato la conferma parte da sola e non serve aprire WhatsApp.
                        Forms\Components\Checkbox::make('send_whatsapp')
                            ->label('Invia conferma WhatsApp')
                            ->accepted()
                            ->required()
                            ->visible(fn (): bool => ! app(WahaService::class)->isConfigured()),
                    ])
                    ->action(function (array $data, Appointment $record, WhatsAppService $whatsAppService, WahaService $waha, $livewire): void {
                        $automatic = $waha->isConfigured();

                        // Lo stato diventa "confirmed": con WAHA la conferma e il promemoria partono da soli (AppointmentObserver).
                        $record->update([
                            'scheduled_at' => $data['scheduled_at'],
                            'status' => 'confirmed',
                        ] + ($automatic ? [] : ['whatsapp_sent' => true]));

                        $livewire->dispatch('filament-fullcalendar--refresh');
                        $livewire->dispatch('appointments-to-schedule--refresh');

                        if (! $automatic) {
                            $record->refresh()->loadMissing(['client', 'dog']);

                            $livewire->js('window.location.href = ' . json_encode($whatsAppService->sendAppointmentConfirmation($record)));
                        }
                    }),
            ])
            ->emptyStateHeading('Nessun appuntamento da programmare');
    }
}
