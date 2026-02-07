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
                ->minDate(now()->startOfMinute())
                ->seconds(false)
                ->reactive(),

            Forms\Components\Checkbox::make('send_whatsapp')
                ->label('Invia conferma WhatsApp')
                ->accepted(fn (string $operation, callable $get) => $operation === 'create' && filled($get('scheduled_at')))
                ->required(fn (string $operation, callable $get) => $operation === 'create' && filled($get('scheduled_at')))
                ->visible(fn (string $operation, callable $get) => $operation === 'create' && filled($get('scheduled_at'))),

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
                ->after(function (array $data, Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $livewire->refreshRecords();

                    $sendWhatsApp = (bool) ($data['send_whatsapp'] ?? false);

                    if ($sendWhatsApp && filled($record->scheduled_at)) {
                        $record->update(['whatsapp_sent' => true]);

                        $url = $whatsAppService->sendAppointmentConfirmation($record);

                        $livewire->js('window.open(' . json_encode($url) . ', "_blank")');
                    }
                });

            return;
        }

        $action->extraModalFooterActions([
            Action::make('whatsapp')
                ->label('WhatsApp')
                ->color('success')
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentConfirmation($record);

                    $livewire->js('window.open(' . json_encode($url) . ', "_blank")');
                }),
            Action::make('whatsapp_reminder')
                ->label('Ricorda appuntamento')
                ->color('success')
                ->icon('heroicon-o-bell-alert')
                ->action(function (Appointment $record, WhatsAppService $whatsAppService, $livewire): void {
                    $url = $whatsAppService->sendAppointmentReminder($record);

                    $livewire->js('window.open(' . json_encode($url) . ', "_blank")');
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
            ->with(['client', 'dog'])
            ->get()
            ->map(function (Appointment $appointment) {
                return [
                    'id'    => $appointment->id,
                    'title' => $appointment->dog->name,
                    'start' => $appointment->scheduled_at->toIso8601String(),
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
                const time = arg.timeText ? `<div style="font-size:11px;opacity:.9;">${arg.timeText}</div>` : '';

                return {
                    html: `<div style="line-height:1.1;">
                        <div style="font-weight:600;">${title}</div>
                        ${time}
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
                el.style.backgroundColor = '#16a34a';
                el.style.borderColor = '#15803d';
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
