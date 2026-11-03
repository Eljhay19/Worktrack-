<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class WorkProfile extends Authenticatable
{
    protected $fillable = [
        'system_user_id',
        'browser_id',
        'browser_token_hash',
        'user_id',
        'email',
        'email_verified_at',
        'access_enabled',
        'access_email',
        'auth_version',
        'nickname',
        'work_type',
        'employee_status',
        'default_regular_hours',
        'hourly_rate',
        'reminder_enabled',
        'reminder_time',
        'reminder_start_date',
        'reminder_details',
        'reminder_schedule_mode',
        'reminder_schedule_saved_at',
        'reminder_days',
        'skip_if_complete',
        'last_reminded_on',
        'last_email_reminded_on',
        'last_seen_at',
    ];

    protected $hidden = [
        'browser_id',
        'browser_token_hash',
        'auth_version',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'default_regular_hours' => 'decimal:2',
        'reminder_enabled' => 'boolean',
        'reminder_days' => 'array',
        'skip_if_complete' => 'boolean',
        'email_verified_at' => 'datetime',
        'access_enabled' => 'boolean',
        'auth_version' => 'integer',
        'last_reminded_on' => 'date',
        'last_email_reminded_on' => 'date',
        'reminder_start_date' => 'date',
        'reminder_schedule_saved_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(WorkEntry::class, 'profile_id');
    }
}
