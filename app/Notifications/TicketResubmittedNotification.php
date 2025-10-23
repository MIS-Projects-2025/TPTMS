<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketResubmittedNotification extends Notification
{
    use Queueable;

    protected $ticketId;
    protected $resubmittedBy;
    protected $projectName;
    protected $actionRequired;

    public function __construct($ticketId, $resubmittedBy, $projectName)
    {
        $this->ticketId = $ticketId;
        $this->resubmittedBy = $resubmittedBy;
        $this->projectName = $projectName;
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
            'resubmitted_by' => $this->resubmittedBy,
            'project_name' => $this->projectName,
            'action_required' => $this->actionRequired,
            'message' => "Ticket {$this->ticketId} has been resubmitted by {$this->resubmittedBy} after clarification",
            'type' => 'TICKET_RESUBMITTED'
        ];
    }
}
