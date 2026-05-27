<?php

namespace Afterburner\Voting\Services;

use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class BallotResultsPdfExporter
{
    public function __construct(
        protected BallotResultsExportService $exportService,
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Pdf::class);
    }

    public function download(Ballot $ballot): Response
    {
        if (! $this->isAvailable()) {
            throw new VotingException(
                'PDF export requires barryvdh/laravel-dompdf. Install it in the host application.'
            );
        }

        $data = $this->exportService->build($ballot);
        $filename = 'ballot-'.$ballot->id.'-results.pdf';

        return Pdf::loadView('afterburner-voting::exports.ballot-results-pdf', $data)
            ->download($filename);
    }
}
