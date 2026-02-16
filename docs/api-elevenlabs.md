# ElevenLabs API Endpoints

**Version:** 1.0
**Base URL:** `/api/conversation`
**Authentication:** Required (`auth:sanctum`)
**Last Updated:** February 15, 2026

---

## Table of Contents

- [Authentication](#authentication)
- [Endpoints](#endpoints)
  - [Text-to-Speech](#post-apiconversationtts)
  - [List Voices](#get-apiconversationvoices)
  - [SDK Credentials](#post-apiconversationsdk-credentials)
  - [Conversation Proxy](#post-apiconversationproxy)
- [Error Responses](#error-responses)
- [Rate Limits](#rate-limits)
- [Usage Tracking](#usage-tracking)

---

## Authentication

All endpoints require authentication using Laravel Sanctum tokens.

**Header:**
```
Authorization: Bearer {your_token}
```

**Getting a Token:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'
```

**Response:**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  }
}
```

---

## Endpoints

### POST `/api/conversation/tts`

Convert text to speech using ElevenLabs text-to-speech API.

**Request:**

```bash
curl -X POST http://localhost:8000/api/conversation/tts \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "Once upon a time in a magical forest, there lived a curious little fox named Finley.",
    "voiceId": "56AoDkrOh6qfVPDXZ7Pt",
    "options": {
      "model_id": "eleven_flash_v2_5",
      "voice_settings": {
        "stability": 0.5,
        "similarity_boost": 0.75,
        "style": 0.0,
        "use_speaker_boost": true
      }
    }
  }'
```

**Request Parameters:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `text` | string | Yes | - | Text to convert to speech (max 5000 characters) |
| `voiceId` | string | Yes | - | ElevenLabs voice ID (e.g., `56AoDkrOh6qfVPDXZ7Pt`) |
| `options.model_id` | string | No | `eleven_flash_v2_5` | TTS model: `eleven_flash_v2_5`, `eleven_turbo_v2_5`, `eleven_multilingual_v2` |
| `options.voice_settings.stability` | float | No | `0.5` | Voice consistency (0.0-1.0) |
| `options.voice_settings.similarity_boost` | float | No | `0.75` | Voice similarity to original (0.0-1.0) |
| `options.voice_settings.style` | float | No | `0.0` | Style exaggeration (0.0-1.0) |
| `options.voice_settings.use_speaker_boost` | boolean | No | `true` | Enable audio enhancement |

**Response (Success - 200 OK):**

Binary audio data with `Content-Type: audio/mpeg`

```bash
# Save to file
curl -X POST http://localhost:8000/api/conversation/tts \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{"text": "Hello world", "voiceId": "56AoDkrOh6qfVPDXZ7Pt"}' \
  --output narration.mp3
```

**Response (Daily Limit Exceeded - 429):**

```json
{
  "error": "Daily narration limit reached. Please try again tomorrow.",
  "limit_info": {
    "characters_used": 10245,
    "daily_limit": 10000,
    "requested_characters": 87
  }
}
```

**Response (Validation Error - 422):**

```json
{
  "message": "The text field is required.",
  "errors": {
    "text": [
      "The text field is required."
    ]
  }
}
```

**Example (JavaScript/React Native):**

```javascript
async function generateNarration(text, voiceId = '56AoDkrOh6qfVPDXZ7Pt') {
  const response = await fetch('http://localhost:8000/api/conversation/tts', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${userToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      text,
      voiceId,
      options: {
        model_id: 'eleven_flash_v2_5',
      },
    }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'TTS request failed');
  }

  // Get audio blob
  const audioBlob = await response.blob();
  const audioUrl = URL.createObjectURL(audioBlob);

  return audioUrl; // Can be used with <audio> element
}

// Usage
const audioUrl = await generateNarration('Once upon a time...');
const audio = new Audio(audioUrl);
audio.play();
```

---

### GET `/api/conversation/voices`

List all available ElevenLabs voices.

**Request:**

```bash
curl -X GET http://localhost:8000/api/conversation/voices \
  -H "Authorization: Bearer {your_token}" \
  -H "Accept: application/json"
```

**Response (Success - 200 OK):**

```json
{
  "voices": [
    {
      "voice_id": "56AoDkrOh6qfVPDXZ7Pt",
      "name": "Cassidy",
      "category": "premade",
      "labels": {
        "accent": "american",
        "age": "young",
        "gender": "female",
        "use case": "narration"
      },
      "description": "Friendly, energetic young female voice perfect for children's stories",
      "preview_url": "https://storage.googleapis.com/..."
    },
    {
      "voice_id": "21m00Tcm4TlvDq8ikWAM",
      "name": "Rachel",
      "category": "premade",
      "labels": {
        "accent": "american",
        "age": "adult",
        "gender": "female",
        "use case": "narration"
      },
      "description": "Professional, clear adult female voice",
      "preview_url": "https://storage.googleapis.com/..."
    }
  ]
}
```

**Recommended Voices for Children's Stories:**

| Voice ID | Name | Age | Gender | Best For |
|----------|------|-----|--------|----------|
| `56AoDkrOh6qfVPDXZ7Pt` | Cassidy | Young | Female | Default - energetic storytelling |
| `AZnzlk1XvdvUeBnXmlld` | Domi | Young | Female | Expressive character voices |
| `21m00Tcm4TlvDq8ikWAM` | Rachel | Adult | Female | Calm bedtime stories |

**Example (JavaScript):**

```javascript
async function getAvailableVoices() {
  const response = await fetch('http://localhost:8000/api/conversation/voices', {
    headers: {
      'Authorization': `Bearer ${userToken}`,
      'Accept': 'application/json',
    },
  });

  const data = await response.json();
  return data.voices;
}

// Filter for child-friendly voices
const voices = await getAvailableVoices();
const childVoices = voices.filter(v =>
  v.labels?.age === 'young' && v.labels?.['use case'] === 'narration'
);
```

---

### POST `/api/conversation/sdk-credentials`

Get signed WebSocket URL for ElevenLabs Conversational AI agents.

**Request:**

```bash
curl -X POST http://localhost:8000/api/conversation/sdk-credentials \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "agentId": "your-agent-id-here"
  }'
```

**Request Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `agentId` | string | Yes | ElevenLabs Conversational AI agent ID |

**Response (Success - 200 OK):**

```json
{
  "signed_url": "wss://api.elevenlabs.io/v1/convai/conversation?agent_id=xxx&signature=yyy&expires=zzz"
}
```

**Example (JavaScript - WebSocket Connection):**

```javascript
async function connectToAgent(agentId) {
  // Get signed URL from backend
  const response = await fetch('http://localhost:8000/api/conversation/sdk-credentials', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${userToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ agentId }),
  });

  const { signed_url } = await response.json();

  // Connect to ElevenLabs WebSocket
  const ws = new WebSocket(signed_url);

  ws.onopen = () => {
    console.log('Connected to conversational AI');
  };

  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Agent response:', data);
  };

  return ws;
}
```

---

### POST `/api/conversation/proxy`

Proxy conversation messages to ElevenLabs Conversational AI (alternative to WebSocket).

**Request:**

```bash
curl -X POST http://localhost:8000/api/conversation/proxy \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "550e8400-e29b-41d4-a716-446655440000",
    "action": "message",
    "agentId": "your-agent-id",
    "message": "Tell me a story about dragons"
  }'
```

**Request Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `sessionId` | string (UUID) | Yes | Unique session identifier |
| `action` | string | Yes | Action type: `start`, `message`, or `end` |
| `agentId` | string | Yes | ElevenLabs agent ID |
| `message` | string | Required for `message` action | User's message to the agent |

**Response (Success - 200 OK):**

```json
{
  "response": "Once upon a time, in a land far away, there lived a friendly dragon named Spark...",
  "audio_url": "https://api.elevenlabs.io/v1/audio/...",
  "session_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Example (JavaScript - Full Conversation Flow):**

```javascript
import { v4 as uuidv4 } from 'uuid';

class ConversationClient {
  constructor(token, agentId) {
    this.token = token;
    this.agentId = agentId;
    this.sessionId = uuidv4();
  }

  async start() {
    return this.sendAction('start');
  }

  async sendMessage(message) {
    return this.sendAction('message', message);
  }

  async end() {
    return this.sendAction('end');
  }

  async sendAction(action, message = null) {
    const body = {
      sessionId: this.sessionId,
      action,
      agentId: this.agentId,
    };

    if (message) {
      body.message = message;
    }

    const response = await fetch('http://localhost:8000/api/conversation/proxy', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Conversation request failed');
    }

    return response.json();
  }
}

// Usage
const conversation = new ConversationClient(userToken, 'agent-123');

await conversation.start();
const response = await conversation.sendMessage('Tell me about your favorite adventure');
console.log('Agent says:', response.response);

// Play audio
const audio = new Audio(response.audio_url);
await audio.play();

// End conversation
await conversation.end();
```

---

## Error Responses

All endpoints return consistent error responses.

### Common Error Codes

| Status | Error | Description |
|--------|-------|-------------|
| `401` | Unauthorized | Missing or invalid authentication token |
| `422` | Validation Error | Invalid request parameters |
| `429` | Rate Limit Exceeded | Daily usage limit reached or too many requests |
| `500` | Server Error | Internal server or ElevenLabs API error |

### Error Response Format

```json
{
  "error": "Human-readable error message",
  "details": {
    "code": "ERROR_CODE",
    "message": "Technical details"
  }
}
```

### Validation Error Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "text": [
      "The text field is required."
    ],
    "voiceId": [
      "The voice id must be a string."
    ]
  }
}
```

---

## Rate Limits

### Laravel Throttle

- **Default:** 60 requests per minute per user
- **Header:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`

### Daily Usage Limits (Character-based)

- **Free Users:** 10,000 characters/day (~10 story pages)
- **Paid Users:** 50,000 characters/day (~50 story pages)

**Checking Usage:**

```bash
# Get current usage info from error response when limit is hit
curl -X POST http://localhost:8000/api/conversation/tts \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{"text": "Test", "voiceId": "56AoDkrOh6qfVPDXZ7Pt"}'

# Response when limit exceeded:
{
  "error": "Daily narration limit reached. Please try again tomorrow.",
  "limit_info": {
    "characters_used": 10245,
    "daily_limit": 10000,
    "requested_characters": 4
  }
}
```

---

## Usage Tracking

All TTS requests are tracked in the database for cost management and analytics.

**Tracked Data:**
- User ID
- Service type (`tts` or `conversation`)
- Character count
- Voice ID and model ID
- Estimated cost (USD)
- Timestamp

**Cost Calculation:**
- Flash/Turbo models: `$0.000024` per character
- Multilingual model: `$0.000030` per character

**Example Costs:**

| Text Length | Model | Cost |
|-------------|-------|------|
| 100 chars | Flash | $0.0024 |
| 500 chars | Flash | $0.012 |
| 1000 chars | Flash | $0.024 |
| 1000 chars | Multilingual | $0.030 |

---

## Complete Integration Example

**React Native Component:**

```javascript
import React, { useState } from 'react';
import { View, Button, Text } from 'react-native';
import { Audio } from 'expo-av';

export default function StoryNarration({ storyText, token }) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [sound, setSound] = useState(null);

  const playNarration = async () => {
    setLoading(true);
    setError(null);

    try {
      // Request TTS from backend
      const response = await fetch('http://localhost:8000/api/conversation/tts', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          text: storyText,
          voiceId: '56AoDkrOh6qfVPDXZ7Pt', // Cassidy voice
          options: {
            model_id: 'eleven_flash_v2_5',
          },
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Failed to generate narration');
      }

      // Convert response to blob
      const audioBlob = await response.blob();
      const audioUri = URL.createObjectURL(audioBlob);

      // Play audio with Expo Audio
      const { sound: playbackObject } = await Audio.Sound.createAsync(
        { uri: audioUri },
        { shouldPlay: true }
      );

      setSound(playbackObject);

    } catch (err) {
      setError(err.message);
      console.error('Narration error:', err);
    } finally {
      setLoading(false);
    }
  };

  const stopNarration = async () => {
    if (sound) {
      await sound.stopAsync();
      await sound.unloadAsync();
      setSound(null);
    }
  };

  return (
    <View>
      <Button
        title={loading ? 'Loading...' : 'Play Narration'}
        onPress={playNarration}
        disabled={loading}
      />
      {sound && (
        <Button title="Stop" onPress={stopNarration} />
      )}
      {error && (
        <Text style={{ color: 'red' }}>{error}</Text>
      )}
    </View>
  );
}
```

---

## Additional Resources

- **Integration Guide:** [docs/elevenlabs.md](./elevenlabs.md)
- **Implementation Checklist:** [docs/vocal-narration.md](./vocal-narration.md)
- **Project Documentation:** [docs/CLAUDE.md](./CLAUDE.md)
- **ElevenLabs API Docs:** https://elevenlabs.io/docs/api-reference

---

**Last Updated:** February 15, 2026
**Maintained by:** StoryWriter Development Team
