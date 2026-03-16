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
            'pages' => $this->whenLoaded('pages', fn () => $this->pages->sortBy('page_number')->values()->map(fn ($p) => [
                'pageNumber' => $p->page_number,
                'content' => $p->content,
                'illustrationPrompt' => $p->illustration_prompt,
                'imageUrl' => $p->image_url,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
