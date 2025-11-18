<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReverbListApps extends Command
{
    protected $signature = 'reverb:list-apps';
    protected $description = 'List all apps configured for Reverb';

    public function handle()
    {
        $apps = config('broadcasting.apps', []);

        if (empty($apps)) {
            $this->warn('No apps found in broadcasting.php config.');
            return;
        }

        $tableData = [];
        foreach ($apps as $app) {
            $tableData[] = [
                'App ID' => $app['app_id'] ?? '(none)',
                'App Key' => $app['key'] ?? '(none)',
                'App Secret' => $app['secret'] ?? '(none)',
            ];
        }

        $this->table(['App ID', 'App Key', 'App Secret'], $tableData);
    }
}
