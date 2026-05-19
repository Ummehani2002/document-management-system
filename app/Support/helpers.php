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
