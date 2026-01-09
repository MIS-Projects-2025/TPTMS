<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class TicketCreatedNotification extends Notification implements ShouldBroadcastNow
{
    // use Queueable;

    public $ticketId;
    public $requestType;
    public $creatorName;
    public $details;
    public $projectName;
    public $requestTypeLabel;
    public $actionRequired;
    public $recipientId;

    public function __construct($ticketId, $requestType, $creatorName, $details, $projectName, $requestTypeLabel = '')
    {
        Log::info('📝 1. NOTIFICATION CONSTRUCTED', [
            'ticket_id' => $ticketId,
            'request_type' => $requestType,
            'creator' => $creatorName
        ]);

        $this->ticketId = $ticketId;
        $this->requestType = $requestType;
        $this->creatorName = $creatorName;
        $this->details = $details;
        $this->projectName = $projectName;
        $this->requestTypeLabel = $requestTypeLabel;
        $this->actionRequired = null;
        $this->recipientId = null;
    }

    public function setRecipientId($recipientId)
    {
        Log::info('📌 2. RECIPIENT ID SET', [
            'recipient_id' => $recipientId,
            'ticket_id' => $this->ticketId
        ]);

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
        Log::info('📡 3. VIA METHOD CALLED', [
            'notifiable_emp_id' => $notifiable->emp_id ?? 'UNKNOWN',
            'recipient_id' => $this->recipientId,
            'ticket_id' => $this->ticketId,
            'channels' => ['database', 'broadcast']
        ]);

        return ['database', 'broadcast'];
    }

    public function toBroadcast($notifiable)
    {
        $message = [
            'id' => uniqid('notif_', true),
            'ticket_id' => $this->ticketId,
            'message' => "New ticket {$this->ticketId} created by {$this->creatorName}",
            'request_type' => $this->requestTypeLabel,
            'details' => substr($this->details, 0, 100),
            'project_name' => $this->projectName,
            'type' => 'TICKET_CREATED',
            'action_required' => $this->actionRequired,
            'timestamp' => now()->toDateTimeString(),
        ];

        Log::info('📤 4. TO BROADCAST METHOD CALLED', [
            'notifiable_emp_id' => $notifiable->emp_id ?? 'UNKNOWN',
            'recipient_id' => $this->recipientId,
            'ticket_id' => $this->ticketId,
            'message_payload' => $message
        ]);

        return new BroadcastMessage($message);
    }

    public function broadcastOn($notifiable = null)
    {
        Log::info('🎯 5. BROADCAST ON METHOD CALLED - START', [
            'notifiable_exists' => !is_null($notifiable),
            'notifiable_class' => $notifiable ? get_class($notifiable) : 'NULL',
            'notifiable_emp_id' => $notifiable ? ($notifiable->emp_id ?? 'NOT_SET') : 'NULL',
            'stored_recipient_id' => $this->recipientId,
            'ticket_id' => $this->ticketId
        ]);

        // Use the stored recipientId first, fallback to notifiable
        $recipientId = $this->recipientId;

        // If recipientId wasn't set via setRecipientId, try to get it from notifiable
        if (!$recipientId && $notifiable) {
            $recipientId = $notifiable->emp_id ?? null;
            Log::info('🔄 Using notifiable emp_id as fallback', [
                'fallback_recipient_id' => $recipientId
            ]);
        }

        Log::info('🎯 5. BROADCAST ON - RECIPIENT DETERMINED', [
            'final_recipient_id' => $recipientId,
            'final_channel' => $recipientId ? 'users.' . $recipientId : 'NO_CHANNEL'
        ]);

        if (!$recipientId) {
            Log::error('❌ NO RECIPIENT ID - BROADCAST CANCELLED', [
                'notification_class' => get_class($this),
                'ticket_id' => $this->ticketId,
                'notifiable_class' => $notifiable ? get_class($notifiable) : 'NULL',
                'stored_recipient_id' => $this->recipientId
            ]);
            return [];
        }

        $channel = new PrivateChannel('users.' . $recipientId);

        Log::info('✅ 6. BROADCASTING TO CHANNEL - FINAL', [
            'channel' => 'users.' . $recipientId,
            'channel_object' => get_class($channel),
            'ticket_id' => $this->ticketId,
            'recipient_id' => $recipientId
        ]);

        return $channel;
    }

    public function broadcastAs()
    {
        $eventName = 'notification.created';

        Log::info('🏷️ 7. BROADCAST AS METHOD CALLED', [
            'event_name' => $eventName,
            'ticket_id' => $this->ticketId,
            'recipient_id' => $this->recipientId
        ]);

        return $eventName;
    }

    public function toDatabase($notifiable)
    {
        Log::info('💾 DATABASE NOTIFICATION SAVED', [
            'notifiable_emp_id' => $notifiable->emp_id ?? 'UNKNOWN',
            'ticket_id' => $this->ticketId
        ]);

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
