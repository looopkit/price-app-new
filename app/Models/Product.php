<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'external_id',
        'type',
        'name',
        'code',
        'article',
        'parent_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function orderPositions(): HasMany
    {
        return $this->hasMany(OrderPosition::class);
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

    public function isVariant(): bool
    {
        return $this->type === 'variant';
    }

    public function hasParent(): bool
    {
        return $this->parent_id !== null;
    }
}