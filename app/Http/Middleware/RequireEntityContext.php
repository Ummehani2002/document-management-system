<?php

namespace App\Http\Middleware;

use App\Services\EntityContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEntityContext
{
    public function __construct(
        protected EntityContextService $entityContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->entityContext->getId($request->user()) === null) {
            return redirect()
                ->route('dashboard')
                ->with('info', 'Select a company to continue.');
        }

        return $next($request);
    }
}
