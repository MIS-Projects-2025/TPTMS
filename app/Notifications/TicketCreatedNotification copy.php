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
    public $recipientId;
    public function __construct($ticketId, $requestType, $creatorName, $details, $projectName, $requestTypeLabel = '', $recipientId = null)
    {
        $this->ticketId = $ticketId;
        $this->requestType = $requestType;
        $this->creatorName = $creatorName;
        $this->details = $details;
        $this->projectName = $projectName;
        $this->requestTypeLabel = $requestTypeLabel;
        $this->actionRequired = null;
        $this->recipientId = $recipientId; // Store recipient ID
        Log::info('🎯 NOTIFICATION CONSTRUCTOR CALLED', [
            'ticket_id' => $ticketId,
            'recipient_id' => $recipientId,
            'implements_should_broadcast' => $this instanceof ShouldBroadcast
        ]);
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
        Log::info('🎯 1. TO BROADCAST METHOD CALLED', [
            'ticket_id' => $this->ticketId,
            'recipient_id' => $this->recipientId,
            'notifiable_id' => $notifiable ? $notifiable->emp_id : 'null',
            'channel' => 'users.' . $this->recipientId
        ]);

        $message = new BroadcastMessage([
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

        Log::info('🎯 2. BROADCAST MESSAGE CREATED', [
            'ticket_id' => $this->ticketId,
            'data' => $message->data
        ]);

        return $message;
    }
    public function broadcastWhen()
    {
        Log::info('🎯 BROADCAST WHEN CALLED', ['ticket_id' => $this->ticketId]);
        return true;
    }

    public function broadcastOn($notifiable = null)
    {
        $recipientId = $this->recipientId ?: ($notifiable ? $notifiable->emp_id : null);

        Log::info('🎯 3. BROADCAST ON METHOD CALLED', [
            'recipient_id' => $recipientId,
            'stored_recipient_id' => $this->recipientId,
            'notifiable' => $notifiable ? get_class($notifiable) : 'null',
            'final_channel' => $recipientId ? 'users.' . $recipientId : 'NO_CHANNEL'
        ]);

        if (!$recipientId) {
            Log::warning('🎯 NO RECIPIENT ID - BROADCAST CANCELLED');
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
