<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TicketService;
use Illuminate\Support\Facades\Log;

class CheckDueTickets extends Command
{
    protected $signature = 'tickets:check-due';
    protected $description = 'Check for overdue tickets and update status to On Hold';

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
            Log::info("Due date checker: Updated {$ticketCount} tickets and their projects to On Hold.");
        } catch (\Exception $e) {
            $this->error('Error checking due dates: ' . $e->getMessage());
            Log::error('Due date checker error: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
