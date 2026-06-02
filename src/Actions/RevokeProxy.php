<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\VotingAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RevokeProxy
{
    public function execute(ProxyVote $proxy, User $user): ProxyVote
    {
        Gate::forUser($user)->authorize('revoke', $proxy);

        if ($proxy->revoked_at !== null) {
            throw new VotingException('This proxy has already been revoked.');
        }

        VotingAuditLogger::proxyRevoked($proxy, $user);

        $proxy->update(['revoked_at' => now()]);

        return $proxy->fresh();
    }
}
