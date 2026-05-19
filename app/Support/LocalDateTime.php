<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Format DB timestamps for display in the application timezone.
 *
 * Laravel stores created_at/updated_at in UTC. When app.timezone is not UTC, Eloquent
 * can hydrate those values in the app timezone without shifting the instant — so
 * "08:45" in the database (UTC) may display as "08:45 AM" instead of UAE "12:45 PM".
 * We always interpret the raw database value as UTC, then convert for display.
 */
class LocalDateTime
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Dubai');
    }

    public static function fromDatabase(?string $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return Carbon::parse($raw, 'UTC')->timezone(self::timezone());
    }

    public static function fromModel(Model $model, string $column): ?Carbon
    {
        $raw = $model->getRawOriginal($column);

        return self::fromDatabase(is_string($raw) ? $raw : null);
    }

    public static function formatModel(Model $model, string $column, string $format = 'd M Y, g:i A'): string
    {
        $dt = self::fromModel($model, $column);

        return $dt?->format($format) ?? '—';
    }

    /**
     * @param  Carbon|DateTimeInterface|string|null  $value
     */
    public static function format(mixed $value, string $format = 'd M Y, g:i A'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof Model) {
            return '—';
        }

        if ($value instanceof Carbon || $value instanceof DateTimeInterface) {
            $raw = $value->format('Y-m-d H:i:s');
            $dt = self::fromDatabase($raw);

            return $dt?->format($format) ?? '—';
        }

        return self::fromDatabase((string) $value)?->format($format) ?? '—';
    }
}
