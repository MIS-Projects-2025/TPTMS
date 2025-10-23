<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketReturnedNotification extends Notification
{
    use Queueable;

    protected $ticketId;
    protected $returnedBy;
    protected $projectName;
    protected $remarks;
    protected $actionRequired;

    public function __construct($ticketId, $returnedBy, $projectName, $remarks)
    {
        $this->ticketId = $ticketId;
        $this->returnedBy = $returnedBy;
        $this->projectName = $projectName;
        $this->remarks = $remarks;
    }

    public function setActionRequired($action)
    {
        $this->actionRequired = $action;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'returned_by' => $this->returnedBy,
            'project_name' => $this->projectName,
            'remarks' => $this->remarks,
            'action_required' => $this->actionRequired,
            'message' => "Ticket {$this->ticketId} has been returned by {$this->returnedBy} for clarification",
            'type' => 'TICKET_RETURNED'
        ];
    }
}
