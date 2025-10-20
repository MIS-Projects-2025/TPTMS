<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class NotificationUser extends Model
{
    use Notifiable;

    protected $table = 'notification_users'; // ← YOUR table
    protected $primaryKey = 'emp_id';
    protected $keyType = 'string';
    protected $connection = 'mysql';
    public $timestamps = true;

    protected $fillable = [
        'emp_id',
        'emp_name',
        'emp_dept',
    ];
}
