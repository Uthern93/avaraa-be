<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inbound extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inbound_number',
        'batch_id',
        'warehouse_id',
        'expected_arrival_date',
        'actual_arrival_date',
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
            'expected_arrival_date' => 'date',
            'actual_arrival_date' => 'date',
        ];
    }

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_COMPLETED = 'completed';

    /**
     * All available statuses.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFYING,
        self::STATUS_COMPLETED,
    ];

    /**
     * Allowed status transitions.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => self::STATUS_VERIFYING,
        self::STATUS_VERIFYING => self::STATUS_COMPLETED,
    ];

    /**
     * Get the warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who created this inbound.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this inbound.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all items in this inbound.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InboundItem::class);
    }

    /**
     * Get all stock movements related to this inbound batch.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id', 'batch_id');
    }

    /**
     * Get all item stocks related to this inbound batch.
     */
    public function itemStocks(): HasMany
    {
        return $this->hasMany(ItemStock::class, 'batch_id', 'batch_id');
    }

    /**
     * Generate the next inbound number.
     */
    public static function generateInboundNumber(): string
    {
        $year = date('Y');
        $lastInbound = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastInbound) {
            $parts = explode('-', $lastInbound->inbound_number);
            $sequence = isset($parts[2]) ? ((int) $parts[2]) + 1 : 1;
        }

        return sprintf('GRN-%s-%03d', $year, $sequence);
    }

    /**
     * Generate the next batch ID.
     * Format: B-<YYYYMMDD>-<A, B, C, ...>
     */
    public static function generateBatchId(): string
    {
        $date = date('Ymd');
        $prefix = 'B-' . $date . '-';

        $lastBatch = self::where('batch_id', 'like', $prefix . '%')
            ->orderBy('batch_id', 'desc')
            ->first();

        if ($lastBatch) {
            $lastLetter = substr($lastBatch->batch_id, -1);
            $nextLetter = chr(ord($lastLetter) + 1);
            // If we exceed Z, start with AA, AB, etc.
            if ($nextLetter > 'Z') {
                $nextLetter = 'A' . chr(ord(substr($lastBatch->batch_id, -2, 1)) + 1);
            }
        } else {
            $nextLetter = 'A';
        }

        return $prefix . $nextLetter;
    }

    /**
     * Get total quantity of all items.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Check if inbound is stored.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if inbound is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Get the next allowed status.
     */
    public function getNextStatus(): ?string
    {
        return self::STATUS_TRANSITIONS[$this->status] ?? null;
    }

    /**
     * Check if the inbound can transition to the given status.
     */
    public function canTransitionTo(string $status): bool
    {
        return $this->getNextStatus() === $status;
    }

    /**
     * Advance to the next status.
     */
    public function advanceStatus(): bool
    {
        $next = $this->getNextStatus();
        if (!$next) {
            return false;
        }
        return $this->update(['status' => $next]);
    }

    /**
     * Mark inbound as verifying.
     */
    public function markAsVerifying(): bool
    {
        return $this->update(['status' => self::STATUS_VERIFYING]);
    }

    /**
     * Mark inbound as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
