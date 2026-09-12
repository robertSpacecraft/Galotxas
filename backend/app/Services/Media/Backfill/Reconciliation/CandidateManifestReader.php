<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use stdClass;
use Throwable;

/**
 * Read-only durable candidate of one journal item, shared by D2-A inspection and the D2-B2
 * mutation repository so both apply one coherence rule. No storage I/O and no mutation.
 */
final class CandidateManifestReader
{
    public function __construct(private readonly ResponsiveMediaKeys $keys) {}

    /**
     * A candidate is coherent only when the whole snapshot metadata agrees: hashes, canonical
     * keys, domain profile/policy and the accepted manifest contract.
     *
     * @return array{?ResponsiveManifest, bool}
     */
    public function read(stdClass $item): array
    {
        $preflight = is_string($item->preflight_classification ?? null)
            ? PreflightClassification::tryFrom($item->preflight_classification)
            : null;
        $json = $item->candidate_manifest_json ?? null;
        $hash = $item->candidate_manifest_sha256 ?? null;
        $master = $item->master_key ?? null;
        $masterHash = $item->master_key_hash ?? null;
        $manifestKey = $item->manifest_key ?? null;
        if ($json === null && $hash === null && $manifestKey === null) {
            return [null, $preflight !== PreflightClassification::LegacyBackfillable];
        }
        if (! is_string($json) || ! is_string($hash) || ! is_string($master)
            || ! is_string($masterHash) || ! is_string($manifestKey)
            || ! hash_equals(hash('sha256', $json), $hash)
            || ! hash_equals(hash('sha256', $master), $masterHash)) {
            return [null, false];
        }
        try {
            $manifest = ResponsiveManifest::fromJson($json, $master, $manifestKey, $this->keys);
            $domain = ManagedMediaDomain::tryFrom(is_string($item->domain ?? null) ? $item->domain : '');
            if ($preflight !== PreflightClassification::LegacyBackfillable || $domain === null
                || $manifest->schemaVersion !== 2 || $manifest->profile !== $domain->profile()
                || $manifest->policy !== $domain->policy()) {
                return [null, false];
            }

            return [$manifest, true];
        } catch (Throwable) {
            return [null, false];
        }
    }
}
