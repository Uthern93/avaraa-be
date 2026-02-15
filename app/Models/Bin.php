<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bin extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rack_id',
        'number',
        'code',
        'is_available',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    /**
     * Get the rack this bin belongs to.
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get all item stocks in this bin.
     */
    public function itemStocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    /**
     * Get all inbound items assigned to this bin.
     */
    public function inboundItems(): HasMany
    {
        return $this->hasMany(InboundItem::class);
    }

    /**
     * Get the rack type code (A, B, C...) via the rack.
     */
    public function getRackTypeAttribute(): string
    {
        return $this->rack->code;
    }

    /**
     * Get the rack label via the rack.
     */
    public function getRackLabelAttribute(): string
    {
        return $this->rack->label;
    }

    /**
     * Get current stock quantity in this bin.
     */
    public function getCurrentStockAttribute(): int
    {
        return $this->itemStocks()->sum('quantity');
    }

    /**
     * Scope to filter available bins.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope bins by rack type code.
     */
    public function scopeOfRackType($query, string $rackCode)
    {
        return $query->whereHas('rack', fn($q) => $q->where('code', $rackCode));
    }

    /**
     * Scope bins by warehouse.
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->whereHas('rack', fn($q) => $q->where('warehouse_id', $warehouseId));
    }
}
