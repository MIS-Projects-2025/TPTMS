<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TicketService;

class CheckDueTicketsManual extends Command
{
    protected $signature = 'tickets:check-due-manual';
    protected $description = 'Manually check for overdue tickets and projects';

    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        parent::__construct();
        $this->ticketService = $ticketService;
    }

    public function handle()
    {
        $this->info('Checking for overdue tickets and updating associated projects...');

        try {
            // This handles both tickets AND their associated projects
            $ticketCount = $this->ticketService->updateOverdueTicketsToOnHold();

            $this->info("Updated {$ticketCount} tickets and their associated projects to On Hold status.");
        } catch (\Exception $e) {
            $this->error('Error checking due dates: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
