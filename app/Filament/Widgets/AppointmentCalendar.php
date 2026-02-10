<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Dog;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendar extends FullCalendarWidget
{
    protected static ?string $heading = 'Calendario Appuntamenti';

    protected static ?int $sort = 2;

    public function getModel(): ?string
    {
        return Appointment::class;
    }

    public function config(): array
    {
        return [
            'initialView' => 'dayGridWeek',
        ];
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
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Cognome')
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
                ->getOptionLabelFromRecordUsing(function (Dog $record): string {
                    $name = trim((string) ($record->name ?? ''));
                    if ($name !== '') {
                        return $name;
                    }

                    $breed = trim((string) ($record->breed ?? ''));
                    if ($breed !== '') {
                        return $breed;
                    }

                    return 'Senza nome';
                })
                ->searchable()
                ->preload()
                ->required()
                ->disabled(fn (callable $get) => blank($get('client_id')))
                ->reactive()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome cane')
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

            Forms\Components\Select::make('services')
                ->label('Servizi')
                ->relationship('services', 'name')
                ->multiple()
                ->preload()
                ->searchable(),

            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data e ora')
                ->minDate(now()->startOfMinute())
                ->seconds(false)
                ->reactive(),

            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->columnSpanFull(),
        ];
    }

    protected function configureAction(Action $action): void
    {
        if (! $action instanceof \Saade\FilamentFullCalendar\Actions\CreateAction) {
            if (! $action instanceof EditAction) {
                return;
            }
        }

        if ($action instanceof \Saade\FilamentFullCalendar\Actions\CreateAction) {
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
                })
                ->afterFormFilled(function () use ($action): void {
                    $action->extraModalFooterActions([
                        $action->makeModalSubmitAction('createAndWhatsapp', arguments: ['send_whatsapp' => true])
                            ->label('Invia WhatsApp')
                            ->color('success')
                            ->icon('heroicon-o-paper-airplane'),
                    ]);
                })
                ->after(function (array $arguments, Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $livewire->refreshRecords();

                    $sendWhatsApp = (bool) ($arguments['send_whatsapp'] ?? false);
                    if ($sendWhatsApp && filled($record->scheduled_at)) {
                        $record->update(['whatsapp_sent' => true]);

                        $url = $whatsAppService->sendAppointmentConfirmation($record);
                        $encodedUrl = json_encode($url);

                        $livewire->js("window.location.href = {$encodedUrl};");
                    }
                });

            return;
        }

        $action->extraModalFooterActions([
            Action::make('whatsapp')
                ->label('Conferma')
                ->color('success')
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentConfirmation($record);

                    $livewire->js('window.location.href = ' . json_encode($url));
                }),
            Action::make('whatsapp_reminder')
                ->label('Ricorda')
                ->color('warning')
                ->icon('heroicon-o-bell-alert')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentReminder($record);

                    $livewire->js('window.location.href = ' . json_encode($url));
                }),
            Action::make('delete')
                ->label('Elimina')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modal()
                ->modalIcon('heroicon-o-trash')
                ->modalIconColor('danger')
                ->cancelParentActions()
                ->modalHeading('Elimina appuntamento')
                ->modalDescription('Confermi l\'eliminazione di questo appuntamento?')
                ->modalSubmitActionLabel('Elimina')
                ->action(function (Appointment $record, $livewire): void {
                    $record->delete();

                    $livewire->refreshRecords();
                }),
        ]);
    }

    /**
     * Recupera gli eventi da mostrare nel calendario
     */
    public function fetchEvents(array $fetchInfo): array
    {
        return Appointment::query()
            ->where('status', 'confirmed')
            ->whereNotNull('scheduled_at')
            ->with(['client', 'dog', 'services'])
            ->get()
            ->map(function (Appointment $appointment) {
                $firstName = trim((string) ($appointment->client?->first_name ?? ''));
                $lastName = trim((string) ($appointment->client?->last_name ?? ''));
                $clientName = trim($firstName . ' ' . $lastName);

                if ($clientName === '') {
                    $dogName = trim((string) ($appointment->dog?->name ?? ''));
                    $dogBreed = trim((string) ($appointment->dog?->breed ?? ''));
                    $clientName = $dogName !== '' ? $dogName : $dogBreed;
                }

                if ($clientName === '') {
                    $clientName = 'Appuntamento';
                }

                $serviceColors = $appointment->services
                    ->pluck('color')
                    ->map(fn (?string $color) => trim((string) $color))
                    ->filter()
                    ->values();

                $serviceNames = $appointment->services
                    ->pluck('name')
                    ->map(fn (?string $name) => trim((string) $name))
                    ->filter()
                    ->values();

                $serviceColor = $serviceColors->first();

                return [
                    'id'    => $appointment->id,
                    'title' => $clientName,
                    'start' => $appointment->scheduled_at->toIso8601String(),
                    'backgroundColor' => $serviceColor ?: '#16a34a',
                    'borderColor' => $serviceColor ?: '#16a34a',
                    'serviceColors' => $serviceColors->toArray(),
                    'serviceNames' => $serviceNames->toArray(),
                ];
            })
            ->toArray();
    }

    public function eventClassNames(): string
    {
        return <<<'JS'
            function() {
                return ['zaga-event'];
            }
        JS;
    }

    public function eventContent(): string
    {
        return <<<'JS'
            function(arg) {
                const title = arg.event.title || '';
                const timeText = arg.timeText || '';
                const colors = Array.isArray(arg.event.extendedProps?.serviceColors)
                    ? arg.event.extendedProps.serviceColors
                    : [];
                const serviceNames = Array.isArray(arg.event.extendedProps?.serviceNames)
                    ? arg.event.extendedProps.serviceNames
                    : [];
                const serviceLine = timeText
                    ? `<div style="font-size:10px;opacity:.9;">${timeText}</div>`
                    : '';
                const servicesList = serviceNames.length
                    ? `<div style="margin-top:2px;display:flex;flex-direction:column;gap:2px;">
                        ${serviceNames.map((name, idx) => {
                            const color = colors[idx] || '#ffffff';
                            return `<div style="display:flex;align-items:center;gap:4px;">
                                <span style="width:6px;height:6px;border-radius:999px;background:${color};display:inline-block;"></span>
                                <span style="font-size:10px;opacity:.9;">${name}</span>
                            </div>`;
                        }).join('')}
                       </div>`
                    : '';

                return {
                    html: `<div style="line-height:1.1;">
                        <div style="font-weight:600;font-size:11px;">${title}</div>
                        ${serviceLine}
                        ${servicesList}
                    </div>`,
                };
            }
        JS;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
            function(info) {
                const el = info.el;
                const bg = info.event.backgroundColor || '#16a34a';
                el.style.backgroundColor = bg;
                el.style.borderColor = bg;
                el.style.color = '#ffffff';
                el.style.borderRadius = '8px';
                el.style.padding = '2px 6px';
                el.style.boxShadow = '0 1px 2px rgba(0,0,0,0.2)';
            }
        JS;
    }

    public function onEventClick(array $event): void
    {
        if ($this->getModel()) {
            $this->record = $this->resolveRecord($event['id']);
        }

        $this->mountAction('edit', [
            'type' => 'click',
            'event' => $event,
        ]);
    }

    public function refreshRecords(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
        $this->dispatch('appointments-to-schedule--refresh');
    }
}
