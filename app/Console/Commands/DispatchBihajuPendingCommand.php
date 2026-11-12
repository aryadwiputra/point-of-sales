<?php

namespace App\Console\Commands;

use App\Services\Bihaju\BihajuService;
use Illuminate\Console\Command;

/**
 * Kirim ulang transaksi Bihaju yang masih tertunda/gagal sementara.
 *
 * Dijadwalkan tiap menit melalui `php artisan schedule:run` sehingga tidak
 * memerlukan worker queue terpisah.
 */
class DispatchBihajuPendingCommand extends Command
{
    protected $signature = 'bihaju:dispatch-pending
                            {--limit=50 : Jumlah record maksimal yang dicoba ulang}
                            {--tenant= : Batasi ke satu tenant saja}';

    protected $description = 'Retry pengiriman transaksi Bihaju yang masih tertunda';

    public function handle(BihajuService $bihaju): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $processed = $bihaju->retryPending($limit);

        if ($processed > 0) {
            $this->info("Berhasil mengirim {$processed} transaksi Bihaju.");
        } else {
            $this->line('Tidak ada transaksi Bihaju yang perlu dikirim.');
        }

        return self::SUCCESS;
    }
}
