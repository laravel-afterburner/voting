<?php

namespace Afterburner\Voting\Models;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyVote extends Model
{
    protected $fillable = [
        'team_id',
        'ballot_id',
        'grantor_voter_unit_type',
        'grantor_voter_unit_id',
        'proxy_holder_user_id',
        'granted_by_user_id',
        'valid_from',
        'valid_until',
        'revoked_at',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function proxyHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_holder_user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $now)
            ->where(function (Builder $inner) use ($now) {
                $inner->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $now);
            });
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        $now = now();

        if ($this->valid_from->gt($now)) {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->gte($now);
    }

    public function matchesGrantor(string $voterUnitType, int $voterUnitId): bool
    {
        return $this->grantor_voter_unit_type === $voterUnitType
            && $this->grantor_voter_unit_id === $voterUnitId;
    }
}
