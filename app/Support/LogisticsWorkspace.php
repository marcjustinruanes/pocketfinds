<?php

namespace App\Support;

use Illuminate\Support\Str;

final class LogisticsWorkspace
{
    public static function sections(): array
    {
        if (auth()->user()?->isHubStaff()) return [
            'overview'=>['label'=>'My hub','icon'=>'dashboard','links'=>[
                ['My hub','logistics.dashboard',['logistics.dashboard']],
            ]],
            'operations'=>['label'=>'Operations','icon'=>'box','links'=>[
                ['Scan parcels','logistics.scan',['logistics.scan']],
                ['Hub transfers','logistics.transfers',['logistics.transfers*']],
                ['Delivery riders','logistics.assignments',['logistics.assignments*']],
                ['Live monitor','logistics.monitor',['logistics.monitor']],
                ['Issues','logistics.issues',['logistics.issues*']],
                ['Delivery history','logistics.history',['logistics.history']],
            ]],
            'resources'=>['label'=>'Vehicles','icon'=>'truck','links'=>[
                ['Hub vehicles','logistics.hub.vehicles',['logistics.hub.vehicles*']],
            ]],
            'finance'=>['label'=>'Staff & finance','icon'=>'peso','links'=>[
                ['Cash desk','logistics.cash-desk',['logistics.cash-desk*']],
                ['My attendance','logistics.my-attendance',['logistics.my-attendance*']],
                ['My salary','logistics.my-salary',['logistics.my-salary*']],
            ]],
        ];
        $sections = [
            'overview'=>['label'=>'Overview','icon'=>'dashboard','links'=>[
                ['Overview','logistics.dashboard',['logistics.dashboard']],
            ]],
            'operations'=>['label'=>'Operations','icon'=>'box','links'=>[
                ['Pickup requests','logistics.requests',['logistics.requests*','logistics.hub-requests*']],
                ['Assignments','logistics.assignments',['logistics.assignments*']],
                ['Hub transfers','logistics.transfers',['logistics.transfers*']],
                ['Live monitor','logistics.monitor',['logistics.monitor']],
                ['Issues','logistics.issues',['logistics.issues*']],
                ['Delivery history','logistics.history',['logistics.history']],
            ]],
            'team'=>['label'=>'Team','icon'=>'users','links'=>[
                ['Riders','logistics.riders',['logistics.riders*','logistics.accounts*']],
                ['Hub staff','logistics.staff',['logistics.staff','logistics.attendance*']],
            ]],
            'resources'=>['label'=>'Resources','icon'=>'pin','links'=>[
                ['Coverage hubs','logistics.hubs',['logistics.hubs*']],
                ['Vehicle fleet','logistics.vehicles',['logistics.vehicles*']],
            ]],
            'finance'=>['label'=>'Finance','icon'=>'peso','links'=>[
                ['Overview & settings','logistics.finance',['logistics.finance','logistics.finance.shipping.*']],
                ['Cash desk','logistics.cash-desk',['logistics.cash-desk*']],
                ['Seller settlements','logistics.finance.payouts',['logistics.finance.payouts*','logistics.finance.treasury.*']],
                ['Rider payouts','logistics.finance.riders',['logistics.finance.riders*']],
                ['Staff salaries','logistics.finance.staff',['logistics.finance.staff*']],
                ['Reconciliation','logistics.finance.reconciliation',['logistics.finance.reconciliation*']],
            ]],
            'reports'=>['label'=>'Reports','icon'=>'chart','links'=>[
                ['Reports','logistics.reports',['logistics.reports']],
                ['Activity log','logistics.activity',['logistics.activity']],
            ]],
        ];
        if (app(\App\Services\CommissionLedger::class)->installed()) {
            $sections['finance']['links'][]=['Platform commissions','commissions.index',['commissions.*']];
        }
        return $sections;
    }

    public static function current(?string $route): ?string
    {
        foreach (self::sections() as $key=>$section) {
            foreach ($section['links'] as [,,$patterns]) {
                if (Str::is($patterns, $route ?? '')) return $key;
            }
        }
        return null;
    }
}
