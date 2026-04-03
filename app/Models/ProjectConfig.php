<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectConfig extends Model
{
    protected $table = 'config';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'value' => 'array',
    ];
}
