<?php

declare(strict_types=1);

namespace App\Console\Commands\DeviceControl;

use App\Domain\DeviceControl\Services\ControlWidgetTemplateRegistry;
use Illuminate\Console\Command;

class SyncControlWidgetTemplatesCommand extends Command
{
    protected $signature = 'device-control:sync-widget-templates {--prune : Remove templates that are no longer defined}';

    protected $description = 'Register control widget templates in the database for dashboard/runtime discovery.';

    public function handle(ControlWidgetTemplateRegistry $registry): int
    {
        $summary = $registry->sync(prune: (bool) $this->option('prune'));

        $this->info('Control widget templates synchronized.');
        $this->line("Created: {$summary['created']}");
        $this->line("Updated: {$summary['updated']}");
        $this->line("Pruned: {$summary['pruned']}");
        $this->line("Registered templates: {$summary['total']}");

        return self::SUCCESS;
    }
}
