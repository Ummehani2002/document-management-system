<?php

namespace App\Http\Middleware;

use App\Services\EntityContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShareEntityContext
{
    public function __construct(
        protected EntityContextService $entityContext
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

            view()->share('currentEntity', $entity);
            view()->share('currentEntityId', $entityId);
        }

        return $next($request);
    }
}
