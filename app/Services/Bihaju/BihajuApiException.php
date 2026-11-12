<?php

namespace App\Services\Bihaju;

use RuntimeException;

/**
 * Error dari respons HTTP Bihaju (membedakan gagal sementara vs permanen).
 */
class BihajuApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
    ) {
        parent::__construct($message);
    }

    /**
     * Error yang tidak akan berhasil meskipun dicoba ulang
     * (konfigurasi salah, saldo kurang, member tidak ditemukan, dsb.).
     */
    public function isPermanent(): bool
    {
        return in_array($this->statusCode, [400, 401, 403, 404, 422], true);
    }
}
