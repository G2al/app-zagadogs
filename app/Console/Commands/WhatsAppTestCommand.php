<?php

namespace App\Console\Commands;

use App\Services\WahaService;
use Illuminate\Console\Command;

class WhatsAppTestCommand extends Command
{
    protected $signature = 'whatsapp:test
        {phone : Numero del destinatario (es. 3489039214, +39 348 903 9214, 393489039214)}
        {message : Testo del messaggio}';

    protected $description = 'Invia un messaggio WhatsApp di prova tramite WAHA';

    public function handle(WahaService $waha): int
    {
        $phone = (string) $this->argument('phone');
        $chatId = $waha->toChatId($phone);

        $this->line('WAHA:      ' . config('services.waha.url'));
        $this->line('Sessione:  ' . config('services.waha.session'));
        $this->line('Chat ID:   ' . ($chatId ?? '(numero non valido)'));

        $result = $waha->sendText($phone, (string) $this->argument('message'));

        if ($result->success) {
            $this->info("Messaggio inviato (HTTP {$result->status}).");
            $this->line(json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->error("Invio fallito [{$result->error}]: {$result->message}");

        if ($result->status !== null) {
            $this->line("HTTP {$result->status}");
        }

        if ($result->response !== null) {
            $this->line(is_string($result->response) ? $result->response : json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::FAILURE;
    }
}
