<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'due_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PICKING = 'picking';
    public const STATUS_PICKED = 'picked';
    public const STATUS_PACKING = 'packing';
    public const STATUS_PACKED = 'packed';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_COMPLETED = 'completed';

    /**
     * All available statuses.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PICKING,
        self::STATUS_PICKED,
        self::STATUS_PACKING,
        self::STATUS_PACKED,
        self::STATUS_DISPATCHED,
        self::STATUS_COMPLETED,
    ];

    /**
     * Get the customer (user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user relationship (for clarity).
     */
    public function customer(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the user who created this order.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this order.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all items in this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    /**
     * Get the dispatch record.
     */
    public function dispatch(): HasOne
    {
        return $this->hasOne(Dispatch::class);
    }

    /**
     * Generate the next order number with the given prefix.
     *
     * @param string $prefix 'ORD' for customer, 'REQ' for staff/admin/manager
     */
    public static function generateOrderNumber(string $prefix = 'ORD'): string
    {
        $lastOrder = self::where('order_number', 'like', $prefix . '-%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastOrder) {
            $parts = explode('-', $lastOrder->order_number);
            $sequence = isset($parts[1]) ? ((int) $parts[1]) + 1 : 1;
        }

        return sprintf('%s-%06d', $prefix, $sequence);
    }

    /**
     * Get total quantity of all items.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Get total items count.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Check if order is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isCompleted();
    }

    /**
     * Check if order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if order is dispatched.
     */
    public function isDispatched(): bool
    {
        return in_array($this->status, [self::STATUS_DISPATCHED, self::STATUS_COMPLETED]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(string $status): bool
    {
        return $this->update(['status' => $status]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        $statuses = array_map('trim', explode(',', $status));

        return count($statuses) === 1
            ? $query->where('status', $statuses[0])
            : $query->whereIn('status', $statuses);
    }

    /**
     * Scope to filter pending orders (not completed).
     */
    public function scopePending($query)
    {
        return $query->where('status', '!=', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter overdue orders.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_COMPLETED);
    }
}
