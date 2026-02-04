# Story Endpoints — `/api/v1/stories`

Reference for the frontend developer integrating the story listing and detail views.

---

## Authentication

All `/api/v1/stories` routes require a Sanctum token. Obtain one via `POST /api/login`. Include it as a Bearer token on every request:

```
Authorization: Bearer <token>
```

An unauthenticated request to any endpoint in this group returns `401`.

---

## Endpoints

### List the current user's stories

```
GET /api/v1/stories
```

Returns only the stories that belong to the authenticated user as a JSON collection. No query parameters are currently supported (no pagination, no filtering).

**Example response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "The Brave Little Fox",
      "slug": "the-brave-little-fox-a1b2c3",
      "body": "Once upon a time...\n---PAGE BREAK---\nChapter two...",
      "prompt": "Tell me a story about a fox who wants to be brave.",
      "user_id": 4,
      "created_at": "2026-02-04T18:00:00.000000Z",
      "updated_at": "2026-02-04T18:00:00.000000Z"
    }
  ]
}
```

### Get a single story

```
GET /api/v1/stories/{slug}
```

Returns one story by its slug. The `slug` is the route key — do not use the numeric `id` in the URL.

**Example response:**

```json
{
  "data": {
    "id": 1,
    "name": "The Brave Little Fox",
    "slug": "the-brave-little-fox-a1b2c3",
    "body": "Once upon a time...\n---PAGE BREAK---\nChapter two...",
    "prompt": "Tell me a story about a fox who wants to be brave.",
    "user_id": 4,
    "created_at": "2026-02-04T18:00:00.000000Z",
    "updated_at": "2026-02-04T18:00:00.000000Z"
  }
}
```

### Delete a story

```
DELETE /api/v1/stories/{slug}
```

Deletes the story and returns `204 No Content` with an empty body.

---

## Response fields

Every story object contains exactly these eight fields:

| Field        | Type   | Description                                                |
|--------------|--------|------------------------------------------------------------|
| `id`         | int    | Primary key                                                |
| `name`       | string | Story title                                                |
| `slug`       | string | URL-safe identifier; use this in detail URLs               |
| `body`       | string | Full story text. Pages are separated by `---PAGE BREAK---` |
| `prompt`     | string | Original transcript sent to the AI for generation          |
| `user_id`    | int    | ID of the user who owns the story                          |
| `created_at` | string | ISO 8601 timestamp                                         |
| `updated_at` | string | ISO 8601 timestamp                                         |

---

## Integration example

```js
const headers = {
  'Authorization': `Bearer ${sanctumToken}`,
  'Accept': 'application/json',
};

// List the current user's stories
const listRes  = await fetch('/api/v1/stories', { headers });
const { data: stories } = await listRes.json();

// Single story (by slug)
const detailRes = await fetch(`/api/v1/stories/${story.slug}`, { headers });
const { data: story } = await detailRes.json();

// Delete a story
await fetch(`/api/v1/stories/${story.slug}`, { method: 'DELETE', headers });
```

---

## Out-of-scope finding

During the v1 Stories review, one unrelated issue was identified and intentionally left untouched:

**`POST /api/generate-story` routes to a method that does not exist.** `routes/api.php` maps this endpoint to `StoryController::generate`, but `StoryController` has no `generate` method. A request to this endpoint will 500. It most likely should point to `StoryGenerationController::generate`, which is the handler used by the working `POST /api/stories/generate` route directly above it in the same file. This was not changed here because it is unrelated to the v1 CRUD work and the correct target should be confirmed before the route is updated.
