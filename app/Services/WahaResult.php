<?php

namespace App\Services;

/**
 * Esito di una chiamata a WAHA. Non lancia mai eccezioni: chi lo riceve decide cosa fare.
 */
final class WahaResult
{
    public const NOT_CONFIGURED = 'not_configured';
    public const INVALID_PHONE = 'invalid_phone';
    public const UNREACHABLE = 'unreachable';
    public const TIMEOUT = 'timeout';
    public const UNAUTHORIZED = 'unauthorized';
    public const SESSION_UNAVAILABLE = 'session_unavailable';
    public const HTTP_ERROR = 'http_error';

    private function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
        public readonly ?string $message = null,
        public readonly ?int $status = null,
        public readonly mixed $response = null,
    ) {
    }

    public static function ok(int $status, mixed $response): self
    {
        return new self(true, status: $status, response: $response);
    }

    public static function failed(string $error, string $message, ?int $status = null, mixed $response = null): self
    {
        return new self(false, $error, $message, $status, $response);
    }

    public function failedWith(string $error): bool
    {
        return ! $this->success && $this->error === $error;
    }
}
