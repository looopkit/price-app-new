<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'external_id',
        'type',
        'product_id',
        'total_quantity',
        'purchase_quantity',
        'price',
        'total',
        'purchase_id',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:3',
        'purchase_quantity' => 'decimal:3',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(OrderPosition::class, 'purchase_id');
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(OrderPosition::class, 'purchase_id');
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByExternalId($query, string $externalId)
    {
        return $query->where('external_id', $externalId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCustomerOrders($query)
    {
        return $query->where('type', 'customerorder');
    }

    public function scopePurchaseOrders($query)
    {
        return $query->where('type', 'purchaseorder');
    }

    public function isCustomerOrder(): bool
    {
        return $this->type === 'customerorder';
    }

    public function isPurchaseOrder(): bool
    {
        return $this->type === 'purchaseorder';
    }

    public function getRemainingQuantity(): float
    {
        return (float) ($this->total_quantity - $this->purchase_quantity);
    }

    public function isFullyCovered(): bool
    {
        return $this->purchase_quantity >= $this->total_quantity;
    }
}