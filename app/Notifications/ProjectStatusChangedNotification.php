<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Log;

class ProjectStatusChangedNotification extends Notification implements ShouldBroadcast
{
    // use Queueable;

    public $projectId;
    public $projectName;
    public $oldStatus;
    public $newStatus;
    public $changedBy;
    public $department;
    public $actionRequired;
    public $recipientId; // NEW: optional recipient override

    public function __construct($projectId, $projectName, $oldStatus, $newStatus, $changedBy, $department)
    {
        $this->projectId = $projectId;
        $this->projectName = $projectName;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->changedBy = $changedBy;
        $this->department = $department;
        $this->actionRequired = null;
        $this->recipientId = null; // default null
    }

    public function setActionRequired($action)
    {
        $this->actionRequired = $action;
        return $this;
    }

    // NEW: set a specific recipient ID
    public function setRecipientId($recipientId)
    {
        $this->recipientId = $recipientId;
        return $this;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast($notifiable)
    {
        $oldStatusLabel = $this->getStatusLabel($this->oldStatus);
        $newStatusLabel = $this->getStatusLabel($this->newStatus);

        return new BroadcastMessage([
            'id' => uniqid('notif_', true),
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'message' => "Project '{$this->projectName}' status changed from {$oldStatusLabel} to {$newStatusLabel}",
            'old_status' => $oldStatusLabel,
            'new_status' => $newStatusLabel,
            'changed_by' => $this->changedBy,
            'department' => $this->department,
            'type' => 'PROJECT_STATUS_CHANGED',
            'action_required' => $this->actionRequired, // now uses set value
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function broadcastOn($notifiable = null)
    {
        $recipientId = $this->recipientId ?? ($notifiable->emp_id ?? null);
        if (!$recipientId) return [];

        Log::info('Broadcasting project status change to channel:', [
            'channel' => 'users.' . $recipientId,
            'project_id' => $this->projectId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus
        ]);

        return new PrivateChannel('users.' . $recipientId);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }

    public function toDatabase($notifiable)
    {
        $oldStatusLabel = $this->getStatusLabel($this->oldStatus);
        $newStatusLabel = $this->getStatusLabel($this->newStatus);

        return [
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'message' => "Project '{$this->projectName}' status changed from {$oldStatusLabel} to {$newStatusLabel}",
            'old_status' => $oldStatusLabel,
            'new_status' => $newStatusLabel,
            'changed_by' => $this->changedBy,
            'department' => $this->department,
            'type' => 'PROJECT_STATUS_CHANGED',
            'action_required' => $this->actionRequired,
            'redirect_url' => route('project.list'),
            'created_at' => now()->toDateTimeString(),
        ];
    }

    private function getStatusLabel($status)
    {
        $labels = [
            1 => 'Pending',
            2 => 'Ready',
            3 => 'In Progress',
            4 => 'On Hold',
            5 => 'Deployed',
            6 => 'Cancelled',
            7 => 'Inactive',
        ];

        return $labels[$status] ?? 'Unknown';
    }
}
