<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class NotificationUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'notification_users';
    protected $primaryKey = 'emp_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'emp_id',
        'emp_name',
        'emp_dept',
    ];

    /**
     * Channel for broadcasting notifications
     */
    public function receivesBroadcastNotificationsOn()
    {
        return 'users.' . $this->emp_id;
    }

    /**
     * Required for Authenticatable
     */
    public function getAuthIdentifierName()
    {
        return 'emp_id';
    }

    public function getAuthIdentifier()
    {
        return $this->emp_id;
    }
}
