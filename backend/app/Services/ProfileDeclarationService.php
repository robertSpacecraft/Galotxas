<?php

namespace App\Services;

use App\Models\Player;
use App\Models\ProfileDeclaration;
use App\Models\User;
use Carbon\CarbonImmutable;

class ProfileDeclarationService
{
    public const GENERAL = 'account_profile_accuracy';

    public const BIRTH_DATE = 'birth_date_accuracy';

    public function __construct(private readonly AccountProfileNoticeService $notices) {}

    public function hasRecognizedGeneral(User $user): bool
    {
        $notice = $this->notices->current();

        return ProfileDeclaration::query()
            ->where('actor_user_id', $user->id)
            ->where('declaration_kind', self::GENERAL)
            ->where('notice_id', $notice['id'])
            ->where('notice_version', $notice['version'])
            ->exists();
    }

    public function recordGeneral(User $actor, ?Player $subject = null): ProfileDeclaration
    {
        if ($this->hasRecognizedGeneral($actor)) {
            $notice = $this->notices->current();

            return ProfileDeclaration::query()
                ->where('actor_user_id', $actor->id)
                ->where('declaration_kind', self::GENERAL)
                ->where('notice_id', $notice['id'])
                ->where('notice_version', $notice['version'])
                ->latest('declared_at')
                ->firstOrFail();
        }

        return $this->record($actor, $subject, self::GENERAL);
    }

    public function recordBirthDate(User $actor, Player $subject): ProfileDeclaration
    {
        return $this->record($actor, $subject, self::BIRTH_DATE);
    }

    private function record(User $actor, ?Player $subject, string $kind): ProfileDeclaration
    {
        $notice = $this->notices->current();

        return ProfileDeclaration::query()->create([
            'actor_user_id' => $actor->id,
            'subject_player_id' => $subject?->id,
            'declaration_kind' => $kind,
            'notice_id' => $notice['id'],
            'notice_version' => $notice['version'],
            'declared_at' => CarbonImmutable::now(),
        ]);
    }
}
