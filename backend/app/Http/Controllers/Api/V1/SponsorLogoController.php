<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SponsorLogoController extends Controller
{
    public function __invoke(
        Sponsor $sponsor,
        MediaDeliveryService $delivery
    ): Response|RedirectResponse {
        abort_unless($sponsor->isEffectivelyVisible(), 404);

        try {
            return $delivery->deliver($sponsor->logo_key);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'El recurso multimedia no está disponible temporalmente.');
        }
    }
}
