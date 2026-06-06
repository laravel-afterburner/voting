<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotOption;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CastVotes
{
    public function __construct(
        protected CastVote $castVote,
    ) {}

    /**
     * @param  Collection<int, VoterUnit>|array<int, VoterUnit>  $units
     * @return Collection<int, BallotResponse>
     */
    public function execute(
        Ballot $ballot,
        User $user,
        BallotOption $option,
        Collection|array $units,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Collection {
        $units = $units instanceof Collection ? $units : collect($units);

        return DB::transaction(function () use ($ballot, $user, $option, $units, $ipAddress, $userAgent) {
            return $units->map(fn (VoterUnit $unit) => $this->castVote->execute(
                $ballot,
                $user,
                $option,
                $unit->type,
                $unit->id,
                $ipAddress,
                $userAgent,
            ));
        });
    }
}
