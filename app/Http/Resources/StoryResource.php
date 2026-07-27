<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'body' => $this->body,
            'prompt' => $this->prompt,
            'user_id' => $this->user_id,
            'elevenlabs_conversation_id' => $this->elevenlabs_conversation_id,
            // Illustrations live on a private bucket, so what the row holds is an
            // object path. It gets signed here, on the way out, rather than saved
            // as a URL that would expire while still sitting in the database.
            'pages' => $this->whenLoaded('pages', fn () => $this->pages->sortBy('page_number')->values()->map(fn ($p) => [
                'pageNumber' => $p->page_number,
                'content' => $p->content,
                'illustrationPrompt' => $p->illustration_prompt,
                'imageUrl' => $p->signed_image_url,
                'audioUrl' => $p->signed_audio_url,
            ])),
            // The cover is page 1's illustration. The bookshelf grid used to dig
            // it out of the markdown in body, which no longer holds a URL worth
            // having, so it is handed over properly here.
            'coverImageUrl' => $this->whenLoaded('coverPage', fn () => $this->coverPage->signed_image_url),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
