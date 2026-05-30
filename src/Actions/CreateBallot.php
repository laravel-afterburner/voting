<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Support\Electorate;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotOption;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBallot
{
    /**
     * @param  array<int, array{label: string, description?: string|null}>  $options
     */
    public function execute(
        Team $team,
        User $user,
        string $title,
        ?string $description,
        BallotType $type,
        ElectorateType|Electorate|string $electorate,
        array $options = [],
        ?VoteVisibility $voteVisibility = null,
        ?float $quorumPercent = null,
        ?\DateTimeInterface $opensAt = null,
        ?\DateTimeInterface $closesAt = null,
        bool $allowAbstain = false,
    ): Ballot {
        Gate::forUser($user)->authorize('create', [Ballot::class, $team]);

        if ($team->id !== $user->currentTeam?->id && ! $user->belongsToTeam($team)) {
            throw new VotingException('You do not belong to this team.');
        }

        $electorate = Electorate::from($electorate);

        return DB::transaction(function () use (
            $team,
            $user,
            $title,
            $description,
            $type,
            $electorate,
            $options,
            $voteVisibility,
            $quorumPercent,
            $opensAt,
            $closesAt,
            $allowAbstain,
        ) {
            $ballot = Ballot::query()->create([
                'team_id' => $team->id,
                'created_by_user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'status' => BallotStatus::Draft,
                'electorate' => $electorate,
                'vote_visibility' => $voteVisibility ?? TeamVotingSettings::defaultVoteVisibilityForTeam($team),
                'allow_abstain' => $allowAbstain,
                'quorum_percent' => $quorumPercent ?? TeamVotingSettings::defaultQuorumPercentForTeam($team),
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ]);

            foreach (array_values($options) as $index => $option) {
                BallotOption::query()->create([
                    'ballot_id' => $ballot->id,
                    'label' => $option['label'],
                    'description' => $option['description'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $ballot->fresh(['options']);
        });
    }
}
