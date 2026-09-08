<?php

namespace App\Services;

use App\Models\Entity;
use Illuminate\Support\Collection;

class BusinessSectorService
{
    public const SESSION_KEY = 'current_business_sector';

    public const TRADING = 'trading';

    public const CONSTRUCTION = 'construction';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::TRADING, self::CONSTRUCTION];
    }

    public function get(): ?string
    {
        $sector = session(self::SESSION_KEY);

        return in_array($sector, self::all(), true) ? $sector : null;
    }

    public function set(string $sector): void
    {
        if (! in_array($sector, self::all(), true)) {
            abort(422, 'Invalid business sector.');
        }

        session([self::SESSION_KEY => $sector]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function label(?string $sector = null): string
    {
        $sector ??= $this->get();

        return match ($sector) {
            self::TRADING => 'Trading',
            self::CONSTRUCTION => 'Construction',
            default => 'Business',
        };
    }

    public function sectorForEntity(Entity $entity): string
    {
        $type = (string) ($entity->business_type ?? '');

        return in_array($type, self::all(), true) ? $type : self::CONSTRUCTION;
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @return Collection<int, Entity>
     */
    public function filterEntities(Collection $entities, ?string $sector = null): Collection
    {
        $sector ??= $this->get();

        if ($sector === null) {
            return $entities;
        }

        return $entities
            ->filter(fn (Entity $entity) => $this->sectorForEntity($entity) === $sector)
            ->values();
    }
}
