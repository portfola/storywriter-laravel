<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Keep the slug on the model, so no caller has to remember to set one
     * (Fizzy #108).
     *
     * Only one of the two write paths ever built a slug. A story created
     * through POST /stories saved with an empty one, and a rename left the old
     * title in it forever. Both are the same omission, so both are handled
     * here rather than in the two controllers.
     */
    protected static function booted(): void
    {
        static::creating(function (self $story) {
            if (blank($story->slug)) {
                $story->slug = self::makeSlug($story->name);
            }
        });

        static::updating(function (self $story) {
            // A caller that sets a slug by hand keeps it; otherwise a rename
            // rebuilds it, which is safe now the slug is a label and not an
            // address (Fizzy #107).
            if ($story->isDirty('name') && ! $story->isDirty('slug')) {
                $story->slug = self::makeSlug($story->name);
            }
        });
    }

    /**
     * A readable slug for a story title, with a short random suffix so two
     * stories of the same name do not collide on the unique column.
     *
     * Str::slug() returns an empty string for a title that is all emoji or all
     * punctuation, hence the fallback -- a slug of just the suffix says nothing.
     */
    public static function makeSlug(?string $name): string
    {
        $base = Str::slug((string) $name) ?: 'story';

        return $base.'-'.Str::random(4);
    }

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
