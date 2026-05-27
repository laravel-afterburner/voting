<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Documents\Actions\UnlinkDocument;
use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\DocumentsIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DetachDocumentFromBallot
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

        app(UnlinkDocument::class)->execute($document, $ballot, $user);
    }
}
