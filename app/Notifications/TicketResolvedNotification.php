<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public $ticketId;
    public $resolvedBy;
    public $projectName;

    public function __construct($ticketId, $resolvedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->resolvedBy = $resolvedBy;
        $this->projectName = $projectName;
    }

    public function via($notifiable)
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} has been resolved",
            'resolved_by' => $this->resolvedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESOLVED',
            'action' => 'VERIFY',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function toDatabase($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} is ready for verification",
            'resolved_by' => $this->resolvedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESOLVED',
        ];
    }
}
