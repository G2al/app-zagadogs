<?php

namespace App\Console\Commands;

use App\Services\AppointmentWhatsAppNotifier;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Invia via WhatsApp i promemoria degli appuntamenti arrivati a scadenza';

    public function handle(AppointmentWhatsAppNotifier $notifier): int
    {
        $result = $notifier->sendDueReminders();

        if (! $result['configured']) {
            $this->line('WAHA non configurato (WAHA_URL, WAHA_API_KEY, WAHA_SESSION nel .env): non faccio niente.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Promemoria: %d pianificati ora, %d inviati, %d da ritentare, %d falliti, %d saltati.',
            $result['planned'],
            $result['sent'],
            $result['retry'],
            $result['failed'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
