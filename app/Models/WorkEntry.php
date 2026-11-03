<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkEntry extends Model
{
    protected $fillable = [
        'profile_id',
        'browser_id',
        'work_date',
        'time_in',
        'time_out',
        'regular_hours',
        'hourly_rate_snapshot',
        'normal_hours',
        'extended_hours',
        'rate_type',
        'modifier',
        'status',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'regular_hours' => 'decimal:2',
        'hourly_rate_snapshot' => 'decimal:2',
        'normal_hours' => 'decimal:2',
        'extended_hours' => 'decimal:2',
    ];

    public function setWorkDateAttribute($value): void
    {
        $this->attributes['work_date'] = Carbon::parse($value)->toDateString();
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WorkProfile::class, 'profile_id');
    }
}
