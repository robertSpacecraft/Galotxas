<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\InvalidOfficialResultActorException;
use App\Models\User;

class OfficialResultActorSnapshotService
{
    public function __construct(
        private readonly UnicodeTextService $text,
    ) {}

    public function snapshot(?User $actor): string
    {
        if (
            $actor === null
            || ! $actor->exists
            || ! $actor->active
            || $actor->role !== UserRole::ADMIN->value
        ) {
            throw new InvalidOfficialResultActorException;
        }

        $name = $this->text->squish($actor->name.' '.$actor->lastname);

        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidOfficialResultActorException;
        }

        return $name;
    }
}
