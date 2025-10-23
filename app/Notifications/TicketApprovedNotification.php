<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class TicketApprovedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $ticketId;
    public $approvedBy;
    public $approvalType;
    public $projectName;
    public $actionRequired;

    public function __construct($ticketId, $approvedBy, $approvalType, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->approvedBy = $approvedBy;
        $this->approvalType = $approvalType; // 'ASSESSMENT', 'DH', 'OD'
        $this->projectName = $projectName;
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
        $approvalLabel = match ($this->approvalType) {
            'ASSESSMENT' => 'assessed',
            'DH' => 'approved by Department Head',
            'OD' => 'approved by Operations Director',
            default => 'updated',
        };

        return new BroadcastMessage([
            'id' => uniqid('notif_', true),
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} has been {$approvalLabel}",
            'approved_by' => $this->approvedBy,
            'approval_type' => $this->approvalType,
            'project_name' => $this->projectName,
            'type' => 'TICKET_APPROVED',
            'action_required' => $this->actionRequired,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
        if (!$notifiable) return [];

        Log::info('Broadcasting approval to channel:', [
            'channel' => 'users.' . $notifiable->emp_id,
            'ticket_id' => $this->ticketId,
            'approval_type' => $this->approvalType
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
            'message' => "Ticket {$this->ticketId} approved by {$this->approvedBy}",
            'approval_type' => $this->approvalType,
            'project_name' => $this->projectName,
            'type' => 'TICKET_APPROVED',
            'action_required' => $this->actionRequired,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
