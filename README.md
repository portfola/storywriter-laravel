# StoryWriter API

Laravel 12 API backend for StoryWriter - an AI-powered platform for creating interactive children's digital storybooks.

## Features

- **AI Story Generation**: Generate children's stories
- **Image Generation**: Automatic cover art creation
- **Text-to-Speech**: Voice narration via ElevenLabs integration
- **REST API**: Versioned endpoints for story CRUD and AI services

## Tech Stack

- Laravel 12
- SQLite (default)
- Together AI (LLM & Image Generation)
- ElevenLabs (TTS)
- Laravel Sanctum (API Authentication)

## Quick Start

```bash
composer setup
composer dev
```

Visit `http://localhost:8000`

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- Together AI API key
- ElevenLabs API key

## Environment

Copy `.env.example` to `.env` and add your API keys:

```env
TOGETHER_API_KEY=your_key_here
ELEVENLABS_API_KEY=your_key_here
```
