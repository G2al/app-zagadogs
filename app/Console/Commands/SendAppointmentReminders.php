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

        $this->info(sprintf(
            'Promemoria: %d inviati, %d da ritentare, %d falliti, %d saltati.',
            $result['sent'],
            $result['retry'],
            $result['failed'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
