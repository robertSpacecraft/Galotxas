<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class OfficialResultIdentityLock
{
    /**
     * @param  Collection<int, Player>  $players
     * @param  Collection<int, PublicIdentityAuthorization>  $authorizations
     * @param  Collection<int, User>  $users
     */
    public function __construct(
        public Collection $players,
        public Collection $authorizations,
        public Collection $users,
        public ?User $actor,
    ) {}
}
