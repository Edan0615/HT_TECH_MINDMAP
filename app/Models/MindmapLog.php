<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MindmapLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'mindmap_id',
        'user_id',
        'action_summary',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the mindmap that this log belongs to.
     */
    public function mindmap(): BelongsTo
    {
        return $this->belongsTo(Mindmap::class);
    }

    /**
     * Get the user who made this change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
