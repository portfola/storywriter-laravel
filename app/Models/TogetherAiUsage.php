<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TogetherAiUsage extends Model
{
    use HasFactory;

    public const SERVICE_STORY = 'story';

    public const SERVICE_IMAGE = 'image';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'together_ai_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'service_type',
        'model_id',
        'estimated_cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
     * Log a Together AI generation (story text or image) for usage tracking.
     */
    public static function logUsage(int $userId, string $serviceType, ?string $modelId = null): self
    {
        $estimatedCost = $serviceType === self::SERVICE_IMAGE
            ? (float) config('services.together.cost_per_image')
            : (float) config('services.together.cost_per_story');

        return self::create([
            'user_id' => $userId,
            'service_type' => $serviceType,
            'model_id' => $modelId,
            'estimated_cost' => $estimatedCost,
        ]);
    }

    /**
     * Get the number of generations of a given type made by a user today.
     */
    public static function getTodayCount(int $userId, string $serviceType): int
    {
        return self::where('user_id', $userId)
            ->where('service_type', $serviceType)
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get the daily generation limit for a given service type.
     * Currently uses the free-tier limit for all users.
     */
    public static function getDailyLimit(string $serviceType): int
    {
        // TODO: Check user's subscription tier when implemented.
        return $serviceType === self::SERVICE_IMAGE
            ? (int) config('services.together.daily_image_limit_free')
            : (int) config('services.together.daily_story_limit_free');
    }

    /**
     * Check whether a user has reached their daily limit for a service type.
     */
    public static function wouldExceedLimit(int $userId, string $serviceType): bool
    {
        return self::getTodayCount($userId, $serviceType) >= self::getDailyLimit($serviceType);
    }
}
