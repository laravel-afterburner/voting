<?php

namespace Afterburner\Voting\Concerns;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\TeamVotingSetting;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasVoting
{
    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class, 'team_id');
    }

    public function votingSettings(): HasOne
    {
        return $this->hasOne(TeamVotingSetting::class, 'team_id');
    }

    public function openBallots(): HasMany
    {
        return $this->ballots()->where('status', BallotStatus::Open);
    }
}
