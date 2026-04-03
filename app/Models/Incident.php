<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $table = 'incidents';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'reports' => 'array',
        'occurred_at' => 'datetime',
    ];
}
