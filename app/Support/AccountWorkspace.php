<?php
namespace App\Support;

final class AccountWorkspace
{
    public static function sections(string $role, bool $salaryAvailable = false, bool $companyAvailable = false): array
    {
        $sections = [
            'profile-information'=>['icon'=>'user','title'=>'My profile','description'=>'Manage your personal details and profile photo.','ids'=>['profile-information','account-details','account-profile']],
            'account-security'=>['icon'=>'shield','title'=>'Login & security','description'=>'Update your password or verify a new email address.','ids'=>['account-security','change-email']],
            'address-information'=>['icon'=>'home','title'=>'My addresses','description'=>'Manage your registered account address.','ids'=>['address-information','account-address']],
        ];
        if ($role === 'seller') {
            $sections['shop-information']=['icon'=>'shop','title'=>'My shop','description'=>'Manage your shop details and photo.','ids'=>['shop-information']];
            $sections['account-documents']=['icon'=>'file','title'=>'Verification','description'=>'View and update your verification documents.','ids'=>['account-documents']];
            if (\App\Services\BuyerCommerce::installed()) $sections['shipping-rates']=['icon'=>'shop','title'=>'Shop availability','description'=>'Manage when your shop accepts orders.','ids'=>['shop-availability']];
        }
        if ($role === 'rider') $sections['vehicle-documents']=['icon'=>'truck','title'=>'Vehicle & documents','description'=>'Manage your registered vehicle and supporting documents.','ids'=>['vehicle-documents']];
        if ($role === 'logistics' && $salaryAvailable) $sections['salary-receiving']=['icon'=>'wallet','title'=>'Salary GCash','description'=>'Manage the account where you receive your salary.','ids'=>['salary-receiving']];
        if ($role === 'logistics' && $companyAvailable) $sections['account-terms']=['icon'=>'file','title'=>'Company terms','description'=>'Manage the terms shown to your company’s staff and riders.','ids'=>['account-terms']];
        return $sections;
    }
}
