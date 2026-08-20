<?php

namespace App\Http\Controllers;

class GuestController extends Controller
{
    private function products(): array
    {
        return [
            1  => ['id'=>1,  'name'=>'Wireless Earbuds Pro',      'seller'=>'TechHub PH',  'seller_slug'=>'techhub-ph',   'price'=>799,  'old_price'=>999,  'badge'=>'20% OFF','rating'=>4.8,'sold'=>1200,'cat'=>'Electronics',  'img'=>'headphones','desc'=>'Premium wireless earbuds with active noise cancellation, 30-hour battery life, and IPX5 water resistance.','specs'=>[['Brand','SoundCore'],['Connectivity','Bluetooth 5.3'],['Battery','30 hrs'],['Water Rating','IPX5'],['Colors','Black, White'],['Warranty','1 year']],'variants'=>['color'=>['Black','White'],'size'=>[]],'reviews'=>[['name'=>'Maria S.','rating'=>5,'date'=>'Dec 12, 2024','text'=>'Amazing sound quality! The noise cancellation is top-notch.'],['name'=>'Juan D.','rating'=>4,'date'=>'Dec 8, 2024','text'=>'Great earbuds for the price.'],['name'=>'Ana R.','rating'=>5,'date'=>'Nov 30, 2024','text'=>'Best purchase this year!']]],
            2  => ['id'=>2,  'name'=>'Minimal Everyday Backpack', 'seller'=>'UrbanCarry',  'seller_slug'=>'urbancarry',    'price'=>549,  'old_price'=>699,  'badge'=>'HOT',    'rating'=>4.7,'sold'=>856, 'cat'=>'Fashion',      'img'=>'bag',       'desc'=>'Sleek 20L backpack made from water-resistant nylon with padded laptop sleeve up to 15\".','specs'=>[['Capacity','20L'],['Material','Water-resistant nylon'],['Laptop Fit','Up to 15"'],['Dimensions','45×30×15 cm'],['Colors','Black, Olive, Navy'],['Warranty','6 months']],'variants'=>['color'=>['Black','Olive','Navy'],'size'=>[]],'reviews'=>[['name'=>'Carlo M.','rating'=>5,'date'=>'Dec 10, 2024','text'=>'Perfect size for daily use.'],['name'=>'Lea T.','rating'=>4,'date'=>'Dec 1, 2024','text'=>'Good quality material.']]],
            3  => ['id'=>3,  'name'=>'Smart Watch Series 5',      'seller'=>'TechHub PH',  'seller_slug'=>'techhub-ph',   'price'=>1299, 'old_price'=>1499, 'badge'=>'15% OFF','rating'=>4.9,'sold'=>2400,'cat'=>'Electronics',  'img'=>'phone',     'desc'=>'Feature-packed smartwatch with health monitoring, GPS, 7-day battery, and 50+ workout modes.','specs'=>[['Display','1.4" AMOLED'],['Battery','7 days'],['GPS','Built-in'],['Water Rating','5ATM'],['Compatibility','iOS & Android'],['Warranty','1 year']],'variants'=>['color'=>['Black','Silver','Rose Gold'],'size'=>[]],'reviews'=>[['name'=>'Ben A.','rating'=>5,'date'=>'Dec 14, 2024','text'=>'Incredible watch for the price.'],['name'=>'Sofia L.','rating'=>5,'date'=>'Dec 9, 2024','text'=>'Love the health tracking features.'],['name'=>'Mark P.','rating'=>4,'date'=>'Dec 3, 2024','text'=>'Great value.']]],
            4  => ['id'=>4,  'name'=>'Lightweight Running Shoes', 'seller'=>'StepUp Store','seller_slug'=>'stepup-store',  'price'=>899,  'old_price'=>1199, 'badge'=>'SALE',   'rating'=>4.6,'sold'=>648, 'cat'=>'Fashion',      'img'=>'bag',       'desc'=>'Ultra-lightweight running shoes with responsive foam cushioning and breathable mesh upper.','specs'=>[['Weight','220g per shoe'],['Upper','Breathable mesh'],['Sole','Responsive foam'],['Sizes','36–45'],['Colors','White, Black, Blue'],['Warranty','3 months']],'variants'=>['color'=>['White','Black','Blue'],'size'=>['36','37','38','39','40','41','42','43','44','45']],'reviews'=>[['name'=>'Rina C.','rating'=>5,'date'=>'Dec 11, 2024','text'=>'So comfortable!'],['name'=>'Paolo G.','rating'=>4,'date'=>'Nov 28, 2024','text'=>'Good shoes, true to size.']]],
            5  => ['id'=>5,  'name'=>'LED Desk Lamp',             'seller'=>'HomeGlow',    'seller_slug'=>'homeglow',      'price'=>399,  'old_price'=>499,  'badge'=>'NEW',    'rating'=>4.8,'sold'=>731, 'cat'=>'Home & Living','img'=>'sparkle',   'desc'=>'Adjustable LED desk lamp with 5 color temperatures, 10 brightness levels, and USB charging port.','specs'=>[['Power','12W LED'],['Color Temp','2700K–6500K'],['Brightness','10 levels'],['USB Port','5V/1A'],['Arm','Fully adjustable'],['Warranty','1 year']],'variants'=>['color'=>['White','Black'],'size'=>[]],'reviews'=>[['name'=>'Trisha V.','rating'=>5,'date'=>'Dec 13, 2024','text'=>'Perfect for studying.'],['name'=>'Kevin B.','rating'=>5,'date'=>'Dec 5, 2024','text'=>'Great lamp, very bright.']]],
            6  => ['id'=>6,  'name'=>'Premium Phone Case',        'seller'=>'TechHub PH',  'seller_slug'=>'techhub-ph',   'price'=>249,  'old_price'=>null, 'badge'=>null,     'rating'=>4.8,'sold'=>2000,'cat'=>'Electronics',  'img'=>'phone',     'desc'=>'Military-grade drop protection with a slim profile. Compatible with all major phone models.','specs'=>[['Protection','MIL-STD-810G'],['Profile','Slim 1.2mm'],['Material','TPU + Polycarbonate'],['Colors','8 options'],['Compatibility','All major models'],['Warranty','6 months']],'variants'=>['color'=>['Black','Clear','Navy','Red','Green','Pink','Yellow','Purple'],'size'=>[]],'reviews'=>[['name'=>'Gab N.','rating'=>5,'date'=>'Dec 15, 2024','text'=>'Dropped my phone twice, not a scratch.'],['name'=>'Mia F.','rating'=>4,'date'=>'Dec 7, 2024','text'=>'Slim and protective.']]],
            7  => ['id'=>7,  'name'=>'Insulated Coffee Tumbler',  'seller'=>'HomeGlow',    'seller_slug'=>'homeglow',      'price'=>329,  'old_price'=>null, 'badge'=>null,     'rating'=>4.7,'sold'=>945, 'cat'=>'Home & Living','img'=>'sparkle',   'desc'=>'Double-wall vacuum insulated tumbler keeps drinks hot 12 hours and cold 24 hours. BPA-free.','specs'=>[['Capacity','500ml'],['Hot','12 hours'],['Cold','24 hours'],['Material','18/8 Stainless Steel'],['BPA Free','Yes'],['Warranty','1 year']],'variants'=>['color'=>['Black','White','Pink','Blue'],'size'=>[]],'reviews'=>[['name'=>'Diane O.','rating'=>5,'date'=>'Dec 12, 2024','text'=>'My coffee stays hot until lunch!'],['name'=>'Ryan S.','rating'=>4,'date'=>'Dec 4, 2024','text'=>'Great tumbler, no leaks.']]],
            8  => ['id'=>8,  'name'=>'Mechanical Keyboard',       'seller'=>'TechHub PH',  'seller_slug'=>'techhub-ph',   'price'=>1899, 'old_price'=>null, 'badge'=>null,     'rating'=>4.9,'sold'=>512, 'cat'=>'Electronics',  'img'=>'phone',     'desc'=>'Compact TKL mechanical keyboard with hot-swappable switches, RGB backlighting, and aluminum frame.','specs'=>[['Layout','TKL (87 keys)'],['Switches','Hot-swappable'],['Backlight','Per-key RGB'],['Frame','Aluminum'],['Connection','USB-C'],['Warranty','2 years']],'variants'=>['color'=>['Space Gray','White'],'size'=>['Blue Switch','Brown Switch','Red Switch']],'reviews'=>[['name'=>'Nico A.','rating'=>5,'date'=>'Dec 14, 2024','text'=>'Best keyboard I\'ve ever owned.'],['name'=>'Jess M.','rating'=>5,'date'=>'Dec 6, 2024','text'=>'Hot-swap feature is amazing.']]],
            9  => ['id'=>9,  'name'=>'Oversized Hoodie',          'seller'=>'UrbanCarry',  'seller_slug'=>'urbancarry',    'price'=>699,  'old_price'=>null, 'badge'=>null,     'rating'=>4.8,'sold'=>1100,'cat'=>'Fashion',      'img'=>'shirt',     'desc'=>'Premium 380gsm fleece oversized hoodie. Soft, warm, and perfect for layering. Sizes XS–3XL.','specs'=>[['Weight','380gsm fleece'],['Fit','Oversized'],['Sizes','XS–3XL'],['Colors','6 neutral tones'],['Care','Machine washable'],['Warranty','3 months']],'variants'=>['color'=>['Black','White','Beige','Sage','Navy','Charcoal'],'size'=>['XS','S','M','L','XL','2XL','3XL']],'reviews'=>[['name'=>'Camille R.','rating'=>5,'date'=>'Dec 13, 2024','text'=>'So soft and cozy!'],['name'=>'Luis T.','rating'=>4,'date'=>'Dec 2, 2024','text'=>'Great hoodie.']]],
            10 => ['id'=>10, 'name'=>'Daily Skincare Set',        'seller'=>'GlowUp PH',   'seller_slug'=>'glowup-ph',    'price'=>599,  'old_price'=>null, 'badge'=>null,     'rating'=>4.6,'sold'=>782, 'cat'=>'Beauty',       'img'=>'sparkle',   'desc'=>'Complete 4-step skincare routine: cleanser, toner, serum, and moisturizer. Dermatologist-tested.','specs'=>[['Steps','4-step routine'],['Skin Type','All skin types'],['Tested','Dermatologist-approved'],['Volume','30–50ml each'],['Fragrance','Fragrance-free'],['Shelf Life','24 months']],'variants'=>['color'=>[],'size'=>[]],'reviews'=>[['name'=>'Aika P.','rating'=>5,'date'=>'Dec 11, 2024','text'=>'My skin has never looked better!'],['name'=>'Bea C.','rating'=>4,'date'=>'Dec 3, 2024','text'=>'Good set for beginners.']]],
        ];
    }

