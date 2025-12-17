<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;

   protected $fillable = [
        'user_id', 
        'title', 
        'body',     // Or 'content', check your database migration!
        'prompt',  
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
