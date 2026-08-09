<?php

namespace App\Http\Resources;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Services\PublicIdentityNoticeService;
use App\Services\SchoolEnrollmentAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $configuration = app(SchoolEnrollmentAvailabilityService::class)
            ->publicConfiguration($this->resource);
        $authorizationEnabled = (bool) config('public_identity.authorization_enabled');
        $authorization = ['enabled' => false];
        if ($authorizationEnabled) {
            $notice = app(PublicIdentityNoticeService::class)->current();
            $authorization = [
                'enabled' => true,
                'notice_id' => $notice['id'],
                'notice_version' => $notice['version'],
                'scope' => $notice['scope'],
                'modes' => array_map(
                    fn (PublicIdentityAuthorizationMode $mode): string => $mode->value,
                    PublicIdentityAuthorizationMode::cases()
                ),
            ];
        }

        return [
            'name' => $this->name,
            'description' => $this->public_description,
            'enrollment_information' => $this->enrollment_information,
            'enrollment_status' => $configuration['status'],
            'enrollments_open' => $configuration['enrollments_open'],
            'privacy_notice' => $configuration['privacy_notice'],
            'default_location' => $this->relationLoaded('defaultLocation')
                && $this->defaultLocation !== null
                    ? new PublicSchoolLocationResource($this->defaultLocation)
                    : null,
            'levels' => $this->relationLoaded('levels')
                ? PublicSchoolLevelResource::collection($this->levels)
                : [],
            'public_identity_authorization' => $authorization,
        ];
    }
}
