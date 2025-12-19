<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;

   protected $fillable = [
        'user_id', 
        'name', 
        'body',     // Or 'content', check your database migration!
        'prompt',  
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
