<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'point_cost',
        'icon',
        'is_active',
        'stock',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'point_cost' => 'integer',
        'is_active' => 'boolean',
        'stock' => 'integer',
    ];

    /**
     * Get the rewards for this product.
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include products in stock.
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('stock', '>', 0)
                ->orWhere('stock', -1); // -1 means unlimited
        });
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock === -1 || $this->stock > 0;
    }

    /**
     * Decrease stock by one.
     */
    public function decreaseStock(): bool
    {
        if ($this->stock === -1) {
            return true; // Unlimited stock
        }

        if ($this->stock > 0) {
            $this->decrement('stock');
            return true;
        }

        return false;
    }
}
