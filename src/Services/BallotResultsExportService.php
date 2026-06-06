<?php

namespace Afterburner\Voting\Services;

use Afterburner\Voting\Models\Ballot;

class BallotResultsExportService
{
    public function __construct(
        protected BallotTallyService $tallyService,
        protected QuorumService $quorumService,
    ) {}

    /**
     * @return array{
     *     ballot: Ballot,
     *     tally: array,
     *     quorum: array,
     *     response_details: array,
     *     weighted: bool
     * }
     */
    public function build(Ballot $ballot): array
    {
        $ballot->loadMissing('options');

        return [
            'ballot' => $ballot,
            'tally' => $this->tallyService->tally($ballot),
            'quorum' => $this->quorumService->calculate($ballot),
            'response_details' => $this->tallyService->responseDetails($ballot),
            'weighted' => $this->tallyService->usesWeightedTally($ballot),
        ];
    }
}
