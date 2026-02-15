<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'code',
        'label',
    ];

    /**
     * Get the warehouse this rack belongs to.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get all bins in this rack.
     */
    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    /**
     * Get all available bins in this rack.
     */
    public function availableBins(): HasMany
    {
        return $this->bins()->where('is_available', true);
    }

    /**
     * Get total bin count.
     */
    public function getBinCountAttribute(): int
    {
        return $this->bins()->count();
    }

    /**
     * Scope by warehouse.
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope by rack code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
