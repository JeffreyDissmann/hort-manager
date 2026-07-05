<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class TrmnlUrl extends Command
{
    protected $signature = 'hort:trmnl-url';

    protected $description = 'Print the signed TRMNL dashboard URL to paste into a TRMNL private plugin (Polling).';

    public function handle(): int
    {
        // Permanent signature (no expiry) — tied to APP_KEY; rotating the key invalidates it.
        $this->line(URL::signedRoute('trmnl.dashboard'));

        return self::SUCCESS;
    }
}
