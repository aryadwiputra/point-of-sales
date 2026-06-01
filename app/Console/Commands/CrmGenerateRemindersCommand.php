<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrmReminders\GenerateCrmRemindersService;
use Illuminate\Console\Command;

class CrmGenerateRemindersCommand extends Command
{
    protected $signature = 'crm:generate-reminders';

    protected $description = 'Generate CRM reminder campaigns for due soon, overdue, and repeat order';

    public function handle(GenerateCrmRemindersService $service): int
    {
        $service->execute();

        $this->info('CRM reminders generated.');

        return self::SUCCESS;
    }
}
