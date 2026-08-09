<?php

namespace App\Services;

use App\Models\SchoolProgram;

class SchoolPublicOverviewService
{
    public function get(): ?SchoolProgram
    {
        return SchoolProgram::query()
            ->select([
                'id',
                'name',
                'public_description',
                'enrollment_information',
                'is_public',
                'enrollments_open',
                'default_school_location_id',
                'contact_phone',
                'contact_email',
            ])
            ->effectivelyPublic()
            ->with([
                'defaultLocation' => fn ($query) => $query
                    ->select([
                        'id',
                        'name',
                        'locality',
                        'address',
                        'is_active',
                    ])
                    ->active(),
                'levels' => fn ($query) => $query
                    ->select([
                        'id',
                        'school_program_id',
                        'name',
                        'minimum_age',
                        'maximum_age',
                        'is_active',
                        'is_public',
                        'sort_order',
                    ])
                    ->effectivelyPublic()
                    ->ordered()
                    ->with([
                        'schedules' => fn ($scheduleQuery) => $scheduleQuery
                            ->select([
                                'id',
                                'school_level_id',
                                'school_location_id',
                                'day_of_week',
                                'starts_at',
                                'ends_at',
                                'is_active',
                                'sort_order',
                            ])
                            ->effectivelyPublic()
                            ->orderBy('day_of_week')
                            ->orderBy('starts_at')
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->with([
                                'location' => fn ($locationQuery) => $locationQuery
                                    ->select([
                                        'id',
                                        'name',
                                        'locality',
                                        'address',
                                        'is_active',
                                    ])
                                    ->active(),
                            ]),
                    ]),
            ])
            ->first();
    }
}
