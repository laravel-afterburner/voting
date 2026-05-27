<?php

namespace Afterburner\Voting\Http\Controllers;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Services\BallotResultsExportService;
use Afterburner\Voting\Services\BallotResultsPdfExporter;
use Afterburner\Voting\Services\BallotTallyService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBallotResultsController
{
    public function __invoke(
        Request $request,
        Team $team,
        Ballot $ballot,
        BallotResultsExportService $exportService,
        BallotTallyService $tallyService,
        BallotResultsPdfExporter $pdfExporter,
    ): StreamedResponse|Response {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        if ($ballot->team_id !== $team->id) {
            abort(404);
        }

        abort_unless($user->hasPermission('export_ballot_results', $team->id), 403);
        abort_unless($user->can('viewResults', $ballot), 403);
        abort_unless($tallyService->canViewTally($ballot), 403);

        if ($request->query('format') === 'pdf') {
            return $pdfExporter->download($ballot);
        }

        $data = $exportService->build($ballot);
        $filename = 'ballot-'.$ballot->id.'-results.csv';

        return response()->streamDownload(function () use ($data, $tallyService) {
            $handle = fopen('php://output', 'w');
            $ballot = $data['ballot'];
            $tally = $data['tally'];
            $quorum = $data['quorum'];
            $weighted = $data['weighted'];

            fputcsv($handle, ['Ballot', $ballot->title]);
            fputcsv($handle, ['Status', $ballot->status->value]);
            fputcsv($handle, [
                $weighted ? 'Total weighted votes' : 'Total votes',
                $tally['total_votes'],
            ]);

            if ($quorum['configured']) {
                fputcsv($handle, [
                    'Quorum',
                    sprintf(
                        '%d of %d (%.1f%%) — %s',
                        $quorum['cast'],
                        $quorum['eligible'],
                        $quorum['percent'],
                        $quorum['met'] ? 'met' : 'not met'
                    ),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Option',
                $weighted ? 'Weighted votes' : 'Votes',
                'Percentage',
            ]);

            foreach ($tally['options'] as $option) {
                fputcsv($handle, [$option['label'], $option['count'], $option['percentage'].'%']);
            }

            if ($tallyService->canViewResponseDetails($ballot) && count($data['response_details']) > 0) {
                fputcsv($handle, []);
                $headers = ['Voter unit', 'Option', 'Cast by', 'Cast at', 'Via proxy'];
                if ($weighted) {
                    array_splice($headers, 2, 0, ['Weight']);
                }
                fputcsv($handle, $headers);

                foreach ($data['response_details'] as $row) {
                    $line = [
                        $row['voter_unit_label'],
                        $row['option_label'],
                    ];
                    if ($weighted) {
                        $line[] = $row['weight'] ?? 1;
                    }
                    $line[] = $row['cast_by_name'];
                    $line[] = $row['cast_at'];
                    $line[] = ($row['via_proxy'] ?? false) ? 'yes' : 'no';
                    fputcsv($handle, $line);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
