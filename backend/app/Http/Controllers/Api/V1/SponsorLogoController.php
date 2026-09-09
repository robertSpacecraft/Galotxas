<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Services\Media\Exceptions\MediaObjectNotFound;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\MediaDeliveryService;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaResolver;
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

    public function variant(
        Sponsor $sponsor,
        int $width,
        ResponsiveMediaResolver $resolver,
        MediaDeliveryService $delivery,
    ): Response|RedirectResponse {
        abort_unless($sponsor->isEffectivelyVisible(), 404);
        $variant = $resolver->variant($sponsor->logo_key, ResponsiveImageProfile::SponsorLogo, $width);
        abort_unless($variant !== null, 404);

        try {
            return $delivery->deliverVariant($variant);
        } catch (MediaObjectNotFound) {
            abort(404);
        } catch (MediaStorageException) {
            abort(503, 'El recurso multimedia no está disponible temporalmente.');
        }
    }
}
