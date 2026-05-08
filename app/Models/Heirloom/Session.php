<?php

namespace App\Models\Heirloom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Session extends Model
{
    use HasFactory;

    protected $table = 'heirloom_sessions';

    protected $fillable = [
        'user_id',
        'subject_id',
        'title',
        'audio_path',
        'status',
        'duration_seconds',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function narratives()
    {
        return $this->hasMany(Narrative::class, 'session_id');
    }

    public function transcript()
    {
        return $this->hasOne(Transcript::class, 'session_id');
    }
}