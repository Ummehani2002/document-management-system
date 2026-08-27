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
