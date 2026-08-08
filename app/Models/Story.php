<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'body',     // Or 'content', check your database migration!
        'prompt',
        'characters_description',
        'elevenlabs_conversation_id',
    ];

    // No getRouteKeyName() here on purpose (Fizzy #107). A story is addressed by
    // its id everywhere -- API and dashboard alike. The slug was never rebuilt
    // when a title changed, so it either described the old name forever or broke
    // every link that had already been handed out. It stays on the row as a
    // human-readable label; it is not an address.

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(StoryPage::class)->orderBy('page_number');
    }

    /**
     * Page 1, whose illustration doubles as the story's cover.
     *
     * Its own relation so the bookshelf list can eager-load one page per story
     * for the cover image, instead of dragging every page of every story back.
     */
    public function coverPage(): HasOne
    {
        return $this->hasOne(StoryPage::class)->where('page_number', 1);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_saved_stories')->withTimestamps();
    }
}
