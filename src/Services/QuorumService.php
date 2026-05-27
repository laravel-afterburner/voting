<?php

namespace Afterburner\Voting\Services;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\QuorumBasis;
use Afterburner\Voting\Models\Ballot;

class QuorumService
{
    public function __construct(
        protected VoterEligibilityResolver $resolver,
    ) {}

    /**
     * @return array{
     *     configured: bool,
     *     met: bool|null,
     *     percent: float|null,
     *     required: float|null,
     *     cast: int,
     *     eligible: int
     * }
     */
    public function calculate(Ballot $ballot): array
    {
        $eligible = $this->resolver->totalEligibleVoterUnits($ballot);
        $cast = $this->numerator($ballot);

        if ($ballot->quorum_percent === null) {
            return [
                'configured' => false,
                'met' => null,
                'percent' => null,
                'required' => null,
                'cast' => $cast,
                'eligible' => $eligible,
            ];
        }

        $required = (float) $ballot->quorum_percent;
        $percent = $eligible > 0 ? round(($cast / $eligible) * 100, 1) : 0.0;

        return [
            'configured' => true,
            'met' => $percent >= $required,
            'percent' => $percent,
            'required' => $required,
            'cast' => $cast,
            'eligible' => $eligible,
        ];
    }

    protected function numerator(Ballot $ballot): int
    {
        $basis = $ballot->quorum_basis
            ? QuorumBasis::tryFrom($ballot->quorum_basis)
            : null;

        if ($basis === QuorumBasis::EligibleUsers) {
            return (int) $ballot->responses()
                ->distinct('cast_by_user_id')
                ->count('cast_by_user_id');
        }

        return $ballot->responses()
            ->get(['voter_unit_type', 'voter_unit_id'])
            ->unique(fn ($response) => $response->voter_unit_type.'|'.$response->voter_unit_id)
            ->count();
    }
}
