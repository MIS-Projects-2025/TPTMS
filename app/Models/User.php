<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Use the employees table and a different database connection
     */
    protected $connection = 'masterlist'; // 👈 your secondary DB connection name
    protected $table = 'employee_masterlist';
    protected $primaryKey = 'EMPLOYID';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'EMPLOYID',
        'EMP_NAME',
        'DEPARTMENT',
        'JOB_TITLE',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get unread notification count
     */
    public function getUnreadNotificationsCount()
    {
        return $this->notifications()
            ->whereNull('read_at')
            ->count();
    }
}
