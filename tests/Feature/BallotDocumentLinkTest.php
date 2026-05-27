<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Actions\AttachDocumentToBallot;
use Afterburner\Voting\Actions\DetachDocumentFromBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\DocumentsIntegration;
use Afterburner\Voting\Tests\TestCase;

class BallotDocumentLinkTest extends TestCase
{
    public function test_attach_and_detach_document_on_draft_ballot(): void
    {
        if (! DocumentsIntegration::isAvailable()) {
            $this->markTestSkipped('Documents package is not available.');
        }

        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'create_resolutions', 'view_documents']);

        $ballot = Ballot::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => 'Budget vote',
            'type' => 'resolution',
            'status' => BallotStatus::Draft,
            'electorate' => 'all_members',
            'vote_visibility' => 'visible_after_close',
        ]);

        $document = Document::query()->create([
            'team_id' => $team->id,
            'name' => 'Budget PDF',
            'filename' => 'budget.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'storage_path' => 'teams/'.$team->id.'/budget.pdf',
            'upload_status' => 'completed',
            'uploaded_by' => $user->id,
        ]);

        app(AttachDocumentToBallot::class)->execute($ballot, $document, $user);

        $this->assertTrue($ballot->fresh()->linkedDocuments->contains('id', $document->id));

        app(DetachDocumentFromBallot::class)->execute($ballot, $document, $user);

        $this->assertFalse($ballot->fresh()->linkedDocuments->contains('id', $document->id));
    }
}
