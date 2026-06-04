<?php

namespace Afterburner\Voting\Models;

use Afterburner\Voting\Casts\ElectorateCast;
use Afterburner\Voting\Concerns\HasLinkedDocuments;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Exceptions\VotingException;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ballot extends Model
{
    use HasLinkedDocuments;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (Ballot $ballot) {
            if (! $ballot->isDirty('vote_visibility')) {
                return;
            }

            if ($ballot->hasBeenPublished()) {
                throw new VotingException(
                    'Vote visibility cannot be changed after this ballot has been published or has received confidential votes.',
                );
            }

            $original = $ballot->getOriginal('vote_visibility');
            $originalVisibility = $original instanceof VoteVisibility
                ? $original
                : VoteVisibility::from($original);

            if ($originalVisibility->isConfidential() && $ballot->responses()->exists()) {
                throw new VotingException(
                    'Vote visibility cannot be changed after this ballot has been published or has received confidential votes.',
                );
            }
        });
    }

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'title',
        'description',
        'type',
        'status',
        'electorate',
        'vote_visibility',
        'allow_abstain',
        'allow_multiple_selections',
        'quorum_percent',
        'quorum_basis',
        'opens_at',
        'closes_at',
        'published_at',
        'closed_at',
        'settings',
    ];

    protected $casts = [
        'type' => BallotType::class,
        'status' => BallotStatus::class,
        'electorate' => ElectorateCast::class,
        'vote_visibility' => VoteVisibility::class,
        'allow_abstain' => 'boolean',
        'allow_multiple_selections' => 'boolean',
        'quorum_percent' => 'decimal:2',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'settings' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(BallotOption::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(BallotResponse::class);
    }

    public function proxyVotes(): HasMany
    {
        return $this->hasMany(ProxyVote::class);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function isOpen(): bool
    {
        if ($this->status !== BallotStatus::Open) {
            return false;
        }

        $now = now();

        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [BallotStatus::Draft, BallotStatus::Scheduled], true);
    }

    public function hasBeenPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function voteVisibilityIsLocked(): bool
    {
        if ($this->hasBeenPublished()) {
            return true;
        }

        return $this->vote_visibility->isConfidential()
            && $this->responses()->exists();
    }
}
