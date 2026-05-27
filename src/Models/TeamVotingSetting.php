<?php

namespace Afterburner\Voting\Models;

use Afterburner\Voting\Enums\VoteVisibility;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamVotingSetting extends Model
{
    protected $fillable = [
        'team_id',
        'default_quorum_percent',
        'default_vote_visibility',
        'allow_proxy_votes',
        'lock_designation_during_open_ballots',
    ];

    protected function casts(): array
    {
        return [
            'default_quorum_percent' => 'float',
            'default_vote_visibility' => VoteVisibility::class,
            'allow_proxy_votes' => 'boolean',
            'lock_designation_during_open_ballots' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
