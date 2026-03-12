<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryPage extends Model
{
    /** @use HasFactory<\Database\Factories\StoryPageFactory> */
    use HasFactory;

    protected $fillable = [
        'story_id',
        'page_number',
        'content',
        'illustration_prompt',
        'image_url',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
