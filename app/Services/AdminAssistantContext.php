<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminAssistantContext
{
    public function build(array $input): array
    {
        $mode = $input['mode'];
        $context = ['mode' => $mode, 'generated_at' => now()->toIso8601String(), 'currency' => 'PHP', 'sources' => []];
        $image = null;
        if (in_array($mode, ['dashboard', 'chat', 'reports', 'analytics', 'inventory', 'anomalies'], true)) {
            $context += $this->metrics((int) ($input['days'] ?? 30));
            $context['business_insights'] = app(CommerceInsights::class)->snapshot((int) ($input['days'] ?? 30));
            $context['sources'][] = ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => route('admin.dashboard')];
            foreach (collect($context['business_insights']['alerts'])->pluck('order_ids')->filter()->flatten()->unique()->take(8) as $orderId) {
                $context['sources'][] = ['id' => 'order:'.$orderId, 'label' => 'Review order '.$orderId, 'url' => route('admin.ai.order', $orderId)];
            }
        }
        if ($mode === 'reports') {
            $context['open_case_count'] = Complaint::whereIn('status', ['open', 'escalated'])->count();
            $cases = Complaint::whereIn('status', ['open', 'escalated'])->orderBy('created_at')->limit(12)->get();
            $context['oldest_open_cases'] = $cases->map(fn ($case) => [
                'source_id' => 'case:'.$case->id, 'type' => $case->complaint_type,
                'subject' => $this->text($case->subject, 150), 'description' => $this->text($case->description, 450),
                'status' => $case->status, 'opened_on' => $case->created_at?->toDateString(),
            ])->all();
            foreach ($cases as $case) {
                $context['sources'][] = ['id' => 'case:'.$case->id, 'label' => $this->text($case->subject ?: 'Complaint', 80), 'url' => route('admin.complaints')];
            }
        }
        if ($mode === 'seller') {
            $seller = User::where('account_type', 'seller')->where('is_admin', false)->findOrFail($input['seller_id']);
            $context['seller'] = [
                'source_id' => 'seller:'.$seller->id, 'shop' => $this->text($seller->business_name ?: 'Unnamed shop', 100),
                'status' => $seller->status, 'category' => $seller->category?->name,
                'submitted_on' => $seller->created_at?->toDateString(),
                'information_present' => [
                    'business_name' => filled($seller->business_name), 'email' => filled($seller->email),
                    'contact_number' => filled($seller->contact_no), 'category' => filled($seller->category_id) || filled($seller->category_other),
                    'address' => filled($seller->province) && filled($seller->municipality) && filled($seller->barangay),
                    'identity_document' => filled($seller->id_file), 'selfie' => filled($seller->selfie_file),
                    'business_permit' => filled($seller->business_permit_file),
                ],
            ];
            $context['sources'][] = ['id' => 'seller:'.$seller->id, 'label' => 'Seller application', 'url' => route('admin.registrations')];
        }
        if (in_array($mode, ['product', 'product_copy'], true)) {
            $product = Product::with('category:id,name')->findOrFail($input['product_id']);
            $context['product'] = [
                'source_id' => 'product:'.$product->id, 'name' => $this->text($product->name, 200),
                'description' => $this->text($product->description, 1600), 'category' => $product->category?->name,
                'price' => (float) $product->price, 'discount_price' => $product->discount_price === null ? null : (float) $product->discount_price,
                'stock' => $product->total_stock, 'status' => $product->status, 'condition' => $product->condition,
            ];
            $matches = Product::where('id', '!=', $product->id)->where(function ($query) use ($product) {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($product->name))]);
                if (filled($product->sku)) $query->orWhere('sku', $product->sku);
            })->limit(6)->get(['id', 'name', 'seller_id']);
            $context['possible_duplicate_matches'] = $matches->map(fn ($match) => [
                'name' => $this->text($match->name, 100), 'same_seller' => $match->seller_id === $product->seller_id,
            ])->all();
            $context['sources'][] = ['id' => 'product:'.$product->id, 'label' => $this->text($product->name, 100), 'url' => route('admin.products')];
            if ($input['include_photo'] ?? false) $image = $this->photo($product);
            $context['photos_assessed'] = $image ? 1 : 0;
            $context['photo_note'] = $image ? 'Only the first available local product photo is supplied.'
                : (($input['include_photo'] ?? false) ? 'A supported local photo was unavailable. Text-only screening.' : 'Photos not shared. Text-only screening.');
            $context['seller_policy_excerpt'] = $this->text(Policy::where('account_type', 'seller')->whereNull('company_name')->value('content'), 1200);
            if ($mode === 'product_copy') $context['allowed_categories'] = \App\Models\Category::orderBy('name')->pluck('name')->all();
        }
        if ($mode === 'draft') {
            $context['draft_brief'] = $this->text($input['message'] ?? '', 1500);
            $context['audience'] = $input['audience'] ?? 'all';
        }
        return ['data' => $context, 'image' => $image];
    }

    private function metrics(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now();
        $orders = Order::whereBetween('created_at', [$start, $end]);
        $completed = (clone $orders)->whereIn('status', ['delivered', 'completed']);
        $monthStart = now()->startOfMonth();
        $top = Order::whereBetween('created_at', [$monthStart, $end])->whereIn('status', ['delivered', 'completed'])
            ->selectRaw('seller_id, SUM(total) AS order_value, COUNT(*) AS order_count')
            ->groupBy('seller_id')->orderByDesc('order_value')->limit(8)->get();
        $shops = User::whereIn('id', $top->pluck('seller_id'))->pluck('business_name', 'id');
        $lowCount = 0;
        $low = [];
        Product::where('status', 'active')->select(['id', 'name', 'stock', 'variations'])->chunkById(200, function ($products) use (&$lowCount, &$low) {
            foreach ($products as $product) {
                if ($product->total_stock <= 5) {
                    $lowCount++;
                    $low[] = ['name' => $this->text($product->name, 100), 'stock' => $product->total_stock];
                    usort($low, fn ($a, $b) => $a['stock'] <=> $b['stock']);
                    $low = array_slice($low, 0, 8);
                }
            }
        });
        return [
            'period' => ['from' => $start->toDateString(), 'through' => $end->toDateString(), 'days' => $days],
            'period_order_count' => (clone $orders)->count(),
            'period_delivered_completed_order_count' => (clone $completed)->count(),
            'period_delivered_completed_order_value' => (float) (clone $completed)->sum('total'),
            'period_cancelled_order_count' => (clone $orders)->where('status', 'cancelled')->count(),
            'sales_definition' => 'Sum of order total for delivered/completed orders, grouped by order placement date. Not net profit or platform revenue; refunds are not deducted.',
            'pending_seller_applications' => User::where('account_type', 'seller')->where('is_admin', false)->where('status', 'pending')->count(),
            'pending_products' => Product::where('status', 'pending')->count(),
            'open_complaints' => Complaint::whereIn('status', ['open', 'escalated'])->count(),
            'low_stock_threshold' => 5, 'low_stock_count' => $lowCount, 'low_stock_examples' => $low,
            'recent_product_submissions' => Product::latest()->limit(5)->get(['name', 'status', 'created_at'])->map(fn ($product) => [
                'name' => $this->text($product->name, 100), 'status' => $product->status, 'submitted_on' => $product->created_at?->toDateString(),
            ])->all(),
            'current_month' => $monthStart->format('Y-m'),
            'top_shops_this_month' => $top->map(fn ($row) => [
                'shop' => $this->text(filled($shops[$row->seller_id] ?? null) ? $shops[$row->seller_id] : 'Shop #'.$row->seller_id, 100),
                'order_value' => (float) $row->order_value, 'orders' => (int) $row->order_count,
            ])->all(),
        ];
    }

    private function text(?string $text, int $limit): string
    {
        $text = strip_tags($text ?? '');
        $text = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email removed]', $text);
        $text = preg_replace('/(?<!\w)(?:\+?\d[\d\s().-]{6,}\d)(?!\w)/', '[number removed]', $text);
        return Str::limit($text, $limit);
    }

    private function photo(Product $product): ?string
    {
        $file = $this->photoFile($product);
        if (!$file) return null;
        $size = getimagesize($file);
        return 'data:'.$size['mime'].';base64,'.base64_encode(file_get_contents($file));
    }

    public function photoFile(Product $product): ?string
    {
        $paths = array_filter(array_merge([$product->image], $product->images ?? []), 'is_string');
        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        if (!$root) return null;
        foreach ($paths as $path) {
            if (preg_match('#^(?:[a-z]+:|[/\\\\])#i', $path)) continue;
            $file = realpath($disk->path($path));
            if (!$file || !str_starts_with(strtolower(str_replace('\\', '/', $file)), strtolower(str_replace('\\', '/', $root)).'/')) continue;
            if (!is_file($file) || filesize($file) > 4 * 1024 * 1024) continue;
            $size = @getimagesize($file);
            if (!$size || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || max($size[0], $size[1]) > 4096) continue;
            return $file;
        }
        return null;
    }
}
