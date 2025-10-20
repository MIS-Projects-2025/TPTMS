<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $ticketId;
    public $approvedBy;
    public $approvalType;
    public $projectName;

    public function __construct($ticketId, $approvedBy, $approvalType, $projectName = '')
    {
        $this->ticketId = $ticketId;
        $this->approvedBy = $approvedBy;
        $this->approvalType = $approvalType; // 'ASSESSMENT', 'DH', 'OD'
        $this->projectName = $projectName;
    }

    public function via($notifiable)
    {
        return ['broadcast', 'database'];
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
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} has been {$approvalLabel}",
            'approved_by' => $this->approvedBy,
            'approval_type' => $this->approvalType,
            'project_name' => $this->projectName,
            'type' => 'TICKET_APPROVED',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function toDatabase($notifiable)
    {
        return [
            'ticket_id' => $this->ticketId,
            'message' => "Ticket {$this->ticketId} approved by {$this->approvedBy}",
            'approval_type' => $this->approvalType,
            'project_name' => $this->projectName,
            'type' => 'TICKET_APPROVED',
        ];
    }
}
