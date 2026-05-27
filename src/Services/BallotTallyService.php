<?php

namespace Afterburner\Voting\Services;

use Afterburner\Voting\Contracts\ProvidesWeightedVotes;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Models\Ballot;

class BallotTallyService
{
    public function __construct(
        protected VoterEligibilityResolver $resolver,
    ) {}

    public function usesWeightedTally(): bool
    {
        return $this->resolver instanceof ProvidesWeightedVotes;
    }

    /**
     * @return array{
     *     total_votes: float,
     *     weighted: bool,
     *     options: array<int, array{option_id: int, label: string, count: float, percentage: float}>
     * }
     */
    public function tally(Ballot $ballot): array
    {
        $ballot->loadMissing('options');
        $weighted = $this->usesWeightedTally();

        $responses = $ballot->responses()
            ->get(['ballot_option_id', 'voter_unit_type', 'voter_unit_id']);

        $counts = [];
        $totalVotes = 0.0;

        foreach ($responses as $response) {
            $weight = $weighted
                ? $this->resolver->voterUnitWeight($ballot, $response->voter_unit_type, $response->voter_unit_id)
                : 1.0;

            $counts[$response->ballot_option_id] = ($counts[$response->ballot_option_id] ?? 0) + $weight;
            $totalVotes += $weight;
        }

        $options = $ballot->options->map(function ($option) use ($counts, $totalVotes) {
            $count = (float) ($counts[$option->id] ?? 0);

            return [
                'option_id' => $option->id,
                'label' => $option->label,
                'count' => $count,
                'percentage' => $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0.0,
            ];
        })->values()->all();

        return [
            'total_votes' => $totalVotes,
            'weighted' => $weighted,
            'options' => $options,
        ];
    }

    public function canViewTally(Ballot $ballot): bool
    {
        if ($ballot->status === BallotStatus::Closed) {
            return true;
        }

        return $ballot->vote_visibility === VoteVisibility::VisibleRealtime;
    }

    public function canViewResponseDetails(Ballot $ballot): bool
    {
        if ($ballot->vote_visibility === VoteVisibility::Secret) {
            return false;
        }

        if ($ballot->vote_visibility === VoteVisibility::VisibleAfterClose) {
            return $ballot->status === BallotStatus::Closed;
        }

        return $ballot->status === BallotStatus::Closed
            || $ballot->vote_visibility === VoteVisibility::VisibleRealtime;
    }

    /**
     * @return array<int, array{
     *     voter_unit_label: string,
     *     option_label: string,
     *     cast_by_name: string,
     *     cast_at: string,
     *     via_proxy: bool,
     *     weight?: float
     * }>
     */
    public function responseDetails(Ballot $ballot): array
    {
        if (! $this->canViewResponseDetails($ballot)) {
            return [];
        }

        $weighted = $this->usesWeightedTally();

        return $ballot->responses()
            ->with(['option', 'castBy'])
            ->orderBy('cast_at')
            ->get()
            ->map(function ($response) use ($ballot, $weighted) {
                $row = [
                    'voter_unit_label' => $this->resolver->voterUnitLabel(
                        $response->voter_unit_type,
                        $response->voter_unit_id,
                    ),
                    'option_label' => $response->option->label,
                    'cast_by_name' => $response->castBy->name,
                    'cast_at' => $response->cast_at->toDateTimeString(),
                    'via_proxy' => $response->proxy_vote_id !== null,
                ];

                if ($weighted) {
                    $row['weight'] = $this->resolver->voterUnitWeight(
                        $ballot,
                        $response->voter_unit_type,
                        $response->voter_unit_id,
                    );
                }

                return $row;
            })
            ->all();
    }
}
