<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Events\BallotPublished;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotOption;
use Afterburner\Voting\Support\ScheduleBallotTransitions;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishBallot
{
    public function execute(Ballot $ballot, User $user): Ballot
    {
        Gate::forUser($user)->authorize('publish', $ballot);

        if (! $ballot->isEditable()) {
            throw new VotingException('Only draft or scheduled ballots can be published.');
        }

        if (! $ballot->opens_at || ! $ballot->closes_at) {
            throw new VotingException('Open and close dates are required when publishing a ballot.');
        }

        if ($ballot->closes_at->lte($ballot->opens_at)) {
            throw new VotingException('Close date must be after the open date.');
        }

        return DB::transaction(function () use ($ballot) {
            $ballot->load(['options', 'team']);

            if ($ballot->electorate === ElectorateType::Custom) {
                $customResolver = config('afterburner-voting.custom_electorate_resolver');
                if (! is_string($customResolver) || $customResolver === '' || ! class_exists($customResolver)) {
                    throw new VotingException('Custom electorate ballots require AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER to be configured.');
                }
            }

            if ($ballot->quorum_percent === null) {
                $quorum = TeamVotingSettings::defaultQuorumPercentForTeam($ballot->team);
                if ($quorum !== null) {
                    $ballot->update(['quorum_percent' => $quorum]);
                    $ballot->refresh();
                }
            }

            if ($ballot->options->isEmpty() && $ballot->type === BallotType::Resolution) {
                BallotOption::query()->create([
                    'ballot_id' => $ballot->id,
                    'label' => 'Yes',
                    'sort_order' => 0,
                ]);
                BallotOption::query()->create([
                    'ballot_id' => $ballot->id,
                    'label' => 'No',
                    'sort_order' => 1,
                ]);
                $ballot->load('options');
            }

            $optionCount = $ballot->options->count();
            if ($optionCount < 1) {
                throw new VotingException('Ballot must have at least one option.');
            }

            if ($optionCount < 2 && ! $ballot->allow_abstain) {
                throw new VotingException('Ballot must have at least two options.');
            }

            $opensInFuture = $ballot->opens_at && $ballot->opens_at->isFuture();
            $status = $opensInFuture ? BallotStatus::Scheduled : BallotStatus::Open;

            $ballot->update([
                'status' => $status,
                'published_at' => now(),
            ]);

            $ballot = $ballot->fresh(['options']);

            BallotPublished::dispatch($ballot);

            ScheduleBallotTransitions::dispatchFor($ballot);

            return $ballot;
        });
    }
}
