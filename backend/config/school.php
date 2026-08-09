<?php

return [
    'enrollment_enabled' => (bool) env('SCHOOL_ENROLLMENT_ENABLED', false),

    'retention' => [
        'unformalized_months' => (int) env('SCHOOL_UNFORMALIZED_RETENTION_MONTHS', 6),
        'student_years' => (int) env('SCHOOL_STUDENT_RETENTION_YEARS', 2),
    ],
];
