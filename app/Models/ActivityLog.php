<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;  // only created_at, managed by the DB default

    protected $fillable = [
        'user_id',
        'user_name',
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'properties',
        'ip_address',
        'user_agent',
        // created_at intentionally excluded: the DB default (useCurrent) owns it
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Event badge colours (used in the Vue page) ────────────────────────────

    public static array $eventColors = [
        'created'        => 'green',
        'updated'        => 'blue',
        'deleted'        => 'red',
        'login'          => 'indigo',
        'logout'         => 'gray',
        'registered'     => 'teal',
        'password_reset' => 'yellow',
    ];
}
