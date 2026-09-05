<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    /**
     * Common phrasings offered as quick picks in the admin -- the column
     * itself is free text, so an admin can always type something else.
     */
    public const CONDITION_PRESETS = [
        'Nyskick' => 'Nyskick',
        'Mycket bra skick' => 'Mycket bra skick',
        'Bra skick' => 'Bra skick',
        'Synligt slitage' => 'Synligt slitage',
    ];

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'sale_price',
        'condition',
        'image',
        'is_new',
        'is_active',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'is_new' => 'boolean',
            'is_active' => 'boolean',
            'specs' => 'array',
        ];
    }

    /**
     * Slug is derived automatically (name + sku, which is already unique)
     * so admins never have to think about it -- one less field to fill in
     * when adding a vara. It isn't used for any public route (only
     * category slugs are), it just needs to be a stable unique value.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->name && $product->sku) {
                $product->slug = Str::slug($product->name.'-'.$product->sku);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    public function totalStock(): int
    {
        return $this->variants->sum('stock');
    }

    /** Full public URL for the stored image, or null when none uploaded. */
    public function imageUrl(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }
}
