<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCategory extends Model
{
    protected $fillable = ['seller_id', 'parent_id', 'name', 'name_key', 'position', 'is_visible'];

    protected $casts = ['is_visible' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'store_category_products');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true)->where(fn ($q) => $q->whereNull('parent_id')->orWhereHas('parent', fn ($p) => $p->where('is_visible', true)));
    }
}
