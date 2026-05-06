<?php

namespace App\Models\Heirloom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transcript extends Model
{
    use HasFactory;

    protected $table = 'heirloom_transcripts';

    protected $fillable = [
        'session_id',
        'user_id',
        'transcript_text',
        'status',
        'language',
        'duration_seconds',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}