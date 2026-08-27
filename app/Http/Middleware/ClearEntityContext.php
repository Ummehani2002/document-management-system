<?php

namespace App\Http\Middleware;

use App\Services\EntityContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearEntityContext
{
    public function __construct(
        protected EntityContextService $entityContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->entityContext->clear();

        return $next($request);
    }
}
