<?php

namespace App\Console\Commands;

use App\Services\SlackUserImporter;
use Illuminate\Console\Command;

class SyncSlackUsers extends Command
{
    protected $signature = 'hort:sync-slack-users';

    protected $description = 'Import/refresh all Slack workspace members as users';

    public function handle(SlackUserImporter $importer): int
    {
        $count = $importer->run();

        $this->info("{$count} Benutzer aus Slack synchronisiert.");

        return self::SUCCESS;
    }
}
