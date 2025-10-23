<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class TicketAssignedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $ticketId;
    public $assignedBy;
    public $projectName;
    public $actionRequired;

    public function __construct($ticketId, $assignedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->assignedBy = $assignedBy;
        $this->projectName = $projectName;
        $this->actionRequired = null;
    }

    public function setActionRequired($action)
    {
        $this->actionRequired = $action;
        return $this;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => uniqid('notif_', true),
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} has been assigned to you",
            'assigned_by' => $this->assignedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_ASSIGNED',
            'action_required' => $this->actionRequired,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
        if (!$notifiable) return [];

        Log::info('Broadcasting assignment to channel:', [
            'channel' => 'users.' . $notifiable->emp_id,
            'ticket_id' => $this->ticketId
        ]);

        return new PrivateChannel('users.' . $notifiable->emp_id);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }

    public function toDatabase($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => "You have been assigned to ticket {$this->ticketId}",
            'assigned_by' => $this->assignedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_ASSIGNED',
            'action_required' => $this->actionRequired,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
