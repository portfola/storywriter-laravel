<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'audio_url',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * A URL the illustration can actually be fetched from, signed and short-lived.
     *
     * image_url holds an object path on a private bucket, which nothing can load
     * on its own, so every place that shows a page image reads this instead of
     * the column. Null when the page has no illustration yet.
     */
    protected function signedImageUrl(): Attribute
    {
        return Attribute::get(
            fn () => app(MediaStorageService::class)->temporaryUrl($this->image_url)
        );
    }

    /**
     * The same idea as signedImageUrl, for the page's narration recording.
     *
     * audio_url is a stored path too, so it needs signing before it is any use to
     * a client. Rows that hold something absolute — an endpoint, or a URL written
     * before media moved to stored paths — come back untouched. Null when the page
     * has not been narrated.
     */
    protected function signedAudioUrl(): Attribute
    {
        return Attribute::get(
            fn () => app(MediaStorageService::class)->temporaryUrl($this->audio_url)
        );
    }
}
