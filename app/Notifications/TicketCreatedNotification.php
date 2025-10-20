<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $ticketId;
    public $requestType;
    public $creatorName;
    public $details;
    public $projectName;
    public $requestTypeLabel;

    public function __construct($ticketId, $requestType, $creatorName, $details, $projectName, $requestTypeLabel = '')
    {
        $this->ticketId = $ticketId;
        $this->requestType = $requestType;
        $this->creatorName = $creatorName;
        $this->details = $details;
        $this->projectName = $projectName;
        $this->requestTypeLabel = $requestTypeLabel;
    }

    public function via($notifiable)
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'ticket_id' => $this->ticketId,
            'message' => "New ticket {$this->ticketId} created by {$this->creatorName}",
            'request_type' => $this->requestTypeLabel,
            'details' => substr($this->details, 0, 100),
            'project_name' => $this->projectName,
            'type' => 'TICKET_CREATED',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }


    public function toDatabase($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => "New ticket {$this->ticketId} created by {$this->creatorName}",
            'request_type' => $this->requestTypeLabel,
            'details' => $this->details,
            'project_name' => $this->projectName,
            'type' => 'TICKET_CREATED',
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
