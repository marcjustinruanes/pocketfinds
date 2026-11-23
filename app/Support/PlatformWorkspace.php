<?php

namespace App\Support;

class PlatformWorkspace
{
    public static function sections(): array
    {
        $sections = [
            ['label'=>'Overview', 'icon'=>'dashboard', 'items'=>[['Overview','admin.dashboard',['admin.dashboard']]]],
            ['label'=>'Accounts', 'icon'=>'users', 'items'=>[
                ['Users','admin.users',['admin.users']],
                ['Registrations','admin.registrations',['admin.registrations']],
                ['Update requests','admin.update-requests',['admin.update-requests','admin.doc-requests*']],
            ]],
            ['label'=>'Marketplace', 'icon'=>'bag', 'items'=>[
                ['Products','admin.products',['admin.products']],
                ['Vouchers','admin.vouchers',['admin.vouchers','admin.commerce.*']],
            ]],
            ['label'=>'Compliance', 'icon'=>'shield', 'items'=>[
                ['Company vehicles','admin.vehicles',['admin.vehicles*']],
                ['Disputes & refunds','admin.complaints',['admin.complaints']],
            ]],
            ['label'=>'Finance', 'icon'=>'peso', 'items'=>[
                ['GCash payments','gcash.index',['gcash.*']],
                ['Historical commissions','admin.commission',['admin.commission']],
            ]],
            ['label'=>'Reports', 'icon'=>'chart', 'items'=>[
                ['Reports','admin.reports',['admin.reports']],
            ]],
            ['label'=>'Settings', 'icon'=>'settings', 'items'=>[
                ['General settings','admin.settings',['admin.settings']],
                ['Policies','admin.policies',['admin.policies']],
                ['FAQs','admin.faqs',['admin.faqs*']],
            ]],
        ];
        if (config('features.admin_ai')) $sections[5]['items'][] = ['AI workspace','admin.ai',['admin.ai*']];
        if (app(\App\Services\CommissionLedger::class)->installed()) {
            array_splice($sections[4]['items'], 1, 0, [[ 'Commission management','commissions.index',['commissions.*'] ]]);
        }
        return $sections;
    }

    public static function active(array $section): bool
    {
        foreach ($section['items'] as $item) {
            if (request()->routeIs(...$item[2])) return true;
        }
        return false;
    }
}
