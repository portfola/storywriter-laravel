<?php

namespace App\Services\Heirloom;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\UploadedFile;

class TranscriptionService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';
    protected HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->apiKey = config('services.groq.key') ?? '';
        $this->http = $http;
    }

    public function transcribe(UploadedFile $audio): string
    {
        $response = $this->http->withToken($this->apiKey)
            ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName())
            ->post("{$this->baseUrl}/audio/transcriptions", [
                'model' => 'whisper-large-v3',
                'response_format' => 'text',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Transcription failed: ' . $response->body());
        }

        return $response->body();
    }
}