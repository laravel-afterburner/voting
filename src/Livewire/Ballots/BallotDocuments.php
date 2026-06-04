<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Actions\AttachDocumentToBallot;
use Afterburner\Voting\Actions\DetachDocumentFromBallot;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\DocumentsIntegration;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BallotDocuments extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $ballotId;

    public bool $embedded = false;

    public bool $showAttachModal = false;

    public bool $showPreviewModal = false;

    public ?int $previewDocumentId = null;

    public string $documentSearch = '';

    public function mount(int $teamId, int $ballotId, bool $embedded = false): void
    {
        abort_unless(DocumentsIntegration::isEnabled(), 404);

        $ballot = $this->ballot();

        if ($ballot->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $ballot), 403);

        $this->teamId = $teamId;
        $this->ballotId = $ballotId;
        $this->embedded = $embedded;
    }

    public function openAttachModal(): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $this->documentSearch = '';
        $this->showAttachModal = true;
    }

    public function closeAttachModal(): void
    {
        $this->showAttachModal = false;
        $this->documentSearch = '';
    }

    public function attachDocument(int $documentId): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $ballot = $this->ballot();
        $document = Document::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($documentId);

        try {
            app(AttachDocumentToBallot::class)->execute($ballot, $document, Auth::user());
            $this->banner(__('Document attached to ballot.'));
            $this->closeAttachModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function detachDocument(int $documentId): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $ballot = $this->ballot();
        $document = Document::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($documentId);

        try {
            app(DetachDocumentFromBallot::class)->execute($ballot, $document, Auth::user());
            $this->banner(__('Document removed from ballot.'));

            if ($this->previewDocumentId === $documentId) {
                $this->closePreview();
            }
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function openPreview(int $documentId): void
    {
        $document = $this->linkedDocument($documentId);

        abort_unless($document->isPreviewableInBrowser(), 404);
        abort_unless(Auth::user()->can('view', $document), 403);

        $this->previewDocumentId = $documentId;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->showPreviewModal = false;
        $this->previewDocumentId = null;
    }

    protected function linkedDocument(int $documentId): Document
    {
        return $this->ballot()
            ->linkedDocuments()
            ->where('documents.id', $documentId)
            ->firstOrFail();
    }

    protected function ballot(): Ballot
    {
        return Ballot::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->ballotId);
    }

    protected function canManageDocuments(): bool
    {
        return Auth::user()->can('attachDocuments', $this->ballot());
    }

    public function render()
    {
        $ballot = $this->ballot()->load(['linkedDocuments.uploader']);
        $team = Team::query()->findOrFail($this->teamId);

        $linkedDocumentIds = $ballot->linkedDocuments->pluck('id');

        $availableDocuments = Document::query()
            ->forTeam($this->teamId)
            ->where('upload_status', 'completed')
            ->when($linkedDocumentIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedDocumentIds))
            ->when(filled($this->documentSearch), function ($query) {
                $term = '%'.$this->documentSearch.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('filename', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();

        $previewDocument = null;
        $previewUrl = null;

        if ($this->showPreviewModal && $this->previewDocumentId) {
            $previewDocument = $ballot->linkedDocuments->firstWhere('id', $this->previewDocumentId);
            if ($previewDocument) {
                $previewUrl = route('teams.documents.preview', [
                    'team' => $team,
                    'document' => $previewDocument,
                ]);
            }
        }

        return view('afterburner-voting::ballots.livewire.ballot-documents', [
            'team' => $team,
            'ballot' => $ballot,
            'linkedDocuments' => $ballot->linkedDocuments,
            'availableDocuments' => $availableDocuments,
            'canManageDocuments' => $this->canManageDocuments(),
            'previewDocument' => $previewDocument,
            'previewUrl' => $previewUrl,
        ]);
    }
}
