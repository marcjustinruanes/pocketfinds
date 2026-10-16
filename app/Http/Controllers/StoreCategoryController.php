<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = StoreCategory::where('seller_id', $request->user()->id)->with(['products', 'children'])->orderBy('position')->orderBy('id')->get();
        $products = Product::where('seller_id', $request->user()->id)->orderBy('name')->get();

        return view('seller.store-categories', compact('categories', 'products'));
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $data = $this->validateCategory($request);
            $data['position'] = (int) StoreCategory::where('seller_id', $request->user()->id)->where('parent_id', $data['parent_id'] ?? null)->max('position') + 1;
            StoreCategory::create($data + ['seller_id' => $request->user()->id]);
        });

        return back()->with('success', 'Store category created.');
    }

    public function update(Request $request, StoreCategory $storeCategory)
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);
        DB::transaction(function () use ($request, $storeCategory) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $storeCategory->update($this->validateCategory($request, $storeCategory));
        });

        return back()->with('success', 'Store category updated.');
    }

    private function validateCategory(Request $request, ?StoreCategory $category = null): array
    {
        $request->merge(['name' => trim((string) $request->name)]);
        $data = $request->validate(['name' => ['required', 'string', 'min:1', 'max:50'], 'parent_id' => ['nullable', 'integer'], 'is_visible' => ['sometimes', 'boolean']]);
        $data['name_key'] = mb_strtolower($data['name']);
        $duplicate = StoreCategory::where('seller_id', $request->user()->id)->where('name_key', $data['name_key'])->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'This store category already exists.']);
        }
        if (! empty($data['parent_id'])) {
            $parent = StoreCategory::where('seller_id', $request->user()->id)->find($data['parent_id']);
            if (! $parent || $parent->parent_id || $parent->id === $category?->id || $category?->children()->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Store categories support a maximum of 2 levels. Choose a top-level category in your store.']);
            }
        }

        return $data;
    }

    public function assign(Request $request, StoreCategory $storeCategory)
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);
        if ($request->has('empty_selection') && ! $request->filled('product_ids')) {
            $request->merge(['product_ids' => []]);
        }
        $data = $request->validate(['product_ids' => ['present', 'array'], 'product_ids.*' => ['required', 'string', 'distinct']]);
        DB::transaction(function () use ($request, $storeCategory, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $products = Product::where('seller_id', $request->user()->id)->whereIn('id', $data['product_ids'])->orderBy('id')->get();
            if ($products->count() !== count($data['product_ids'])) {
                throw ValidationException::withMessages(['product_ids' => 'Choose products from your own store.']);
            }
            foreach ($products as $product) {
                $count = DB::table('store_category_products')->where('product_id', $product->id)->where('store_category_id', '!=', $storeCategory->id)->count();
                if ($count >= 3) {
                    throw ValidationException::withMessages(['product_ids' => 'A product can belong to at most 3 store categories.']);
                }
            }
            $storeCategory->products()->sync($data['product_ids']);
        });

        return back()->with('success', 'Category products updated.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $categories = StoreCategory::where('seller_id', $request->user()->id)->orderBy('id')->get();
            if ($categories->pluck('id')->sort()->values()->all() !== collect($data['ids'])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw ValidationException::withMessages(['ids' => 'Include each category in your store exactly once.']);
            }
            foreach ($data['ids'] as $position => $id) {
                StoreCategory::whereKey($id)->update(['position' => $position]);
            }
        });

        return back()->with('success', 'Category order updated.');
    }

    public function destroy(Request $request, StoreCategory $storeCategory)
    {
        abort_unless($storeCategory->seller_id === $request->user()->id, 403);
        $request->validate(['confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $storeCategory) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $ids = $storeCategory->children()->pluck('id')->push($storeCategory->id);
            DB::table('store_category_products')->whereIn('store_category_id', $ids)->delete();
            StoreCategory::whereIn('id', $ids)->whereNotNull('parent_id')->delete();
            $storeCategory->delete();
        });

        return back()->with('success', 'Category removed. Products were kept and can be found under Uncategorized when they have no other store category.');
    }
}
