<?php

namespace App\Models\Heirloom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Narrative extends Model
{
    use HasFactory;

    protected $table = 'heirloom_narratives';

    protected $fillable = [
        'user_id',
        'subject_id',
        'session_id',
        'transcript_id',
        'narrative_text',
        'format',
        'status',
        'share_token',
    ];

    protected static function booted(): void
    {
        static::creating(function ($narrative) {
            $narrative->share_token = Str::random(32);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }
}