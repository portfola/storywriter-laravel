<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoryRequest;
use App\Http\Requests\UpdateStoryRequest;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return StoryResource::collection(auth()->user()->stories);
    }

    /**
     * Display the specified resource.
     */
    public function show(Story $story)
    {
        return StoryResource::make($story->load('pages'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoryRequest $request)
    {
        // 1. Get the validated data from the request
        $data = $request->validated();

        // 2. If the app sends 'content' but the DB uses 'body', map it here:
        if (isset($data['content'])) {
            $data['body'] = $data['content'];
        }

        // 3. Force the user_id to be the currently authenticated user
        $data['user_id'] = auth()->id();

        // 4. Create the story
        $story = Story::create($data);

        return $story->toResource();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoryRequest $request, Story $story)
    {
        $story->update($request->validated());

        return $story->toResource();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Story $story)
    {
        $story->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the authenticated user's saved stories.
     */
    public function saved()
    {
        return StoryResource::collection(auth()->user()->savedStories()->orderByDesc('user_saved_stories.created_at')->get());
    }

    /**
     * Save a story for the authenticated user.
     */
    public function save(Request $request, Story $story)
    {
        $validated = $request->validate([
            'elevenlabs_conversation_id' => 'nullable|string|max:255',
        ]);

        auth()->user()->savedStories()->syncWithoutDetaching([$story->id]);

        if (! empty($validated['elevenlabs_conversation_id']) && empty($story->elevenlabs_conversation_id)) {
            $story->update(['elevenlabs_conversation_id' => $validated['elevenlabs_conversation_id']]);
        }

        return StoryResource::make($story->load('pages'));
    }

    /**
     * Unsave a story for the authenticated user.
     */
    public function unsave(Story $story)
    {
        auth()->user()->savedStories()->detach($story->id);

        return response()->json(null, 204);
    }
}
