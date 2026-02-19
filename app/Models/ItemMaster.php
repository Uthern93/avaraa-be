<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemMaster extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'item_master';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_sku',
        'item_name',
        'category_id',
        'weight',
        'storage_type',
        'qty_per_pallet',
        'qty_per_carton',
        'dimension_width',
        'dimension_height',
        'dimension_depth',
        'dimension_unit',
        'created_by',
        'updated_by',
    ];

    /**
     * Storage type constants.
     */
    public const STORAGE_PALLET = 1;
    public const STORAGE_CARTON = 2;
    public const STORAGE_ODD_SIZE = 3;

    public const STORAGE_TYPES = [
        self::STORAGE_PALLET => 'Pallet',
        self::STORAGE_CARTON => 'Carton',
        self::STORAGE_ODD_SIZE => 'Odd Size',
    ];

    /**
     * Get the category of this item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created this item.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this item.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all stock records for this item.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class, 'item_id');
    }

    /**
     * Get all stock movements for this item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    /**
     * Get all inbound items for this item.
     */
    public function inboundItems(): HasMany
    {
        return $this->hasMany(InboundItem::class, 'item_id');
    }

    /**
     * Get all delivery order items for this item.
     */
    public function deliveryOrderItems(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class, 'item_id');
    }

    /**
     * Get total stock quantity across all warehouses.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->stocks()->sum('quantity');
    }

    /**
     * Get stock quantity for a specific warehouse.
     */
    public function getStockInWarehouse(int $warehouseId): int
    {
        return $this->stocks()
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
