<?php

$trustedProxiesValue = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'scheduler_enabled' => (bool) env('DEPLOYMENT_SCHEDULER_ENABLED', false),
    'trusted_proxies' => in_array($trustedProxiesValue, ['*', '**'], true)
        ? $trustedProxiesValue
        : array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', $trustedProxiesValue)
        ))),
];
