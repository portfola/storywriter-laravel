<?php

namespace App\Models\Heirloom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'heirloom_subjects';

    protected $fillable = [
        'user_id',
        'name',
        'birth_year',
        'places_lived',
        'education_profession',
        'family_structure',
        'life_chapters',
        'interests',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}