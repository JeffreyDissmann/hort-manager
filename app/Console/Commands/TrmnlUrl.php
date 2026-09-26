<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class TrmnlUrl extends Command
{
    protected $signature = 'hort:trmnl-url {--rotate : Issue a new token, invalidating every link handed out so far}';

    protected $description = 'Print the signed TRMNL dashboard URL to paste into a TRMNL private plugin (Polling).';

    public function handle(): int
    {
        if ($this->option('rotate')) {
            Setting::rotateTrmnlToken();
            $this->warn('Neues Token — der alte Link funktioniert nicht mehr. Bitte im TRMNL-Plugin ersetzen.');
        }

        // Signature (no expiry, tied to APP_KEY) plus the rotatable token, so a leaked
        // link can be revoked here without touching APP_KEY.
        $this->line(URL::signedRoute('trmnl.dashboard', ['token' => Setting::trmnlToken()]));

        return self::SUCCESS;
    }
}