    private function shops(): array
    {
        return [
            'techhub-ph'   => ['name'=>'TechHub PH',  'initial'=>'T','rating'=>4.9,'products'=>12,'sales'=>'8.2k','joined'=>'Jan 2023','desc'=>'Your go-to store for the latest gadgets, accessories, and tech essentials. All products are authentic and come with warranty.','items'=>[1,3,6,8]],
            'urbancarry'   => ['name'=>'UrbanCarry',   'initial'=>'U','rating'=>4.8,'products'=>6, 'sales'=>'3.1k','joined'=>'Mar 2023','desc'=>'Minimalist bags, clothing, and lifestyle accessories for the modern urban commuter.','items'=>[2,9]],
            'homeglow'     => ['name'=>'HomeGlow',     'initial'=>'H','rating'=>4.8,'products'=>8, 'sales'=>'2.4k','joined'=>'Jun 2023','desc'=>'Curated home essentials and lifestyle products to make your space more comfortable and beautiful.','items'=>[5,7]],
            'stepup-store' => ['name'=>'StepUp Store', 'initial'=>'S','rating'=>4.6,'products'=>5, 'sales'=>'1.8k','joined'=>'Aug 2023','desc'=>'Quality footwear and athletic gear for every lifestyle and budget.','items'=>[4]],
            'glowup-ph'    => ['name'=>'GlowUp PH',   'initial'=>'G','rating'=>4.7,'products'=>9, 'sales'=>'2.9k','joined'=>'Feb 2023','desc'=>'Affordable, dermatologist-approved skincare and beauty products for all skin types.','items'=>[10]],
        ];
    }

    public function product($id)
    {
        $products     = $this->products();
        $product      = $products[$id] ?? abort(404);
        $shops        = $this->shops();
        $shop         = $shops[$product['seller_slug']] ?? null;
        $related      = array_filter($products, fn($p) => $p['id'] !== (int)$id && $p['cat'] === $product['cat']);
        $shopProducts = $shop ? array_filter($products, fn($p) => $p['id'] !== (int)$id && in_array($p['id'], $shop['items'])) : [];
        return view('guest.product', compact('product', 'shop', 'related', 'shopProducts'));
    }

    public function shop($slug)
    {
        $shops    = $this->shops();
        $shop     = $shops[$slug] ?? abort(404);
        $products = $this->products();
        $items    = array_filter($products, fn($p) => in_array($p['id'], $shop['items']));
        return view('guest.shop', compact('shop', 'items', 'slug'));
    }
}
