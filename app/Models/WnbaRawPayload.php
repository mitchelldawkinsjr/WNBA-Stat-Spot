<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WnbaRawPayload extends Model
{
    protected $fillable = [
        'source_id',
        'entity_type',
        'endpoint',
        'season',
        'content_hash',
        'payload',
        'record_count',
        'fetched_at',
    ];

    protected $casts = [
        'season' => 'integer',
        'record_count' => 'integer',
        'fetched_at' => 'datetime',
    ];
}
