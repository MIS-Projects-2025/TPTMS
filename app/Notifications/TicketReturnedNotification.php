<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class TicketReturnedNotification extends Notification implements ShouldBroadcast
{
    // use Queueable;

    public $ticketId;
    public $returnedBy;
    public $projectName;
    public $remarks;
    public $actionRequired;
    public $recipientId;

    public function __construct($ticketId, $returnedBy, $projectName, $remarks)
    {
        $this->ticketId = $ticketId;
        $this->returnedBy = $returnedBy;
        $this->projectName = $projectName;
        $this->remarks = $remarks;
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
            'message' => "Ticket {$this->ticketId} has been returned by {$this->returnedBy} for clarification",
            'returned_by' => $this->returnedBy,
            'project_name' => $this->projectName,
            'remarks' => $this->remarks,
            'type' => 'TICKET_RETURNED',
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
            'returned_by' => $this->returnedBy,
            'project_name' => $this->projectName,
            'remarks' => $this->remarks,
            'action_required' => $this->actionRequired,
            'recipient_id' => $this->recipientId,
            'message' => "Ticket {$this->ticketId} has been returned by {$this->returnedBy} for clarification",
            'type' => 'TICKET_RETURNED'
        ];
    }
}
