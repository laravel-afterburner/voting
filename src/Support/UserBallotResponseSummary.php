<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UserBallotResponseSummary
{
    public function __construct(
        protected VoterEligibilityResolver $resolver,
    ) {}

    /**
     * @param  Collection<int, BallotResponse>  $responses
     * @return Collection<int, array{
     *     option_label: string,
     *     unit_label: string|null,
     *     consolidated: bool,
     *     via_proxy: bool,
     *     cast_at: Carbon,
     *     response_ids: array<int, int>,
     *     voter_units: array<int, array{type: string, id: int}>
     * }>
     */
    public function summarize(Ballot $ballot, Collection $responses): Collection
    {
        if ($responses->isEmpty()) {
            return collect();
        }

        $ownedDirect = $responses
            ->filter(fn (BallotResponse $response) => $this->isDirectOwnedLotVote($response))
            ->values();

        $remaining = $responses
            ->reject(fn (BallotResponse $response) => $ownedDirect->contains('id', $response->id))
            ->values();

        $summaries = collect();

        if ($ownedDirect->isNotEmpty()) {
            $uniqueOptions = $ownedDirect->pluck('ballot_option_id')->unique();

            if ($ownedDirect->count() > 1 && $uniqueOptions->count() === 1) {
                $summaries->push($this->consolidatedEntry($ballot, $ownedDirect));
            } else {
                foreach ($ownedDirect as $response) {
                    $summaries->push($this->individualEntry($ballot, $response, includeUnitLabel: $ownedDirect->count() > 1));
                }
            }
        }

        foreach ($remaining as $response) {
            $summaries->push($this->individualEntry(
                $ballot,
                $response,
                includeUnitLabel: $response->voter_unit_type !== User::class,
            ));
        }

        return $summaries->values();
    }

    protected function isDirectOwnedLotVote(BallotResponse $response): bool
    {
        return $response->voter_unit_type !== User::class
            && $response->proxy_vote_id === null;
    }

    /**
     * @param  Collection<int, BallotResponse>  $responses
     * @return array{
     *     option_label: string,
     *     unit_label: string|null,
     *     consolidated: bool,
     *     via_proxy: bool,
     *     cast_at: Carbon,
     *     response_ids: array<int, int>,
     *     voter_units: array<int, array{type: string, id: int}>
     * }
     */
    protected function consolidatedEntry(Ballot $ballot, Collection $responses): array
    {
        /** @var BallotResponse $first */
        $first = $responses->first();

        return [
            'option_label' => $first->option->label,
            'unit_label' => null,
            'consolidated' => true,
            'via_proxy' => false,
            'cast_at' => $responses->max('cast_at'),
            'response_ids' => $responses->pluck('id')->all(),
            'voter_units' => $responses
                ->map(fn (BallotResponse $response) => [
                    'type' => $response->voter_unit_type,
                    'id' => $response->voter_unit_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     option_label: string,
     *     unit_label: string|null,
     *     consolidated: bool,
     *     via_proxy: bool,
     *     cast_at: Carbon,
     *     response_ids: array<int, int>,
     *     voter_units: array<int, array{type: string, id: int}>
     * }
     */
    protected function individualEntry(Ballot $ballot, BallotResponse $response, bool $includeUnitLabel): array
    {
        $unitLabel = null;

        if ($includeUnitLabel && $response->voter_unit_type !== User::class) {
            $unitLabel = $this->resolver->voterUnitLabel(
                $response->voter_unit_type,
                $response->voter_unit_id,
            );
        }

        return [
            'option_label' => $response->option->label,
            'unit_label' => $unitLabel,
            'consolidated' => false,
            'via_proxy' => $response->proxy_vote_id !== null,
            'cast_at' => $response->cast_at,
            'response_ids' => [$response->id],
            'voter_units' => [[
                'type' => $response->voter_unit_type,
                'id' => $response->voter_unit_id,
            ]],
        ];
    }
}
