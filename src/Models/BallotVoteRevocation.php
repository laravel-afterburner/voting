<?php

namespace Afterburner\Voting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BallotVoteRevocation extends Model
{
    protected $fillable = [
        'ballot_id',
        'voter_unit_type',
        'voter_unit_id',
        'revoked_by_user_id',
        'ballot_option_id',
        'ip_address',
        'user_agent',
        'revoked_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(BallotOption::class, 'ballot_option_id');
    }
}
