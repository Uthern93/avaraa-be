<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'location',
    ];

    /**
     * Get all racks in this warehouse.
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    /**
     * Get all bins in this warehouse (through racks).
     */
    public function bins()
    {
        return Bin::whereHas('rack', fn ($q) => $q->where('warehouse_id', $this->id));
    }

    /**
     * Get all item stocks in this warehouse.
     */
    public function itemStocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    /**
     * Get all stock movements in this warehouse.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get all delivery order items from this warehouse.
     */
    public function deliveryOrderItems(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }
}
