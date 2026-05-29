<?php

namespace App\Models;

use Afterburner\Voting\Concerns\HasVoting;
use App\Traits\SimulatesSubscriptionEntitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasVoting;
    use SimulatesSubscriptionEntitlements;

    protected $fillable = ['name', 'user_id', 'timezone', 'trial_ends_at'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
