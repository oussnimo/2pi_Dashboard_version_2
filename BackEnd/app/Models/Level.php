<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $primaryKey = 'level_id'; // ✅ AJOUTE CETTE LIGNE
    protected $fillable = [
        'game_id',
        'level_number',
        'level_type',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id', 'game_id');
    }

    // ✅ Renamed to match Eloquent conventions (used in eager loading)
    public function boxQuestionAnswer()
    {
        return $this->hasMany(BoxQuestionAnswer::class, 'level_id');
    }

    // ✅ Gardé hasMany — un level balloon peut avoir plusieurs questions
    public function balloonType()
    {
        return $this->hasMany(BalloonType::class, 'level_id');
    }
}
