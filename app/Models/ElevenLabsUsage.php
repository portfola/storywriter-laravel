<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElevenLabsUsage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'elevenlabs_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'service_type',
        'character_count',
        'voice_id',
        'model_id',
        'estimated_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'character_count' => 'integer',
        'estimated_cost' => 'decimal:4',
    ];

    /**
     * Get the user that made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a text-to-speech request with usage tracking.
     *
     * @param  string  $text  The text that was converted to speech
     * @param  string  $voiceId  The ElevenLabs voice ID used
     * @param  string  $modelId  The TTS model used
     */
    public static function logTtsRequest(string $text, string $voiceId, string $modelId): self
    {
        $characterCount = strlen($text);

        // Cost per character based on model
        // Source: https://elevenlabs.io/pricing
        $costPerChar = match ($modelId) {
            'eleven_multilingual_v2' => 0.000030,
            'eleven_turbo_v2_5' => 0.000024,
            'eleven_flash_v2_5' => 0.000024,
            default => 0.000024, // Default to flash pricing
        };

        $estimatedCost = $characterCount * $costPerChar;

        return self::create([
            'user_id' => auth()->id(),
            'service_type' => 'tts',
            'character_count' => $characterCount,
            'voice_id' => $voiceId,
            'model_id' => $modelId,
            'estimated_cost' => $estimatedCost,
        ]);
    }

    /**
     * Log a conversational AI request with usage tracking.
     *
     * @param  string  $message  The message sent to the conversation agent
     * @param  string  $agentId  The ElevenLabs agent ID used
     */
    public static function logConversationRequest(string $message, string $agentId): self
    {
        $characterCount = strlen($message);

        // Conversational AI uses similar pricing to TTS
        // Using flash model pricing as baseline
        $costPerChar = 0.000024;
        $estimatedCost = $characterCount * $costPerChar;

        return self::create([
            'user_id' => auth()->id(),
            'service_type' => 'conversation',
            'character_count' => $characterCount,
            'voice_id' => $agentId, // Store agent ID in voice_id field
            'model_id' => 'conversation_agent',
            'estimated_cost' => $estimatedCost,
        ]);
    }
}
