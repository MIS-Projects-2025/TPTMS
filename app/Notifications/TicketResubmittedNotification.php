<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class TicketResubmittedNotification extends Notification implements ShouldBroadcast
{
    // use Queueable;

    public $ticketId;
    public $resubmittedBy;
    public $projectName;
    public $actionRequired;
    public $recipientId;

    public function __construct($ticketId, $resubmittedBy, $projectName)
    {
        $this->ticketId = $ticketId;
        $this->resubmittedBy = $resubmittedBy;
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
            'message' => "Ticket {$this->ticketId} has been resubmitted by {$this->resubmittedBy} after clarification",
            'resubmitted_by' => $this->resubmittedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESUBMITTED',
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

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'resubmitted_by' => $this->resubmittedBy,
            'project_name' => $this->projectName,
            'action_required' => $this->actionRequired,
            'recipient_id' => $this->recipientId, // added recipientId
            'message' => "Ticket {$this->ticketId} has been resubmitted by {$this->resubmittedBy} after clarification",
            'type' => 'TICKET_RESUBMITTED',
        ];
    }
}
