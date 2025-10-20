<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $ticketId;
    public $assignedBy;
    public $projectName;

    public function __construct($ticketId, $assignedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->assignedBy = $assignedBy;
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
            'message' => "Ticket {$this->ticketId} has been assigned to you",
            'assigned_by' => $this->assignedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_ASSIGNED',
            'action' => 'START_WORK',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function toDatabase($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => "You have been assigned to ticket {$this->ticketId}",
            'assigned_by' => $this->assignedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_ASSIGNED',
        ];
    }
}
