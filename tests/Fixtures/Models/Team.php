<?php

namespace App\Models;

use Afterburner\Voting\Concerns\HasVoting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasVoting;

    protected $fillable = ['name', 'user_id', 'timezone'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
