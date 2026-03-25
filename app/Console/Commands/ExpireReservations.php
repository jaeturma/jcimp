<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature   = 'reservations:expire';
    protected $description = 'Release expired ticket reservations and restore inventory';

    public function handle(ReservationService $service): int
    {
        $count = $service->expireStale();

        $this->info("Expired {$count} reservation(s).");

        return Command::SUCCESS;
    }
}
