<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketClosedNotification extends Notification
{
    // use Queueable;

    protected $ticketId;
    protected $closedBy;
    protected $projectName;
    protected $rating;
    protected $actionRequired;
    protected $recipientId;

    public function __construct($ticketId, $closedBy, $projectName, $rating = null)
    {
        $this->ticketId = $ticketId;
        $this->closedBy = $closedBy;
        $this->projectName = $projectName;
        $this->rating = $rating;
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
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'closed_by' => $this->closedBy,
            'project_name' => $this->projectName,
            'rating' => $this->rating,
            'action_required' => $this->actionRequired,
            'recipient_id' => $this->recipientId, // added recipientId
            'message' => "Ticket {$this->ticketId} has been closed by {$this->closedBy}" .
                ($this->rating ? " with rating {$this->rating}/5" : ""),
            'type' => 'TICKET_CLOSED',
        ];
    }
}
