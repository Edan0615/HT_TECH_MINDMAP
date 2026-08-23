<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mindmap extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'folder',
        'data',
        'ai_history',
    ];

    protected $casts = [
        'data' => 'array',
        'ai_history' => 'array',
    ];

    /**
     * Get the user that owns the mindmap.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the revision logs for this mindmap.
     */
    public function logs()
    {
        return $this->hasMany(MindmapLog::class);
    }
}
