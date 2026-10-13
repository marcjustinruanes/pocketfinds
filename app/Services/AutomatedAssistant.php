<?php

namespace App\Services;

class AutomatedAssistant
{
    public function recognizesBuyerQuestion(string $message): bool
    {
        // Complex comparisons/recommendations belong to AI rather than a broad keyword rule.
        if ($this->matches($message, 'compare|comparison|difference|versus|vs|recommend|better|best|suggest|why|paghambingin')) return false;
        return $this->matches($message, 'cancel|cancellation|kansela|order|orders|tracking|track|status|nasaan|shipping|delivery|deliver|padala|payment|payments|pay|cod|bayad|return|returns|refund|refunds|policy|policies|terms|balik|support|human|seller|contact|tulong|product|products|price|prices|stock|available|browse|under|below|budget|gift|pet|baby|beauty|electronics|apparel|gadget|hi|hello|hey|menu|start|kumusta');
    }

    private function matches(string $message, string $words): bool
    {
        return (bool) preg_match('/\b(?:'.$words.')\b/iu', $message);
    }

    public function buyerReply(string $message, array $context): array
    {
        $reply = 'Choose a topic below: products, orders, shipping, payment methods or returns. For other questions, choose AI or contact human support.';
        $products = []; $pages = ['products'];
        if ($this->matches($message, 'cancel|cancellation|kansela')) {
            $reply = 'Open Orders to check whether cancellation is available for your order. This chatbot cannot cancel it. If the option is unavailable, contact your seller or support.';
            $pages = ['orders', 'messages'];
        } elseif ($this->matches($message, 'order|orders|tracking|track|status|nasaan')) {
            $pages = ['orders'];
            if (!($context['signed_in_buyer'] ?? false)) $reply = 'Please sign in to view your orders. Guest chat cannot access private order information.';
            elseif (!($context['orders_shared'] ?? false)) $reply = 'Enable "Include my recent order statuses" to see them here, or open Orders. In automated mode, these statuses stay within PocketFinds.';
            elseif (empty($context['recent_orders'])) $reply = 'No recent orders were found for your account.';
            else $reply = "Your recent order statuses:\n".collect($context['recent_orders'])->take(5)->map(fn ($order) => $order['number'].': '.str_replace('_', ' ', $order['status']).' ('.$order['placed_on'].')')->implode("\n")."\nThese are recorded statuses, not a guaranteed delivery date.";
        } elseif ($this->matches($message, 'shipping|delivery|deliver|padala')) {
            $reply = $context['shipping_note'] ?? 'Check the shipping fee at checkout. For delivery questions, contact your seller or support.';
            $pages = ['cart', 'messages'];
        } elseif ($this->matches($message, 'payment|payments|pay|cod|bayad')) {
            $methods = $context['active_payment_methods'] ?? [];
            $reply = $methods ? 'Currently enabled payment methods: '.implode(', ', $methods).'. Check checkout for available options. Never share PINs, OTPs or card details here.' : 'No active payment methods were supplied. Check checkout or contact support; I cannot confirm an available method.';
            $pages = ['cart'];
        } elseif ($this->matches($message, 'return|returns|refund|refunds|policy|policies|terms|balik')) {
            $policies = $context['policies'] ?? [];
            $reply = $policies ? "Published buyer-policy excerpts:\n".collect($policies)->take(2)->map(fn ($policy) => ($policy['title'] ?? 'Buyer policy').': '.mb_substr($policy['excerpt'], 0, 800))->implode("\n\n")."\nThese excerpts may be incomplete. Contact support to confirm exceptions or request a return; nothing has been submitted." : 'No published buyer-policy excerpt is available. Contact human support or your seller to confirm return/refund terms. I cannot promise a refund.';
            $pages = ['messages'];
        } elseif ($this->matches($message, 'support|human|seller|contact|tulong')) {
            $reply = 'Use Contact support to send your issue to the PocketFinds team, or message your seller. Nothing is sent automatically; you will review and submit the message yourself.';
            $pages = ['messages'];
        } elseif ($this->matches($message, 'product|products|price|prices|stock|available|browse|under|below|budget|gift|pet|baby|beauty|electronics|apparel|gadget')) {
            $budget = null;
            if (preg_match('/\b(?:under|below|budget|up to)\s*(?:php\s*|pesos\s*|\x{20B1}\s*)?([\d,]+(?:\.\d{1,2})?)/iu', $message, $match)) $budget = (float) str_replace(',', '', $match[1]);
            $products = collect($context['products'] ?? [])->filter(fn ($product) => $product['stock'] > 0 && ($budget === null || $product['price'] <= $budget))->take(3)
                ->map(fn ($product) => array_intersect_key($product, array_flip(['name', 'price', 'url'])))->values()->all();
            $reply = $products ? 'Here are in-stock products from the current catalog snapshot'.($budget !== null ? ' at or below PHP '.number_format($budget, 2) : '').'. Prices exclude shipping. Open a product for its options and details.' : 'No in-stock products were found in this limited snapshot'.($budget !== null ? ' within that budget' : '').'. Browse Products or try a different product/category and budget.';
        } elseif ($this->matches($message, 'hi|hello|hey|menu|start|kumusta')) {
            $reply = 'Hi! Choose a topic below. I can show recorded shopping information without using AI.';
        }
        return ['engine' => 'automated', 'reply' => $reply, 'products' => $products,
            'links' => collect($context['pages'] ?? [])->only($pages)->values()->all(),
            'suggestions' => [
                ['label' => 'Products', 'message' => 'Browse products'], ['label' => 'My orders', 'message' => 'My order status'],
                ['label' => 'Shipping', 'message' => 'Shipping fees'], ['label' => 'Payments', 'message' => 'Payment methods'],
                ['label' => 'Returns', 'message' => 'Returns policy'],
            ]];
    }

