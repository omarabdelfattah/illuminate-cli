<?php

namespace App\Models;

use App\Relations\DonutRelation;
use Illuminate\Database\Eloquent\Model;

class Neighborhood extends Model
{
    protected $table = 'neighborhoods';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'properties' => 'array',
    ];

    public function incidents(float $inner = 0.5, float $outer = 2.0): DonutRelation
    {
        return new DonutRelation($this, $inner, $outer);
    }
}