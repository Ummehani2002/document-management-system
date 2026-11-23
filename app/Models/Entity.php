<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use SoftDeletes;

    public const TYPE_TRADING = 'trading';

    public const TYPE_CONSTRUCTION = 'construction';

    protected $fillable = ['name', 'short_code', 'business_type'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function displayCode(): string
    {
        $code = strtoupper(trim((string) $this->short_code));

        return $code !== '' ? $code : entity_initials((string) $this->name);
    }

    public function isTrading(): bool
    {
        return $this->business_type === self::TYPE_TRADING;
    }

    public function businessTypeLabel(): string
    {
        return match ($this->business_type) {
            self::TYPE_TRADING => 'Trading',
            self::TYPE_CONSTRUCTION => 'Construction',
            default => 'Construction',
        };
    }
}