    public function adminReply(string $message, array $context): array
    {
        $data = $context['data']; $links = []; $findings = [];
        $reply = 'Choose a topic: sales, low stock, best-selling products, pending reviews, complaints or reports. For an open-ended question, switch to AI. No changes are made by this chatbot.';
        $days = $data['period']['days'] ?? 30;
        if ($this->matches($message, 'low|stock|inventory|restock')) {
            $reply = ($data['low_stock_count'] ?? 0).' active products have stock at or below '.($data['low_stock_threshold'] ?? 5).' units.';
            foreach ($data['low_stock_examples'] ?? [] as $product) $findings[] = ['title' => $product['name'], 'evidence' => 'Recorded stock: '.$product['stock'].' units.', 'next_step' => 'Review inventory and supplier lead times before restocking.'];
            $links[] = ['label' => 'Inventory insights', 'url' => route('admin.ai', ['mode' => 'inventory'])];
        } elseif ($this->matches($message, 'best|popular|selling|bestseller')) {
            $rows = $data['business_insights']['product_sales'] ?? [];
            $reply = $rows ? 'Best-selling products by delivered/completed units in the selected '.$days.'-day period:' : 'No delivered/completed product sales were recorded in the selected period.';
            foreach ($rows as $row) $findings[] = ['title' => $row['name'], 'evidence' => $row['units'].' units; historical item value PHP '.number_format($row['item_value_before_order_discounts'], 2).'.', 'next_step' => 'Item value excludes shipping and order-level discounts; it is not profit.'];
        } elseif ($this->matches($message, 'shop|shops')) {
            $rows = $data['top_shops_this_month'] ?? [];
            $reply = $rows ? 'Top shops by delivered/completed order value this calendar month ('.($data['current_month'] ?? '').'):' : 'No delivered/completed shop sales were recorded this calendar month.';
            foreach ($rows as $row) $findings[] = ['title' => $row['shop'], 'evidence' => 'PHP '.number_format($row['order_value'], 2).' across '.$row['orders'].' orders.', 'next_step' => 'Order value is not net profit; refunds are not deducted.'];
        } elseif ($this->matches($message, 'sale|sales|revenue|order|orders|performance')) {
            $reply = 'Selected '.$days.'-day period: '.($data['period_order_count'] ?? 0).' placed orders, '.($data['period_delivered_completed_order_count'] ?? 0).' delivered/completed orders, '.($data['period_cancelled_order_count'] ?? 0).' cancelled orders. Delivered/completed order value: PHP '.number_format($data['period_delivered_completed_order_value'] ?? 0, 2).'. This is not net profit; refunds are not deducted.';
        } elseif ($this->matches($message, 'pending|approval|approvals|review|registration|registrations|seller|sellers|account|accounts')) {
            $reply = 'Pending seller applications: '.($data['pending_seller_applications'] ?? 0).'. Pending products: '.($data['pending_products'] ?? 0).'. Review documents and listings manually; nothing has been approved or rejected.';
            $links = [['label' => 'Seller applications', 'url' => route('admin.registrations')], ['label' => 'Product reviews', 'url' => route('admin.products')]];
        } elseif ($this->matches($message, 'complaint|complaints|dispute|disputes')) {
            $reply = 'Open/escalated complaints: '.($data['open_complaints'] ?? 0).'. Open the review queue to read the evidence and respond manually.';
            $links[] = ['label' => 'Complaints', 'url' => route('admin.complaints')];
        } elseif ($this->matches($message, 'report|reports|export|download')) {
            $reply = 'Download a data report for the selected '.$days.'-day period, or open Reports. Exporting does not publish anything or use AI.';
            $links = [['label' => 'Download report', 'url' => route('admin.ai.export', ['days' => $days])], ['label' => 'Reports', 'url' => route('admin.reports')]];
        } elseif ($this->matches($message, 'hi|hello|hey|menu|start|kumusta')) $reply = 'Hello! Choose a dashboard topic below for an automated, read-only response.';
        if ($faq = app(FaqKnowledge::class)->match('admin', $message)) {
            $reply = $faq->answer; $findings = [];
            $links = [['label' => $faq->question, 'url' => route('admin.faqs', ['audience' => 'admin', 'edit' => $faq->id])]];
        }
        return ['engine' => 'automated', 'summary' => $reply, 'risk' => 'not_applicable', 'findings' => array_slice($findings, 0, 5),
            'limitations' => ['Automated, rule-based response. No AI provider was contacted. Counts reflect the current database snapshot.'],
            'draft' => ['title' => '', 'body' => '', 'audience' => 'all'], 'sources' => $links,
            'snapshot' => array_intersect_key($data, array_flip(['mode', 'generated_at', 'period', 'currency'])),
            'suggestions' => [
                ['label' => 'Sales', 'message' => 'Sales summary'], ['label' => 'Low stock', 'message' => 'Low stock products'],
                ['label' => 'Best sellers', 'message' => 'Best-selling products'], ['label' => 'Pending reviews', 'message' => 'Pending approvals'],
                ['label' => 'Complaints', 'message' => 'Open complaints'], ['label' => 'Reports', 'message' => 'Download reports'],
            ]];
    }
}
