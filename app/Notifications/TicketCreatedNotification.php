<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class TicketCreatedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $ticketId;
    public $requestType;
    public $creatorName;
    public $details;
    public $projectName;
    public $requestTypeLabel;
    public $actionRequired;

    public function __construct($ticketId, $requestType, $creatorName, $details, $projectName, $requestTypeLabel = '')
    {
        $this->ticketId = $ticketId;
        $this->requestType = $requestType;
        $this->creatorName = $creatorName;
        $this->details = $details;
        $this->projectName = $projectName;
        $this->requestTypeLabel = $requestTypeLabel;
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
            'message' => "New ticket {$this->ticketId} created by {$this->creatorName}",
            'request_type' => $this->requestTypeLabel,
            'details' => substr($this->details, 0, 100),
            'project_name' => $this->projectName,
            'type' => 'TICKET_CREATED',
            'action_required' => $this->actionRequired,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
        if (!$notifiable) return [];

        Log::info('Broadcasting to channel:', [
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
            'message' => "New ticket {$this->ticketId} created by {$this->creatorName}",
            'request_type' => $this->requestTypeLabel,
            'details' => $this->details,
            'project_name' => $this->projectName,
            'type' => 'TICKET_CREATED',
            'action_required' => $this->actionRequired,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
