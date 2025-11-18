<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;

class TicketResolvedNotification extends Notification implements ShouldBroadcast
{
    // use Queueable;

    public $ticketId;
    public $resolvedBy;
    public $projectName;
    public $actionRequired;
    public $recipientId;

    public function __construct($ticketId, $resolvedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->resolvedBy = $resolvedBy;
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
            'message' => "Ticket {$this->ticketId} has been resolved",
            'resolved_by' => $this->resolvedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESOLVED',
            'action_required' => $this->actionRequired,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
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
            'message' => "Ticket {$this->ticketId} is ready for verification",
            'resolved_by' => $this->resolvedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESOLVED',
            'action_required' => $this->actionRequired,
            'recipient_id' => $this->recipientId, // added recipientId
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
