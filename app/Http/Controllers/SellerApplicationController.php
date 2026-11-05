<?php

namespace App\Http\Controllers;

use App\Enums\SellerType;
use App\Models\Category;
use App\Models\User;
use App\Services\MediaLifecycle;
use App\Support\PhilippineMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SellerApplicationController extends Controller
{
    private function applicant(Request $request): User
    {
        $grant = $request->session()->get('seller_application_correction');
        abort_unless(is_array($grant) && ($grant['expires_at'] ?? 0) > now()->timestamp, 403, 'Sign in again to correct your application.');
        abort_if($request->user() && (string) $request->user()->id !== (string) $grant['id'], 403);
        $user = User::whereKey($grant['id'])->where('account_type', 'seller')->where('status', 'rejected')->firstOrFail();
        abort_unless($user->can_resubmit,403,'Editing and resubmission are not permitted for this application.');
        return $user;
    }

    public function edit(Request $request)
    {
        $applicant = $this->applicant($request);
        $documents = $this->documents($applicant);
        return response()->view('auth.seller-application-correction', [
            'applicant' => $applicant, 'documents' => $documents,
            'categories' => Category::orderBy('name')->get(),
            'idTypes' => DB::table('id_types')->orderBy('name')->get(),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function documents(User $applicant, mixed $sellerType = null): array
    {
        $documents = [];
        $definitions = ['id_file' => ['Government-issued ID', 'id_files'], 'selfie_file' => ['Selfie', 'selfie_files']];
        foreach (\App\Support\SellerDocuments::LABELS as $field => $label) $definitions[$field] = [$label, 'seller_documents'];
        $requiredCertificates = \App\Support\SellerDocuments::required($sellerType ?? $applicant->seller_type);
        foreach ($definitions as $field => [$label, $directory]) {
            $path = $applicant->{$field};
            $disk = 'sensitive_documents';
            $exists = $path && (Storage::disk($disk)->exists($path) || Storage::disk('public')->exists($path));
            $required = in_array($field, ['id_file', 'selfie_file'], true) || in_array($field, $requiredCertificates, true);
            $types = $field === 'dti_certificate_file' ? 'sole_proprietorship' : ($field === 'sec_certificate_file' ? 'corporation' : ($field === 'bir_certificate_file' ? 'sole_proprietorship,corporation' : null));
            $documents[$field] = compact('label', 'directory', 'exists', 'required', 'types');
        }
        return $documents;
    }

    public function update(Request $request)
    {
        $applicant = $this->applicant($request);
        $request->merge([
            'tin' => is_string($request->input('tin')) ? preg_replace('/[-\s]/u', '', $request->input('tin')) : $request->input('tin'),
            'contact_no' => is_string($request->input('contact_no')) ? PhilippineMobile::normalize($request->input('contact_no')) : $request->input('contact_no'),
        ]);
        $name = ['required', 'string', 'min:2', 'max:50', "regex:/^[\\p{L}'’\\-]+(\\s[\\p{L}'’\\-]+)*$/u"];
        $rules = [
            'given_names' => $name, 'last_name' => $name,
            'middle_name' => array_replace($name, [0 => 'nullable']),
            'sex' => ['required', new \Illuminate\Validation\Rules\Enum(\App\Enums\Sex::class)],
            'birthday' => 'required|date|before_or_equal:'.now()->subYears(16)->toDateString(),
            'business_name' => ['required', 'string', 'min:3', 'max:30', Rule::unique('users', 'business_name')->ignore($applicant->id)],
            'seller_type' => ['required', Rule::enum(SellerType::class)],
            'tin' => ['required', 'string', 'regex:/^[0-9]{12}$/D', Rule::unique('users', 'tin')->ignore($applicant->id)],
            'contact_no' => ['required', 'regex:/^09[0-9]{9}$/D', function ($attribute, $value, $fail) use ($applicant) {
                if (User::whereKeyNot($applicant->id)->whereIn('contact_no', PhilippineMobile::variants($value))->exists()) $fail('This mobile number is already registered.');
            }],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_other' => 'nullable|string|max:100',
            'region' => 'required|string|max:255', 'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255', 'barangay' => 'required|string|max:255',
            'house_no' => 'nullable|string|max:50', 'street' => 'nullable|string|max:100',
            'postal_code' => 'required|regex:/^[0-9]{4}$/D',
            'id_type_id' => 'required|integer|exists:id_types,id',
        ];
        $documents = $this->documents($applicant, $request->input('seller_type'));
        foreach ($documents as $field => $document) {
            $rules[$field] = $document['required'] ? [$document['exists'] ? 'nullable' : 'required', 'file', $field === 'selfie_file' ? 'mimes:jpg,jpeg,png' : 'mimes:jpg,jpeg,png,pdf', 'max:5120'] : ['prohibited'];
        }
        $validator = validator($request->all(), $rules);
        $validator->after(function ($validator) use ($request) {
            if ((int) $request->filled('category_id') + (int) $request->filled('category_other') !== 1) {
                $validator->errors()->add('category_id', 'Choose one category, or enter a custom category.');
            }
        });
        $data = $validator->validate();
        foreach (array_keys($documents) as $field) unset($data[$field]);

        DB::transaction(function () use ($applicant, $request, $documents, $data) {
            $current = User::whereKey($applicant->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->account_type === 'seller' && $current->status === 'rejected', 409, 'This application has already been reviewed or resubmitted.');
            abort_unless($current->can_resubmit,403,'Resubmission permission has been withdrawn.');
            if (Schema::hasTable('hiring_application_history')) {
                DB::table('hiring_application_history')->insert([
                    'user_id' => $current->id, 'attempt_id' => (string) Str::uuid(),
                    'company_name' => $current->business_name, 'role' => 'seller', 'status' => 'rejected',
                    'reason' => $current->status_reason, 'occurred_at' => $current->updated_at ?? now(),
                    'recorded_at' => now(), 'legacy_snapshot' => true,
                ]);
            }
            $current->fill($data);
            foreach ($documents as $field => $document) {
                if ($request->hasFile($field)) {
                    $current->{$field} = app(MediaLifecycle::class)->store($request->file($field), $document['directory'], 'public');
                }
            }
            $current->status = 'pending';
            $current->can_resubmit = false;
            $current->status_reason = null;
            $current->save();
            if (Schema::hasTable('hiring_application_history')) {
                DB::table('hiring_application_history')->insert([
                    'user_id' => $current->id, 'attempt_id' => (string) Str::uuid(),
                    'company_name' => $current->business_name, 'role' => 'seller', 'status' => 'pending',
                    'occurred_at' => now(), 'recorded_at' => now(), 'legacy_snapshot' => false,
                ]);
            }
        });
        $request->session()->forget('seller_application_correction');
        return redirect()->route('login')->with('success', 'Your corrected application has been resubmitted and is awaiting admin approval.');
    }
}
