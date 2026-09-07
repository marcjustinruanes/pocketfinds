<?php

namespace App\Models;

use Illuminate\Support\Collection;

/**
 * Not an Eloquent model — there's no `logistics_companies` table. A "company"
 * is just a `users.business_name` value shared by every logistics/rider staff
 * row that joined it, plus a `users.company_logo` (copied onto each member's
 * row for simplicity) and its own row in `policies` (type='logistics_company_terms',
 * keyed by company_name). These helpers keep that lookup logic in one place.
 */
class LogisticsCompany
{
    /** Every company at least one approved logistics staff member has joined — what the registration picker offers. */
    public static function approved(): Collection
    {
        return User::where('is_logistics', true)->where('status', 'approved')
            ->whereNotNull('business_name')->where('business_name', '!=', '')
            ->selectRaw('business_name, max(company_logo) as company_logo')
            ->groupBy('business_name')->orderBy('business_name')->get();
    }

    /** One company's live, admin-approved Terms & Conditions (or null if it hasn't submitted one yet). */
    public static function policyFor(string $companyName): ?Policy
    {
        return Policy::where('type', 'logistics_company_terms')->where('company_name', $companyName)->first();
    }

    /** Every existing company name (any status) — used to block "create" from colliding with one already taken. */
    public static function nameTaken(string $name): bool
    {
        return User::where('is_logistics', true)->where('business_name', $name)->exists();
    }

    /** Admin's company-review list: name, logo, staff count, and its Terms & Conditions row. */
    public static function summaries(): Collection
    {
        $companies = User::where('is_logistics', true)
            ->whereNotNull('business_name')->where('business_name', '!=', '')
            ->selectRaw('business_name, count(*) as staff_count, max(company_logo) as company_logo')
            ->groupBy('business_name')->orderBy('business_name')->get();

        $policies = Policy::where('type', 'logistics_company_terms')
            ->whereIn('company_name', $companies->pluck('business_name'))
            ->with('submittedBy')->get()->keyBy('company_name');

        return $companies->map(fn ($c) => (object) [
            'name'        => $c->business_name,
            'logo_path'   => $c->company_logo,
            'staff_count' => $c->staff_count,
            'policy'      => $policies->get($c->business_name),
        ]);
    }
}
