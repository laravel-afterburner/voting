<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Documents\Actions\LinkDocument;
use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\DocumentsIntegration;
use Afterburner\Voting\Support\VotingAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachDocumentToBallot
{
    public function execute(Ballot $ballot, Document $document, User $user): void
    {
        if (! DocumentsIntegration::isEnabled()) {
            throw new VotingException('Document attachments are not available.');
        }

        Gate::forUser($user)->authorize('attachDocuments', $ballot);

        if ($document->team_id !== $ballot->team_id) {
            throw new VotingException('The document must belong to the same team as this ballot.');
        }

        app(LinkDocument::class)->execute($document, $ballot, $user);

        VotingAuditLogger::ballotDocumentAttached($ballot, $document, $user);
    }
}
