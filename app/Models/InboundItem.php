<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundItem extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_STORED = 'stored';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'inbound_id',
        'item_id',
        'quantity',
        'received_quantity',
        'rack_id',
        'bin_id',
        'maintenance_date',
        'expiry_date',
        'manufacturing_year',
        'status',
        'created_by',
        'created_at',
    ];

    protected $appends = ['bin_location'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'expiry_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the inbound record.
     */
    public function inbound(): BelongsTo
    {
        return $this->belongsTo(Inbound::class);
    }

    /**
     * Get the item master record.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /**
     * Get the bin.
     */
    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    /**
     * Get the rack.
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the user who created this record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if item is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if item needs maintenance.
     */
    public function needsMaintenance(): bool
    {
        return $this->maintenance_date && $this->maintenance_date->isPast();
    }

    public function getBinLocationAttribute(): ?string
    {
        return $this->bin?->code;
    }

    public function isStored(): bool
    {
        return $this->status === self::STATUS_STORED;
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
     * Boot method to auto-set created_at.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }
}
