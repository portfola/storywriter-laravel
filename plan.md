# ElevenLabs Conversation Integration Fix

## Problem Summary

**Issue**: Frontend fails to start ElevenLabs conversation with error:
```
"Missing sessionId or apiKey in credentials response"
```

**Root Cause**: The backend now returns a `signed_url` from ElevenLabs API, but the frontend still expects the old response format with `sessionId` or `apiKey`.

**Status**:
- Backend: Fixed and deployed (returns signed URL)
- Frontend: Needs update to use signed URL

---

## What Changed

### Old Backend Response (before fix)
```json
{
  "sessionId": "uuid",
  "apiKey": "sk-...",
  "agentId": "agent-id",
  "expiresAt": "2026-01-15T12:00:00Z"
}
```

### New Backend Response (current)
```json
{
  "signed_url": "wss://api.elevenlabs.io/v1/convai/conversation?agent_id=...&signature=..."
}
```

The new approach is more secure because:
- The API key is never exposed to the frontend
- ElevenLabs generates a time-limited signed WebSocket URL
- The backend calls ElevenLabs API server-side to get this URL

---

## Frontend Changes Required

### Task 1: Find the ElevenLabs Service Code

Search for files that:
- Call `/api/conversation/sdk-credentials`
- Reference `sessionId` or `apiKey` from the credentials response
- Initialize the ElevenLabs SDK/WebSocket connection

Likely file locations:
- `src/services/elevenlabs.ts` or similar
- `src/hooks/useConversation.ts` or similar
- `src/utils/api.ts` or similar

### Task 2: Update Credentials Handling

**Old code pattern** (what to look for):
```typescript
const response = await api.post('/conversation/sdk-credentials', { agentId });
const { sessionId, apiKey } = response.data;

// Using apiKey to initialize SDK
const conversation = new ElevenLabsConversation({
  apiKey: apiKey,
  agentId: agentId,
});
```

**New code pattern** (what to change to):
```typescript
const response = await api.post('/conversation/sdk-credentials', { agentId });
const { signed_url } = response.data;

// Using signed URL to connect directly
const conversation = new ElevenLabsConversation({
  signedUrl: signed_url,
});

// OR if using WebSocket directly:
const ws = new WebSocket(signed_url);
```

### Task 3: Update ElevenLabs SDK Initialization

The ElevenLabs Conversational AI SDK supports signed URLs. Check their documentation for the exact API, but typically:

```typescript
// Option A: Using @11labs/react or @11labs/client
import { Conversation } from '@11labs/client';

const conversation = await Conversation.startSession({
  signedUrl: signed_url,  // Use signed URL instead of agentId + apiKey
});

// Option B: Using the useConversation hook
const conversation = useConversation({
  signedUrl: signed_url,
});
```

### Task 4: Remove apiKey/sessionId References

Remove or update any code that:
- Destructures `apiKey` or `sessionId` from the response
- Passes `apiKey` to the ElevenLabs SDK
- Stores `apiKey` in state or context

---

## Backend Reference

### Endpoint
`POST /api/conversation/sdk-credentials`

### Request
```json
{
  "agentId": "your-agent-id"
}
```

### Response (Success - 200)
```json
{
  "signed_url": "wss://api.elevenlabs.io/v1/convai/conversation?agent_id=...&signature=..."
}
```

### Response (Error - 500)
```json
{
  "error": "Failed to get signed URL from ElevenLabs",
  "details": { ... }
}
```

### Backend File
`app/Http/Controllers/Api/V1/ElevenLabsController.php` - `sdkCredentials()` method

---

## Testing

### 1. Test Backend Directly
```bash
# Get auth token
TOKEN=$(curl -s -X POST https://staging-api.storywriter.net/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your@email.com","password":"yourpass"}' | jq -r '.token')

# Test sdk-credentials endpoint
curl -X POST https://staging-api.storywriter.net/api/conversation/sdk-credentials \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"agentId":"your-agent-id"}'

# Expected: {"signed_url":"wss://api.elevenlabs.io/..."}
```

### 2. Test Frontend After Changes
1. Open browser dev tools Network tab
2. Start a conversation
3. Verify `/api/conversation/sdk-credentials` returns `signed_url`
4. Verify WebSocket connection opens to `wss://api.elevenlabs.io/...`
5. Verify microphone permission is requested
6. Verify audio plays from agent

---

## ElevenLabs Documentation

- [Conversational AI SDK](https://elevenlabs.io/docs/conversational-ai/client-sdk)
- [Signed URL Authentication](https://elevenlabs.io/docs/conversational-ai/authentication)
