<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Format DB timestamps for display in the application timezone (e.g. Asia/Dubai).
 *
 * Older rows may store UTC wall-clock in the database; newer rows may store local
 * wall-clock when app.timezone was set without UTC serialization. We pick the
 * interpretation that is not in the future relative to "now".
 */
class LocalDateTime
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Dubai');
    }

    public static function fromModel(Model $model, string $column): ?Carbon
    {
        $raw = $model->getRawOriginal($column);
        if ($raw === null || $raw === '') {
            return null;
        }

        $tz = self::timezone();
        $asLocalWallClock = Carbon::parse((string) $raw, $tz);
        $asUtcThenLocal = Carbon::parse((string) $raw, 'UTC')->timezone($tz);

        // DB value written as local time (e.g. 14:16 for 2:16 PM) breaks UTC parsing (+4h).
        if ($asUtcThenLocal->greaterThan(now($tz))) {
            return $asLocalWallClock;
        }

        return $asUtcThenLocal;
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

            return self::fromRaw($raw)?->format($format) ?? '—';
        }

        return self::fromRaw((string) $value)?->format($format) ?? '—';
    }

    protected static function fromRaw(string $raw): ?Carbon
    {
        $tz = self::timezone();
        $asLocalWallClock = Carbon::parse($raw, $tz);
        $asUtcThenLocal = Carbon::parse($raw, 'UTC')->timezone($tz);

        if ($asUtcThenLocal->greaterThan(now($tz))) {
            return $asLocalWallClock;
        }

        return $asUtcThenLocal;
    }
}
