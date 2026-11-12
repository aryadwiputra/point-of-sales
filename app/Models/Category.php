<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToTenant, HasFactory;

    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id', 'image', 'name', 'description',
    ];

    /**
     * products
     *
     * @return void
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function pricingRules()
    {
        return $this->hasMany(PricingRule::class);
    }

    /**
     * image
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('/storage/category/'.$value) : null,
        );
    }
}
