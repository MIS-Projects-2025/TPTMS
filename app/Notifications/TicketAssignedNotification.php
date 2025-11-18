<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;

class TicketAssignedNotification extends Notification implements ShouldBroadcast
{
    // use Queueable;

    public $ticketId;
    public $assignedBy;
    public $projectName;
    public $actionRequired;
    public $recipientId;

    public function __construct($ticketId, $assignedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->assignedBy = $assignedBy;
        $this->projectName = $projectName;
        $this->actionRequired = null;
        $this->recipientId = null;
    }

    public function setRecipientId($recipientId)
    {
        $this->recipientId = $recipientId;
        return $this;
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
        // Use recipientId if set, otherwise fallback to notifiable
        $recipientId = $this->recipientId ?? ($notifiable->emp_id ?? null);

        if (!$recipientId) {
            return [];
        }

        return new PrivateChannel('users.' . $recipientId);
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
            'recipient_id' => $this->recipientId, // added recipientId to database entry
        ];
    }
}
