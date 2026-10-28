<?php

namespace App\Models;

use App\Enums\Suffix;
use App\Services\HubHiring;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function save(array $options = [])
    {
        if (Schema::hasTable('hiring_application_history')
            && ($this->account_type === 'rider' || $this->logistics_role === 'hub_staff')) {
            try {
                return app(HubHiring::class)->persist($this, fn () => parent::save($options));
            } catch (UniqueConstraintViolationException $e) {
                throw ValidationException::withMessages(['email' => 'An account with these identifiers already exists. Refresh and try again.']);
            }
        }

        return parent::save($options);
    }

    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'account_type', 'auth_method', 'google_id', 'username',
        'last_name', 'given_names', 'middle_name', 'suffix', 'sex', 'birthday', 'age',
        'email', 'contact_no',
        'region', 'province', 'municipality', 'barangay', 'house_no', 'street',
        'seller_type', 'tin',
        'position', 'sec_registration_no', 'postal_code', 'service_regions',
        'is_on_vacation',
        'password', 'id_file', 'id_type_id', 'selfie_file', 'resume_file',
        'status', 'status_reason', 'is_admin', 'is_logistics', 'logistics_role', 'logistics_hub_id',
        'interview_scheduled_at', 'interview_location', 'category_id', 'category_other',
        'profile_picture', 'business_name', 'business_permit_file', 'shipping_fee', 'company_logo',
        // rider-only fields (merged in from the now-dropped rider_profiles table)
        'vehicle_type', 'vehicle_ownership', 'vehicle_brand', 'vehicle_model', 'plate_number',
        'or_file', 'cr_file', 'license_number', 'license_expiry', 'license_file',
        // logistics-only settings
        'notify_new_requests', 'notify_unassigned_shipments', 'preferred_scanner',
        'theme', 'preferred_language',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
            'suffix' => Suffix::class,
            'seller_type' => \App\Enums\SellerType::class,
            'service_regions' => 'array',
            'is_on_vacation' => 'boolean',
            'license_expiry' => 'date',
            'interview_scheduled_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_logistics' => 'boolean',
            'notify_new_requests' => 'boolean',
            'notify_unassigned_shipments' => 'boolean',
        ];
    }

    /** True if this rider's vehicle type requires a driver's license. */
    public function requiresLicense(): bool
    {
        return in_array($this->vehicle_type, ['two_wheels', 'four_wheels'], true);
    }

    /** A logistics account is 'admin' (the company's founder — the default for every
     *  existing account) or 'hub_staff' (tied to one specific LogisticsHub). */
    public function isLogisticsAdmin(): bool
    {
        return $this->is_logistics && in_array($this->logistics_role, [null, 'admin'], true);
    }

    public function isHubStaff(): bool
    {
        return $this->is_logistics && $this->logistics_role === 'hub_staff';
    }

    public function logisticsHub()
    {
        return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id');
    }

    /** Reverse of Product::seller() — a seller's own listings. */
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    /** Buyers following this seller's shop. */
    public function followers()
    {
        return $this->hasMany(ShopFollow::class, 'seller_id');
    }

    /** Shops this buyer follows. */
    public function followedShops()
    {
        return $this->hasMany(ShopFollow::class, 'buyer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function shopPhotoUrl(): ?string
    {
        if (! $this->profile_picture) {
            return null;
        }
        if (preg_match('/^category:(\d+)$/', $this->profile_picture, $matches)) {
            return route('guest.category-image', ['id' => (int) $matches[1]], false);
        }

        return Storage::disk('profile_images')->url($this->profile_picture);
    }

    /** This user's logistics company's own (admin-approved) Terms & Conditions, if it has submitted one. */
    public function companyPolicy(): ?Policy
    {
        return $this->business_name ? LogisticsCompany::policyFor($this->business_name) : null;
    }
}
