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
    public function __construct()
    {
        // Runs StoryPolicy over index/show/store/update/destroy. save(), unsave()
        // and saved() are not resource methods, so they authorize by hand below.
        $this->authorizeResource(Story::class, 'story');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // coverPage is eager-loaded so the bookshelf gets a cover image per story
        // without a query per card.
        return StoryResource::collection(auth()->user()->stories()->with('coverPage')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show(Story $story)
    {
        return StoryResource::make($story->load('pages', 'coverPage'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoryRequest $request)
    {
        $data = $request->validated();

        // The request speaks the app's names, the model speaks the column
        // names. Passing the request's own keys through instead dropped the
        // title on the floor: 'title' is not fillable, so mass assignment
        // discarded it and the story saved with no name (Fizzy #108). The slug
        // comes from the model's creating hook.
        $story = Story::create([
            'user_id' => auth()->id(),
            'name' => $data['title'],
            'body' => $data['content'],
        ]);

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
     *
     * Scoped to stories the caller owns, not just to the rows on their shelf
     * (Fizzy #104). A bookshelf row is only ever meant to point at one of your
     * own stories -- saving is owner-only -- so a row pointing anywhere else is
     * leftover damage from the cross-user hole in #94. Listing it would read
     * that story out in full, name, body, pages and signed media URLs included,
     * to a user GET /stories/{story} turns away with a 403.
     *
     * Filtering on the way out rather than deleting the stray rows: a user can
     * unsave a bad row themselves, and whether any exist in staging or
     * production is still an open question. This holds either way.
     */
    public function saved()
    {
        $this->authorize('viewAny', Story::class);

        return StoryResource::collection(
            auth()->user()->savedStories()
                ->where('stories.user_id', auth()->id())
                ->orderByDesc('user_saved_stories.created_at')
                ->get()
        );
    }

    /**
     * Save a story for the authenticated user.
     */
    public function save(Request $request, Story $story)
    {
        $this->authorize('save', $story);

        $validated = $request->validate([
            'elevenlabs_conversation_id' => 'nullable|string|max:255',
        ]);

        auth()->user()->savedStories()->syncWithoutDetaching([$story->id]);

        if (! empty($validated['elevenlabs_conversation_id']) && empty($story->elevenlabs_conversation_id)) {
            $story->update(['elevenlabs_conversation_id' => $validated['elevenlabs_conversation_id']]);
        }

        return StoryResource::make($story->load('pages', 'coverPage'));
    }

    /**
     * Unsave a story for the authenticated user.
     */
    public function unsave(Story $story)
    {
        $this->authorize('unsave', $story);

        auth()->user()->savedStories()->detach($story->id);

        return response()->json(null, 204);
    }
}
