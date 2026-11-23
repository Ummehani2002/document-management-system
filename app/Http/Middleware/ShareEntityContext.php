<?php

namespace App\Http\Middleware;

use App\Services\BusinessSectorService;
use App\Services\EntityContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShareEntityContext
{
    public function __construct(
        protected EntityContextService $entityContext,
        protected BusinessSectorService $businessSector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $entityId = $this->entityContext->getId($user);
            $entity = $this->entityContext->get($user);

            if ($entityId !== null && ! $request->has('entity_id')) {
                $request->merge(['entity_id' => $entityId]);
            }

            $switcherEntities = $this->entityContext->accessibleEntities($user)->map(function ($item) {
                $sector = $this->businessSector->sectorForEntity($item);

                return (object) [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sector' => $sector,
                    'sector_label' => $this->businessSector->label($sector),
                    'initials' => $item->displayCode(),
                    'logo_url' => entity_logo_url($item->name),
                ];
            });

            view()->share('currentEntity', $entity);
            view()->share('currentEntityId', $entityId);
            view()->share(
                'currentEntityLogoUrl',
                $entity !== null ? entity_logo_url((string) $entity->name) : null
            );
            view()->share(
                'currentEntitySectorLabel',
                $entity !== null ? $this->businessSector->label($this->businessSector->sectorForEntity($entity)) : null
            );
            view()->share('switcherEntities', $switcherEntities);
        }

        return $next($request);
    }
}
