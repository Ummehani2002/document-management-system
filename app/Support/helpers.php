<?php

use App\Support\LocalDateTime;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('format_local_datetime')) {
    /**
     * @param  \Carbon\Carbon|\DateTimeInterface|string|null  $value
     */
    function format_local_datetime(mixed $value, string $format = 'd M Y, g:i A'): string
    {
        return LocalDateTime::format($value, $format);
    }
}

if (! function_exists('format_model_datetime')) {
    function format_model_datetime(Model $model, string $column, string $format = 'd M Y, g:i A'): string
    {
        return LocalDateTime::formatModel($model, $column, $format);
    }
}

if (! function_exists('entity_route')) {
    /**
     * Build a route URL, automatically including the current entity context when set.
     *
     * @param  array<string, mixed>  $parameters
     */
    function entity_route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $entityId = app(\App\Services\EntityContextService::class)->getId(auth()->user());

        if ($entityId !== null && ! array_key_exists('entity_id', $parameters)) {
            $parameters['entity_id'] = $entityId;
        }

        return route($name, $parameters, $absolute);
    }
}

if (! function_exists('entity_initials')) {
    function entity_initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $initials .= strtoupper(substr($word, 0, 1));

            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'CO';
    }
}

if (! function_exists('entity_logo_url')) {
    /**
     * Optional company logo for known entity names.
     */
    function entity_logo_url(string $name): ?string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        if (str_contains($key, 'water in motion')) {
            return asset('images/water-in-motion.png').'?v=1';
        }

        if (str_contains($key, 'proscape infra')) {
            return asset('images/proscape-infra.png').'?v=1';
        }

        if (str_contains($key, 'proscape international')) {
            return asset('images/proscape-international.png').'?v=1';
        }

        // Plain PROSCAPE (exact / short name only — after infra & international checks)
        if ($key === 'proscape' || preg_match('/^proscape(\s+llc)?$/', $key) === 1) {
            return asset('images/proscape.png').'?v=1';
        }

        if (str_contains($key, 'metaline')) {
            return asset('images/metaline.png').'?v=1';
        }

        if (str_contains($key, 'stones') && str_contains($key, 'slates')) {
            return asset('images/stones-and-slates.png').'?v=1';
        }

        if (str_contains($key, 'tanseeq llc') || $key === 'tanseeq') {
            return asset('images/tanseeq-llc.png').'?v=1';
        }

        if (str_contains($key, 'tanseeq')) {
            return asset('images/tanseeq-investment.png').'?v=1';
        }

        return null;
    }
}
