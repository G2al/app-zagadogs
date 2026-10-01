<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client minimale per WAHA (WhatsApp HTTP API). Indipendente dai model dell'app:
 * riceve un numero e un testo, e restituisce un WahaResult senza mai lanciare eccezioni.
 */
class WahaService
{
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 15;

    public function sendText(string $phone, string $message): WahaResult
    {
        $url = rtrim((string) config('services.waha.url'), '/');
        $apiKey = (string) config('services.waha.api_key');
        $session = (string) config('services.waha.session');

        if ($url === '' || $apiKey === '' || $session === '') {
            return $this->fail(WahaResult::NOT_CONFIGURED, 'WAHA non configurato: controlla WAHA_URL, WAHA_API_KEY e WAHA_SESSION nel .env.');
        }

        $chatId = $this->toChatId($phone);

        if ($chatId === null) {
            return $this->fail(WahaResult::INVALID_PHONE, 'Numero di telefono non valido.', context: ['phone' => $this->maskPhone($phone)]);
        }

        $context = ['chat_id' => $this->maskChatId($chatId), 'session' => $session, 'text_length' => mb_strlen($message)];

        try {
            $response = Http::withHeaders(['X-Api-Key' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->post($url . '/api/sendText', [
                    'chatId' => $chatId,
                    'text' => $message,
                    'session' => $session,
                ]);
        } catch (ConnectionException $e) {
            return $this->connectionFailure($e, $context);
        } catch (Throwable $e) {
            return $this->fail(WahaResult::HTTP_ERROR, 'Errore imprevisto nella chiamata a WAHA.', context: $context + ['exception' => $e::class, 'detail' => $e->getMessage()]);
        }

        return $this->fromResponse($response, $context);
    }

    /**
     * Converte un numero in chatId WAHA ("393489039214@c.us"), oppure null se non valido.
     * Accetta "+39 348 903 9214", "3489039214", "393489039214", "0039348...".
     * Senza prefisso internazionale il numero è considerato italiano; con "+" o "00"
     * seguiti da un prefisso diverso da 39 viene lasciato com'è.
     */
    public function toChatId(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $explicitInternational = str_starts_with(ltrim($phone), '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        if ($explicitInternational && ! str_starts_with($digits, '39')) {
            // Numero estero esplicito: 8-15 cifre secondo E.164.
            return preg_match('/^\d{8,15}$/', $digits) ? $digits . '@c.us' : null;
        }

        // Un numero italiano con prefisso ha almeno 11 cifre (39 + 9 della fascia più corta);
        // un cellulare senza prefisso ne ha 10 e può iniziare per "39" (es. 393...).
        if (! (str_starts_with($digits, '39') && strlen($digits) >= 11)) {
            $digits = '39' . $digits;
        }

        return preg_match('/^39\d{9,11}$/', $digits) ? $digits . '@c.us' : null;
    }

    private function fromResponse(Response $response, array $context): WahaResult
    {
        $status = $response->status();
        $body = $response->json() ?? $response->body();

        if ($response->successful()) {
            return WahaResult::ok($status, $body);
        }

        [$error, $message] = match (true) {
            in_array($status, [401, 403], true) => [WahaResult::UNAUTHORIZED, 'WAHA ha rifiutato la API key.'],
            $status === 404 || $this->sessionProblem($status, $body) => [WahaResult::SESSION_UNAVAILABLE, 'Sessione WAHA non disponibile o non avviata.'],
            default => [WahaResult::HTTP_ERROR, "WAHA ha risposto con errore HTTP {$status}."],
        };

        return $this->fail($error, $message, $status, $body, $context);
    }

    private function sessionProblem(int $status, mixed $body): bool
    {
        if ($status !== 422 && $status !== 400 && $status !== 500) {
            return false;
        }

        $text = strtolower(is_string($body) ? $body : (string) json_encode($body));

        return str_contains($text, 'session');
    }

    private function connectionFailure(ConnectionException $e, array $context): WahaResult
    {
        $timedOut = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28');

        return $timedOut
            ? $this->fail(WahaResult::TIMEOUT, 'Timeout nella chiamata a WAHA.', context: $context)
            : $this->fail(WahaResult::UNREACHABLE, 'WAHA non raggiungibile: è avviato e l\'URL è corretto?', context: $context);
    }

    private function fail(string $error, string $message, ?int $status = null, mixed $response = null, array $context = []): WahaResult
    {
        Log::warning('WAHA sendText fallito', $context + [
            'error' => $error,
            'message' => $message,
            'status' => $status,
            'response' => is_string($response) ? mb_substr($response, 0, 500) : $response,
        ]);

        return WahaResult::failed($error, $message, $status, $response);
    }

    private function maskChatId(string $chatId): string
    {
        return $this->maskPhone(strstr($chatId, '@', true) ?: $chatId);
    }

    /** Mostra solo le ultime 3 cifre nei log. */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? '(vuoto)' : str_repeat('*', max(strlen($digits) - 3, 0)) . substr($digits, -3);
    }
}
