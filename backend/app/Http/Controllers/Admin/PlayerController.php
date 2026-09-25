<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OfficialResultMutationImpact;
use App\Enums\PlayerGender;
use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlayerRequest;
use App\Http\Requests\Admin\UpdatePlayerRequest;
use App\Models\Category;
use App\Models\Player;
use App\Models\User;
use App\Services\OfficialResultLockService;
use App\Services\OfficialResultMutationGuard;
use App\Services\PlayerSlugService;
use App\Services\PlayerUniqueConstraintService;
use App\Services\PublicIdentityAuthorizationService;
use App\Services\PublicIdentityNoticeService;
use App\Services\PublicPlayerIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::with('user')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.players.index', compact('players'));
    }

    public function create()
    {
        $users = User::doesntHave('player')
            ->orderBy('name')
            ->get();

        $genderOptions = PlayerGender::options();
        $dominantHandOptions = [
            'right' => 'Derecha',
            'left' => 'Izquierda',
            'both' => 'Ambas',
        ];

        return view('admin.players.create', compact('users', 'genderOptions', 'dominantHandOptions'));
    }

    public function store(
        StorePlayerRequest $request,
        PlayerSlugService $slugs,
        PlayerUniqueConstraintService $uniqueConstraints,
    ) {
        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);

        try {
            Player::create([
                'user_id' => $validated['user_id'],
                'nickname' => $validated['nickname'] ?? null,
                'slug' => $slugs->generate($validated['nickname'] ?? null, $user),
                'dni' => $validated['dni'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'level' => $validated['level'],
                'license_number' => $validated['license_number'] ?? null,
                'dominant_hand' => $validated['dominant_hand'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'active' => $validated['active'] ?? false,
            ]);
        } catch (QueryException $exception) {
            $uniqueConstraints->rethrowAsValidation($exception);
        }

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador creado correctamente.');
    }

    public function show(
        Player $player,
        PublicIdentityAuthorizationService $authorizationService,
        PublicIdentityNoticeService $noticeService,
        PublicPlayerIdentityService $publicIdentityService,
    ) {
        $player->load([
            'user',
            'teams',
            'entries.category',
            'publicIdentityAuthorizations' => fn ($query) => $query->ordered(),
        ]);

        $isMinor = $authorizationService->isMinor($player);
        $blockingAuthorization = $player->publicIdentityAuthorizations->first(
            fn ($authorization): bool => in_array($authorization->state, [
                PublicIdentityAuthorizationState::PENDING,
                PublicIdentityAuthorizationState::APPROVED,
            ], true)
        );
        $displayAuthorization = $blockingAuthorization
            ?? $player->publicIdentityAuthorizations->first();
        $authorizationEnabled = (bool) config('public_identity.authorization_enabled');
        $notificationEnabled = (bool) config('public_identity.notification_enabled');
        $canRequest = $isMinor
            && $authorizationEnabled
            && $notificationEnabled
            && $blockingAuthorization === null;
        $availableModes = $canRequest
            ? collect([
                PublicIdentityAuthorizationMode::ALIAS,
                PublicIdentityAuthorizationMode::NAME_INITIAL,
            ])
                ->filter(fn (PublicIdentityAuthorizationMode $mode): bool => $authorizationService
                    ->playerSupportsMode($player, $mode))
                ->values()
            : collect();

        return view('admin.players.show', [
            'player' => $player,
            'isMinor' => $isMinor,
            'publicIdentityDisplayName' => $publicIdentityService->displayName($player),
            'displayAuthorization' => $displayAuthorization,
            'blockingAuthorization' => $blockingAuthorization,
            'authorizationEnabled' => $authorizationEnabled,
            'notificationEnabled' => $notificationEnabled,
            'availableAuthorizationModes' => $availableModes,
            'publicIdentityNotice' => $canRequest && $availableModes->isNotEmpty()
                ? $noticeService->current()
                : null,
        ]);
    }

    public function edit(Player $player)
    {
        $users = User::whereDoesntHave('player')
            ->orWhere('id', $player->user_id)
            ->orderBy('name')
            ->get();

        $genderOptions = PlayerGender::options();
        $dominantHandOptions = [
            'right' => 'Derecha',
            'left' => 'Izquierda',
            'both' => 'Ambas',
        ];

        return view('admin.players.edit', compact('player', 'users', 'genderOptions', 'dominantHandOptions'));
    }

    public function update(
        UpdatePlayerRequest $request,
        Player $player,
        PlayerUniqueConstraintService $uniqueConstraints,
    ) {
        $validated = $request->validated();

        $data = [
            'user_id' => $validated['user_id'],
            'nickname' => $validated['nickname'] ?? null,
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'license_number' => $validated['license_number'] ?? null,
            'dominant_hand' => $validated['dominant_hand'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $validated['active'] ?? false,
        ];

        try {
            $player->update($data);
        } catch (QueryException $exception) {
            $uniqueConstraints->rethrowAsValidation($exception);
        }

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador actualizado correctamente.');
    }

    public function destroy(
        Player $player,
        OfficialResultMutationGuard $mutationGuard,
        OfficialResultLockService $locks,
    ) {
        DB::transaction(function () use ($player, $mutationGuard, $locks): void {
            $categoryIds = Category::query()
                ->where(function ($query) use ($player): void {
                    $query->whereHas(
                        'entries',
                        fn ($entryQuery) => $entryQuery->where('player_id', $player->id)
                    )->orWhereHas(
                        'registrations',
                        fn ($registrationQuery) => $registrationQuery->where('player_id', $player->id)
                    )->orWhereHas(
                        'teams.players',
                        fn ($playerQuery) => $playerQuery->where('players.id', $player->id)
                    );
                })
                ->orderBy('id')
                ->pluck('id');

            $mutationGuard->lockAndGuardCategories(
                $categoryIds,
                OfficialResultMutationImpact::PARTICIPANTS
            );
            $locks->lockRoundsAndMatches($categoryIds);
            $locks->lockEntriesAndTeams($categoryIds);

            $lockedPlayer = Player::query()->lockForUpdate()->findOrFail($player->id);
            $lockedPlayer->delete();
        });

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador eliminado correctamente.');
    }
}
