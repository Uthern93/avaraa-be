<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'warehouse_id',
        'bin_id',
        'batch_id',
        'expiry_date',
        'maintenance_date',
        'manufacturing_year',
        'quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'maintenance_date' => 'date',
        ];
    }

    /**
     * Get the item master record.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /**
     * Get the warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the bin.
     */
    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    /**
     * Get the inbound (batch) record.
     */
    public function inbound(): BelongsTo
    {
        return $this->belongsTo(Inbound::class, 'batch_id', 'batch_id');
    }

    /**
     * Check if stock is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Increase stock quantity.
     */
    public function increaseQuantity(int $amount): bool
    {
        return $this->increment('quantity', $amount);
    }

    /**
     * Decrease stock quantity.
     */
    public function decreaseQuantity(int $amount): bool
    {
        if ($this->quantity < $amount) {
            return false;
        }
        return $this->decrement('quantity', $amount);
    }

    /**
     * Scope to filter by warehouse.
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by bin.
     */
    public function scopeInBin($query, string $binId)
    {
        return $query->where('bin_id', $binId);
    }

    /**
     * Scope to filter available stock (quantity > 0).
     */
    public function scopeAvailable($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope to filter non-expired stock.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', now());
        });
    }

    /**
     * Scope for FEFO (First Expired, First Out) ordering.
     */
    public function scopeFefo($query)
    {
        return $query->orderByRaw('expiry_date IS NULL, expiry_date ASC');
    }
}
