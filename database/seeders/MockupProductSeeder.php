<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MockupProductSeeder extends Seeder
{
    /**
     * Imports the full 347-item catalog (real product names, prices,
     * sizes/stock and specs) from database/seeders/data/mockup_products.json
     * -- the JSON export of the original design mockup's PRODUCTS array.
     *
     * The mockup's image URLs point at files.catbox.moe (the user's own
     * product photos, uploaded there only as a way to hand them to us as
     * URLs). We download each one once and store it in the app's own
     * "public" disk so the shop never depends on that external host being
     * reachable -- catbox is a temporary file host, not a CDN, so hot-
     * linking it long-term would risk broken images with no warning.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/mockup_products.json');

        if (! is_file($path)) {
            $this->command?->error("Mockup data file missing: {$path}");

            return;
        }

        $products = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $categories = Category::query()->pluck('id', 'slug');

        $total = count($products);
        $imported = 0;
        $imageCache = []; // catbox URL => stored disk path, so repeated URLs download once.
        $skipped = [];

        foreach ($products as $i => $data) {
            $categorySlug = $data['cat'] === 'trojor' ? 'trojor' : $data['cat'];
            $categoryId = $categories[$categorySlug] ?? null;

            if (! $categoryId) {
                $skipped[] = $data['sku'].' (okänd kategori: '.$data['cat'].')';

                continue;
            }

            $imagePath = $this->downloadImage($data['img'], $data['sku'], $imageCache);

            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'category_id' => $categoryId,
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name'].'-'.$data['sku']),
                    'description' => $data['desc'] ?? null,
                    'price' => $data['price'],
                    'condition' => ($data['new'] ?? false) ? 'Nyskick' : 'Mycket bra skick',
                    'image' => $imagePath,
                    'is_new' => (bool) ($data['new'] ?? false),
                    'is_active' => true,
                    'specs' => $data['specs'] ?? [],
                ]
            );

            foreach ($data['sizes'] ?? [] as $size) {
                $stock = $data['stock'][$size] ?? 1;

                $product->variants()->updateOrCreate(
                    ['size' => $size],
                    ['stock' => max(0, (int) $stock)]
                );
            }

            $imported++;

            if ($imported % 25 === 0 || $imported === $total) {
                $this->command?->getOutput()->write("\r  Importerat {$imported}/{$total} varor...");
            }
        }

        $this->command?->getOutput()->writeln('');

        if ($skipped) {
            $this->command?->warn('Hoppade över '.count($skipped).' rader: '.implode(', ', array_slice($skipped, 0, 10)));
        }
    }

    /**
     * Downloads $url once per unique URL (mockup data reuses a handful of
     * images across variant SKUs) into storage/app/public/products and
     * returns the disk-relative path Product::image expects. Returns null
     * -- leaving the product without an image rather than failing the
     * whole import -- if the download doesn't succeed.
     */
    private function downloadImage(string $url, string $sku, array &$cache): ?string
    {
        if (isset($cache[$url])) {
            return $cache[$url];
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $diskPath = 'products/'.Str::slug($sku).'.'.$extension;

        if (Storage::disk('public')->exists($diskPath)) {
            return $cache[$url] = $diskPath;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0 (compatible; NSVOImport/1.0)\r\n",
            ],
        ]);

        try {
            $contents = @file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            $contents = false;
        }

        if ($contents === false) {
            Log::warning("MockupProductSeeder: failed to download image for {$sku}: {$url}");

            return $cache[$url] = null;
        }

        Storage::disk('public')->put($diskPath, $contents);

        return $cache[$url] = $diskPath;
    }
}
