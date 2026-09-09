<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\MediaObjectKeyGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ManagedMediaReferenceRegistry
{
    /** Includes deleted news for explicit exclusion, never public visibility scopes. */
    public function query(ManagedMediaDomain $domain): Builder
    {
        $model = $domain->model();
        $columns = ['id', $domain->column()];
        if ($prefix = $domain->metadataPrefix()) {
            $columns = [...$columns, $prefix.'_width', $prefix.'_height'];
        }
        $query = $model::query();
        if ($domain === ManagedMediaDomain::News) {
            $query->withTrashed();
            $columns[] = 'deleted_at';
        }

        return $query->select($columns)->orderBy('id');
    }

    /** @return list<ManagedMediaReference> */
    public function batch(ManagedMediaDomain $domain, int $afterId = 0, int $limit = 100, ?int $throughId = null): array
    {
        if ($afterId < 0 || $limit < 1 || $limit > 1000 || ($throughId !== null && $throughId < $afterId)) {
            throw new InvalidArgumentException('El intervalo de referencias no es válido.');
        }

        return $this->query($domain)->where('id', '>', $afterId)
            ->when($throughId !== null, fn (Builder $query) => $query->where('id', '<=', $throughId))
            ->limit($limit)->get()->map(fn (Model $row) => $this->reference($domain, $row))->all();
    }

    public function find(ManagedMediaDomain $domain, int $id): ?ManagedMediaReference
    {
        $row = $this->query($domain)->find($id);

        return $row === null ? null : $this->reference($domain, $row);
    }

    public function identity(ManagedMediaReference $reference): ?string
    {
        $key = $reference->masterKey;
        if (! is_string($key) || ! (new MediaObjectKeyGenerator)->isValidForPurpose($key, $reference->domain->profile()->purpose())) {
            return null;
        }

        return substr($key, 0, strrpos($key, '.'));
    }

    /**
     * Bounded whereIn queries across ALL domains, independent of future CLI filters.
     * SQL may use a case-insensitive collation; validation and grouping below never do.
     *
     * @param  list<ManagedMediaReference>  $references
     * @return array<string, list<ManagedMediaReference>>
     */
    public function liveOwners(array $references): array
    {
        $identities = [];
        foreach ($references as $reference) {
            if (! $reference->deleted && ($identity = $this->identity($reference)) !== null) {
                $identities[$identity] = [];
            }
        }
        foreach (array_chunk(array_keys($identities), 100) as $chunk) {
            $keys = [];
            foreach ($chunk as $identity) {
                foreach (['jpg', 'png', 'webp'] as $extension) {
                    $keys[] = $identity.'.'.$extension;
                }
            }
            foreach (ManagedMediaDomain::cases() as $domain) {
                $query = $this->query($domain)->whereIn($domain->column(), $keys);
                if ($domain === ManagedMediaDomain::News) {
                    $query->whereNull('deleted_at');
                }
                foreach ($query->lazyById(100) as $row) {
                    $owner = $this->reference($domain, $row);
                    $identity = $this->identity($owner);
                    if ($identity !== null && array_key_exists($identity, $identities)) {
                        $identities[$identity][] = $owner;
                    }
                }
            }
        }

        return $identities;
    }

    private function reference(ManagedMediaDomain $domain, Model $row): ManagedMediaReference
    {
        $prefix = $domain->metadataPrefix();

        return new ManagedMediaReference($domain, (int) $row->getKey(), $row->{$domain->column()},
            $domain === ManagedMediaDomain::News && $row->deleted_at !== null,
            $prefix === null ? null : $row->{$prefix.'_width'}, $prefix === null ? null : $row->{$prefix.'_height'});
    }
}
