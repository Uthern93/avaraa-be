<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
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
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
    ];

    /**
     * Movement type constants.
     */
    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';

    /**
     * Reference type constants.
     */
    public const REF_INBOUND = 'inbound';
    public const REF_INBOUND_ITEM = 'inbound_item';
    public const REF_DELIVERY_ORDER = 'delivery_order';
    public const REF_DELIVERY_ORDER_ITEM = 'delivery_order_item';
    public const REF_ADJUSTMENT = 'adjustment';

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
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * Check if this is an inbound movement.
     */
    public function isInbound(): bool
    {
        return $this->movement_type === self::TYPE_IN;
    }

    /**
     * Check if this is an outbound movement.
     */
    public function isOutbound(): bool
    {
        return $this->movement_type === self::TYPE_OUT;
    }

    /**
     * Create an IN movement.
     */
    public static function createInMovement(array $data): self
    {
        return self::create(array_merge($data, ['movement_type' => self::TYPE_IN]));
    }

    /**
     * Create an OUT movement.
     */
    public static function createOutMovement(array $data): self
    {
        return self::create(array_merge($data, ['movement_type' => self::TYPE_OUT]));
    }

    /**
     * Scope to filter IN movements.
     */
    public function scopeInMovements($query)
    {
        return $query->where('movement_type', self::TYPE_IN);
    }

    /**
     * Scope to filter OUT movements.
     */
    public function scopeOutMovements($query)
    {
        return $query->where('movement_type', self::TYPE_OUT);
    }

    /**
     * Scope to filter by reference.
     */
    public function scopeForReference($query, string $type, int $id)
    {
        return $query->where('reference_type', $type)
            ->where('reference_id', $id);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
