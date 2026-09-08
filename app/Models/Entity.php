<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use SoftDeletes;

    public const TYPE_TRADING = 'trading';

    public const TYPE_CONSTRUCTION = 'construction';

    protected $fillable = ['name', 'business_type'];

    public function projects()
    {
        return $this->hasMany(Project::class);
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
