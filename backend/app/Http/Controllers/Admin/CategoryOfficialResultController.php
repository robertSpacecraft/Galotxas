<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\CupAlreadyOfficialException;
use App\Exceptions\CupOfficializationNotReadyException;
use App\Exceptions\InvalidOfficialResultActorException;
use App\Exceptions\InvalidReopenReasonException;
use App\Exceptions\LeagueAlreadyOfficialException;
use App\Exceptions\LeagueOfficializationNotReadyException;
use App\Exceptions\NoCurrentCupOfficialResultException;
use App\Exceptions\NoCurrentLeagueOfficialResultException;
use App\Exceptions\OfficialResultConcurrencyConflictException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReopenOfficialResultRequest;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Services\Admin\OfficialResultReadinessIssueFormatter;
use App\Services\OfficializeCupResultService;
use App\Services\OfficializeLeagueResultService;
use App\Services\ReopenCupResultService;
use App\Services\ReopenLeagueResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryOfficialResultController extends Controller
{
    public function officializeLeague(
        Request $request,
        Category $category,
        OfficializeLeagueResultService $service,
        OfficialResultReadinessIssueFormatter $issues,
    ): RedirectResponse {
        try {
            $result = $service->officialize($category, $request->user());
        } catch (LeagueOfficializationNotReadyException $exception) {
            return $this->notReady(
                $category,
                'La Liga ya no reúne las condiciones para oficializarse.',
                $issues->format(
                    OfficialResultCompetitionPart::LEAGUE,
                    $exception->safeIssues(),
                ),
            );
        } catch (
            LeagueAlreadyOfficialException
            |OfficialResultConcurrencyConflictException
            |InvalidOfficialResultActorException $exception
        ) {
            return $this->error($category, $exception->getMessage());
        } catch (OfficialResultSourceIntegrityException) {
            return $this->integrityError($category);
        }

        return redirect()
            ->route('admin.categories.show', $category)
            ->with('success', "Liga oficializada correctamente como v{$result->version}.");
    }

    public function officializeCup(
        Request $request,
        Category $category,
        OfficializeCupResultService $service,
        OfficialResultReadinessIssueFormatter $issues,
    ): RedirectResponse {
        try {
            $result = $service->officialize($category, $request->user());
        } catch (CupOfficializationNotReadyException $exception) {
            return $this->notReady(
                $category,
                'La Copa ya no reúne las condiciones para oficializarse.',
                $issues->format(
                    OfficialResultCompetitionPart::CUP,
                    $exception->safeIssues(),
                ),
            );
        } catch (
            CupAlreadyOfficialException
            |OfficialResultConcurrencyConflictException
            |InvalidOfficialResultActorException $exception
        ) {
            return $this->error($category, $exception->getMessage());
        } catch (OfficialResultSourceIntegrityException) {
            return $this->integrityError($category);
        }

        return redirect()
            ->route('admin.categories.show', $category)
            ->with('success', "Copa oficializada correctamente como v{$result->version}.");
    }

    public function show(
        Category $category,
        CategoryOfficialResult $officialResult,
    ): View {
        $this->assertBelongsToCategory($category, $officialResult);
        $category->loadMissing('championship.season');

        $officialResult->load([
            'leagueRows' => fn ($query) => $query->orderBy('position'),
            'cupWinner',
            'matchSnapshots' => function ($query) use ($officialResult): void {
                if ($officialResult->competition_part === OfficialResultCompetitionPart::LEAGUE) {
                    $query->orderBy('source_game_match_id');

                    return;
                }

                $query
                    ->orderByRaw("case stage when 'semifinal' then 1 when 'final' then 2 else 3 end")
                    ->orderBy('source_game_match_id');
            },
        ]);

        return view('admin.categories.official-results.show', [
            'category' => $category,
            'officialResult' => $officialResult,
        ]);
    }

    public function reopenForm(
        Category $category,
        CategoryOfficialResult $officialResult,
    ): View {
        $this->assertCurrentOfficialResult($category, $officialResult);
        $category->loadMissing('championship.season');

        return view('admin.categories.official-results.reopen', [
            'category' => $category,
            'officialResult' => $officialResult,
        ]);
    }

    public function reopen(
        ReopenOfficialResultRequest $request,
        Category $category,
        CategoryOfficialResult $officialResult,
        ReopenLeagueResultService $leagueService,
        ReopenCupResultService $cupService,
    ): RedirectResponse {
        $this->assertCurrentOfficialResult($category, $officialResult);

        try {
            $result = match ($officialResult->competition_part) {
                OfficialResultCompetitionPart::LEAGUE => $leagueService->reopen(
                    $category,
                    $request->user(),
                    $request->validated('reason'),
                    $officialResult,
                ),
                OfficialResultCompetitionPart::CUP => $cupService->reopen(
                    $category,
                    $request->user(),
                    $request->validated('reason'),
                    $officialResult,
                ),
            };
        } catch (InvalidReopenReasonException $exception) {
            return back()
                ->withErrors(['reason' => $exception->getMessage()])
                ->withInput();
        } catch (
            NoCurrentLeagueOfficialResultException
            |NoCurrentCupOfficialResultException
            |OfficialResultConcurrencyConflictException $exception
        ) {
            return $this->error($category, $exception->getMessage());
        } catch (InvalidOfficialResultActorException) {
            return $this->error($category, 'No se ha podido identificar un administrador activo.');
        } catch (OfficialResultSourceIntegrityException) {
            return $this->integrityError($category);
        }

        return redirect()
            ->route('admin.categories.show', $category)
            ->with(
                'success',
                sprintf(
                    '%s reabierta correctamente (v%d).',
                    $this->partLabel($result->competition_part),
                    $result->version,
                ),
            );
    }

    private function assertBelongsToCategory(
        Category $category,
        CategoryOfficialResult $officialResult,
    ): void {
        abort_unless((int) $officialResult->category_id === (int) $category->id, 404);
    }

    private function assertCurrentOfficialResult(
        Category $category,
        CategoryOfficialResult $officialResult,
    ): void {
        $this->assertBelongsToCategory($category, $officialResult);

        abort_unless(
            $officialResult->status === OfficialResultStatus::OFFICIAL
                && CategoryOfficialResult::query()
                    ->whereKey($officialResult->id)
                    ->where('category_id', $category->id)
                    ->where('competition_part', $officialResult->competition_part->value)
                    ->where('status', OfficialResultStatus::OFFICIAL->value)
                    ->exists(),
            404,
        );
    }

    /** @param list<string> $issues */
    private function notReady(
        Category $category,
        string $message,
        array $issues,
    ): RedirectResponse {
        return redirect()
            ->route('admin.categories.show', $category)
            ->with('error', $message)
            ->with('official_result_issues', $issues);
    }

    private function integrityError(Category $category): RedirectResponse
    {
        return $this->error(
            $category,
            'No se ha podido completar la operación porque el histórico oficial es incoherente.',
        );
    }

    private function error(Category $category, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.categories.show', $category)
            ->with('error', $message);
    }

    private function partLabel(OfficialResultCompetitionPart $part): string
    {
        return $part === OfficialResultCompetitionPart::LEAGUE ? 'Liga' : 'Copa';
    }
}
