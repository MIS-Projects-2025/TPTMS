<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class TicketResolvedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $ticketId;
    public $resolvedBy;
    public $projectName;

    public function __construct($ticketId, $resolvedBy, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->resolvedBy = $resolvedBy;
        $this->projectName = $projectName;
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
            'action' => 'VERIFY',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
        if (!$notifiable) return [];

        Log::info('Broadcasting resolution to channel:', [
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
            'message' => "Ticket {$this->ticketId} is ready for verification",
            'resolved_by' => $this->resolvedBy,
            'project_name' => $this->projectName,
            'type' => 'TICKET_RESOLVED',
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
