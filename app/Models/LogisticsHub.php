<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One province/municipality a logistics company covers — registered by the
 * company itself (via the PSGC API), never hardcoded. Every hub a company has
 * is assumed connected to every other hub that same company has, so route
 * feasibility is just "does this company have a hub in both cities."
 */
class LogisticsHub extends Model
{
    protected $fillable = ['company_name', 'province', 'municipality', 'is_regional_hub', 'is_hiring'];

    protected $casts = ['is_regional_hub' => 'boolean', 'is_hiring' => 'boolean'];

    /**
     * Company names able to fully service a seller-in-$origin / buyer-in-$destination
     * order via their own connected hub network — i.e. they have a hub in both cities
     * (or one hub covering both, when origin and destination are the same city).
     */
    public static function companiesServicing(?string $originMunicipality, ?string $destinationMunicipality): Collection
    {
        if (!$originMunicipality || !$destinationMunicipality) {
            return collect();
        }

        $origins = static::whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($originMunicipality))])
            ->pluck('company_name')->unique();

        if (mb_strtolower(trim($originMunicipality)) === mb_strtolower(trim($destinationMunicipality))) {
            return $origins->values();
        }

        $destinations = static::whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($destinationMunicipality))])
            ->pluck('company_name')->unique();

        return $origins->intersect($destinations)->values();
    }

    /** This company's hub cities — used to label a shipment's origin/destination hub. */
    public static function forCompany(string $companyName): Collection
    {
        return static::where('company_name', $companyName)->orderBy('province')->orderBy('municipality')->get();
    }

    /** True if this company has a hub in the given municipality. */
    public static function companyHasHubIn(string $companyName, ?string $municipality): bool
    {
        if (!$municipality) return false;
        return static::where('company_name', $companyName)
            ->whereRaw('LOWER(municipality) = ?', [mb_strtolower(trim($municipality))])
            ->exists();
    }

    /**
     * This company's regional (province-level) hub municipality for $province,
     * if it has any hub there at all. Falls back to whichever hub was
     * registered first in that province when none was explicitly designated
     * (e.g. registered before this existed, or the company only ever added
     * one municipality there — in which case that one IS the regional hub).
     */
    public static function regionalHubFor(string $companyName, string $province): ?string
    {
        $inProvince = static::where('company_name', $companyName)
            ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($province))]);

        return (clone $inProvince)->where('is_regional_hub', true)->value('municipality')
            ?? (clone $inProvince)->orderBy('id')->value('municipality');
    }

    /**
     * The ordered hub-to-hub route a shipment takes for this company between a
     * seller in $originMunicipality/$originProvince and a buyer in
     * $destinationMunicipality/$destinationProvince. Empty when they're the
     * same city (no transfer needed), one 'direct' leg when they're in the
     * same province (unchanged single-hop behavior), and up to three when
     * crossing provinces — see ShipmentHubLeg's docblock. Every hub named here
     * is one this company itself registered; nothing here is hardcoded.
     *
     * @return array<int, array{leg_type:string, from_hub:string, to_hub:string}>
     */
    public static function buildRoute(string $companyName, string $originMunicipality, ?string $originProvince, string $destinationMunicipality, ?string $destinationProvince): array
    {
        if (mb_strtolower(trim($originMunicipality)) === mb_strtolower(trim($destinationMunicipality))) {
            return [];
        }

        $sameProvince = $originProvince && $destinationProvince
            && mb_strtolower(trim($originProvince)) === mb_strtolower(trim($destinationProvince));

        if ($sameProvince || !$originProvince || !$destinationProvince) {
            return [['leg_type' => 'direct', 'from_hub' => $originMunicipality, 'to_hub' => $destinationMunicipality]];
        }

        $originRegional      = static::regionalHubFor($companyName, $originProvince) ?: $originMunicipality;
        $destinationRegional = static::regionalHubFor($companyName, $destinationProvince) ?: $destinationMunicipality;

        $legs = [];
        if (mb_strtolower(trim($originRegional)) !== mb_strtolower(trim($originMunicipality))) {
            $legs[] = ['leg_type' => 'dispatch', 'from_hub' => $originMunicipality, 'to_hub' => $originRegional];
        }
        $legs[] = ['leg_type' => 'relay', 'from_hub' => $originRegional, 'to_hub' => $destinationRegional];
        if (mb_strtolower(trim($destinationRegional)) !== mb_strtolower(trim($destinationMunicipality))) {
            $legs[] = ['leg_type' => 'delivery', 'from_hub' => $destinationRegional, 'to_hub' => $destinationMunicipality];
        }

        return $legs;
    }
}
