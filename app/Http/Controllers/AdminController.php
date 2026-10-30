<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Models\AccountUpdateRequest;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Commission;
use App\Models\CompanyVehicle;
use App\Models\Complaint;
use App\Models\LogisticsCompany;
use App\Models\LogisticsHub;
use App\Models\Message;
use App\Models\Order;
use App\Models\Policy;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Setting;
use App\Models\SupportFaq;
use App\Models\User;
use App\Services\HubHiring;
use App\Services\MediaLifecycle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use HandlesMessaging;

    /** Admin can message any non-admin platform user. */
    protected function isAllowedContact(User $user): bool
    {
        return ! $user->is_admin;
    }

    public function login()
    {
        if (auth()->check()) {
            if (auth()->user()->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            if (auth()->user()->is_logistics) {
                return redirect()->route('logistics.dashboard');
            }
            if (auth()->user()->account_type === 'rider' && auth()->user()->status === 'approved') {
                return redirect()->route('rider.dashboard');
            }
        }

        return view('auth.login');
    }

    public function loginPost(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $login = $request->input('email');

        // Find user by email or username
        $user = User::where('email', $login)->orWhere('username', $login)->first();

        if ($user) {
            if ($user->status === 'pending') {
                return back()->withErrors(['email' => 'Your account is still pending admin approval. Please wait for confirmation.'])->withInput();
            }
            if ($user->status === 'interview') {
                return back()->withErrors(['email' => "You've been invited to a face-to-face interview — check your email for details. Your account will be enabled after that interview is confirmed."])->withInput();
            }
            if ($user->status === 'rejected') {
                if (! $user->password || ! \Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
                    return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
                }
                $message = 'Your account application was rejected.';
                if ($user->status_reason && $user->account_type!=='rider') {
                    $message .= ' Reason: '.$user->status_reason;
                }
                return back()->withErrors(['email' => $message])
                    ->with('accountStatus', 'rejected')->withInput();
            }
            if ($user->status === 'suspended') {
                return back()->withErrors(['email' => 'Your account has been suspended.'])
                    ->with('accountStatus', 'suspended')->withInput();
            }
            // Google-only accounts have no usable password
            if ($user->auth_method === 'google' && ! $user->password) {
                return back()->withErrors(['email' => 'This account was registered with Google. Please use "Continue with Google" to sign in.'])->withInput();
            }
        }

        // Auth::attempt only works with email, so resolve the email from username if needed
        $email = $user ? $user->email : $login;
        $credentials = ['email' => $email, 'password' => $request->input('password')];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = auth()->user();

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->is_logistics) {
                return redirect()->route('logistics.dashboard');
            }
            if ($user->account_type === 'buyer') {
                return redirect()->route('buyer.dashboard');
            }
            if ($user->account_type === 'seller') {
                return redirect()->route('seller.dashboard');
            }
            if ($user->account_type === 'rider') {
                return redirect()->route('rider.dashboard');
            }

            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function sidebarCounts(): array
    {
        return [
            // Riders and hub staff wait on their own logistics company's approval, not Admin's —
            // see LogisticsController::riders()/staff().
            'pendingRegistrations' => User::where('status', 'pending')->where('is_admin', false)->where('account_type', '!=', 'rider')
                ->where(fn ($q) => $q->where('logistics_role', '!=', 'hub_staff')->orWhereNull('logistics_role'))
                ->count(),
            'openDisputes' => Complaint::whereIn('status', ['open', 'escalated'])->count(),
            'unreadMessages' => Message::where('receiver_id', auth()->id())->where('read', false)->count(),
            'pendingDocs' => AccountUpdateRequest::where('status', 'pending')->count(),
            'pendingProducts' => Product::where('status', 'pending')->count(),
            'pendingCompanyPolicies' => Policy::where('type', 'logistics_company_terms')->whereNotNull('pending_content')->count(),
            'pendingVehicles' => CompanyVehicle::where('platform_status', 'pending')->count(),
        ];
    }

    public function faqs(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['audience' => ['sometimes', 'in:buyer,admin'], 'edit' => ['sometimes', 'integer']]);
        $audience = $data['audience'] ?? 'admin';
        $editing = isset($data['edit']) ? SupportFaq::findOrFail($data['edit']) : null;

        return view('admin.faqs', array_merge($this->sidebarCounts(), [
            'audience' => $audience, 'editing' => $editing,
            'faqs' => SupportFaq::where('audience', $audience)->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString(),
        ]));
    }

    public function approveUser(User $user)
    {
        abort_if(app(HubHiring::class)->role($user) !== null, 403, 'Personnel recruitment belongs to the company Logistics Admin.');
        $user->update(['status' => 'approved', 'status_reason' => null]);
        $this->sendStatusEmail($user, 'approved');

        return back()->with('success', 'Application approved.');
    }

    public function rejectUser(Request $request, User $user)
    {
        abort_if(app(HubHiring::class)->role($user) !== null, 403, 'Personnel recruitment belongs to the company Logistics Admin.');
        $reason = $this->resolveReason($request);
        // Founder names remain reserved; hubs/personnel and operational history are preserved.
        $user->update(['status' => 'rejected', 'status_reason' => $reason]);
        $this->sendStatusEmail($user, 'rejected', $reason);

        return back()->with('success', 'Application rejected.');
    }

    /**
     * The reason modal (shared by Reject and Suspend) submits a preset radio pick
     * plus a details textarea that's always fillable, not just when "Other" is
     * chosen — required only when the pick IS "Other", optional extra context otherwise.
     */
    private function resolveReason(Request $request): ?string
    {
        $request->validate([
            'reason_preset' => 'required|string|max:150',
            'reason_details' => $request->input('reason_preset') === 'other' ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ]);
        $preset = $request->input('reason_preset');
        $details = trim((string) $request->input('reason_details'));
        if ($preset === 'other') {
            return $details;
        }

        return $details !== '' ? "{$preset} — {$details}" : $preset;
    }

    /** Formal, branded email for every account-status change a registrant/user is promised. */
    private function sendStatusEmail(User $user, string $status, ?string $reason = null): void
    {
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            \Illuminate\Support\Facades\DB::afterCommit(fn () => $this->sendStatusEmail($user, $status, $reason));

            return;
        }
        if (! $user->email) {
            return;
        }
        $name = trim($user->given_names.' '.$user->last_name);
        $role = ucfirst($user->account_type ?: 'account');
        $subject = match ($status) {
            'approved' => 'PocketFinds — Registration Approved',
            'activated' => 'PocketFinds — Account Reactivated',
            'rejected' => 'PocketFinds — Registration Update',
            'suspended' => 'PocketFinds — Account Suspended',
            default => 'PocketFinds — Account Update',
        };
        try {
            Mail::send('emails.account-status', compact('name', 'role', 'status', 'reason', 'subject'), function ($m) use ($user, $subject) {
                $m->to($user->email)->subject($subject);
            });
        } catch (\Exception $e) {
            // Status change is already saved either way — a mail hiccup shouldn't block the admin action.
        }
    }

    public function activateUser(Request $request, User $user)
    {
        if (app(HubHiring::class)->role($user) !== null) {
            abort_unless($user->status === 'suspended', 422, 'Only suspended personnel can be reactivated.');
        }
        if(app(HubHiring::class)->role($user)==='rider') $user->hiringDecision=['action'=>'activate','reason'=>app(\App\Services\PersonnelReasons::class)->resolve($request,'activate')];
        $user->update(['status' => 'approved', 'status_reason' => null]);
        $this->sendStatusEmail($user, 'activated');

        return back()->with('success', 'Account activated.');
    }

    public function suspendUser(Request $request, User $user)
    {
        if (app(HubHiring::class)->role($user) !== null) {
            abort_unless($user->status === 'approved', 422, 'Only approved personnel can be suspended.');
        }
        $reason = app(HubHiring::class)->role($user)==='rider'?app(\App\Services\PersonnelReasons::class)->resolve($request,'suspend'):$this->resolveReason($request);
        $user->update(['status' => 'suspended', 'status_reason' => $reason]);
        $this->sendStatusEmail($user, 'suspended', $user->account_type==='rider'?null:$reason);

        return back()->with('success', 'Account suspended.');
    }

    public function dashboard(Request $request)
    {
        $counts = $this->sidebarCounts();
        $totalUsers = User::where('is_admin', false)->count();
        $pendingCount = $counts['pendingRegistrations'];
        $recentUsers = User::where('is_admin', false)->latest()->take(5)->get();
        $latestAnnouncement = Announcement::latest()->first();

        // Sales performance: real per-day order totals for the selected window (0 where no orders).
        $salesDays = in_array((int) $request->query('days'), [7, 14, 30, 90], true) ? (int) $request->query('days') : 30;
        $rangeStart = now()->subDays($salesDays - 1)->startOfDay();
        $dailySales = Order::where('created_at', '>=', $rangeStart)
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $salesSeries = collect(range(0, $salesDays - 1))->map(function ($i) use ($rangeStart, $dailySales) {
            $date = $rangeStart->copy()->addDays($i);

            return ['date' => $date, 'total' => (float) ($dailySales[$date->format('Y-m-d')] ?? 0)];
        });
        $salesTotal = $salesSeries->sum('total');

        return view('admin.dashboard', array_merge($counts, compact(
            'totalUsers', 'pendingCount', 'recentUsers', 'latestAnnouncement', 'salesSeries', 'salesTotal', 'salesDays'
        )));
    }

    public function aiOrderReview(Order $order)
    {
        abort_unless(auth()->user()?->is_admin, 403);

        return view('admin.ai-order-review', array_merge($this->sidebarCounts(), ['order' => $order]));
    }

    public function aiWorkspace(Request $request)
    {
        $selection = $request->validate([
            'mode' => 'sometimes|in:dashboard,reports,seller,product,draft,chat,analytics,inventory,anomalies,product_copy',
            'product_id' => 'sometimes|string|max:64', 'seller_id' => 'sometimes|integer|min:1',
        ]);
        $products = Product::orderByRaw("status = 'pending' DESC")->latest()->limit(100)->get(['id', 'name', 'status']);
        $sellers = User::where('account_type', 'seller')->where('is_admin', false)
            ->orderByRaw("status = 'pending' DESC")->latest()->limit(100)->get(['id', 'business_name', 'status']);
        if (! empty($selection['product_id']) && ! $products->contains('id', $selection['product_id'])) {
            $products->prepend(Product::findOrFail($selection['product_id'], ['id', 'name', 'status']));
        }
        if (! empty($selection['seller_id']) && ! $sellers->contains('id', $selection['seller_id'])) {
            $sellers->prepend(User::where('account_type', 'seller')->where('is_admin', false)->findOrFail($selection['seller_id'], ['id', 'business_name', 'status']));
        }

        return view('admin.ai-workspace', array_merge($this->sidebarCounts(), compact('products', 'sellers', 'selection'), [
            'aiAvailable' => (bool) config('services.groq.key'),
            'chatHistory' => $request->session()->get('admin_assistant.'.auth()->id(), []),
            'chatEngine' => $request->session()->get('admin_assistant_engine.'.auth()->id(), $request->session()->has('admin_assistant.'.auth()->id()) ? 'ai' : 'automated'),
        ]));
    }

    public function registrations()
    {
        $counts = $this->sidebarCounts();
        // Riders/couriers are Logistics/Sorting Center's to review (LogisticsController::riders()),
        // not Admin's — the ERP spec's admin scope is buyer/seller/logistics applications only.
        // Hub staff join an existing logistics company the same way a rider does, so their
        // applications belong to that company's own admin too (LogisticsController::staff()),
        // not the platform admin — otherwise a hub-staff signup would show up here looking
        // indistinguishable from someone founding a brand new company.
        // This is a review queue for an initial approve/reject decision, not a directory — an
        // approved account moves on to the Users page, and a suspended one could only ever have
        // gotten there by being approved first, so it belongs there too, not back here.
        $users = User::with('category')->where('is_admin', false)->where('account_type', '!=', 'rider')
            ->where(fn ($q) => $q->where('logistics_role', '!=', 'hub_staff')->orWhereNull('logistics_role'))
            ->whereNotIn('status', ['approved', 'suspended'])->latest()->get();

        return view('admin.registrations', array_merge($counts, compact('users')));
    }

    public function users()
    {
        $counts = $this->sidebarCounts();
        // A rejected application never became a real account — it stays on the Registrations
        // page for reconsideration (Approve there), not here.
        $users = User::with('category')->where('is_admin', false)->where('status', '!=', 'rejected')->latest()->get();

        return view('admin.users', array_merge($counts, compact('users')));
    }

    public function compliance()
    {
        $counts = $this->sidebarCounts();
        $sellers = User::with('category')->where('account_type', 'seller')->where('is_admin', false)->latest()->get();

        return view('admin.compliance', array_merge($counts, compact('sellers')));
    }

    public function complaints()
    {
        $counts = $this->sidebarCounts();
        $complaints = Complaint::with(['complainant', 'respondent'])->latest('created_at')->get();

        return view('admin.complaints', array_merge($counts, compact('complaints')));
    }

    public function resolveComplaint(Request $request, $id)
    {
        Complaint::where('id', $id)->update([
            'status' => 'resolved',
            'handled_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution' => $request->input('resolution', 'Resolved by admin.'),
        ]);

        return back();
    }

    public function commission()
    {
        $counts = $this->sidebarCounts();
        $commissions = Commission::with('seller')->latest('created_at')->get();
        return view('admin.commission', array_merge($counts, compact('commissions')));
    }

    public function reports()
    {
        $counts = $this->sidebarCounts();
        $totalUsers = User::where('is_admin', false)->count();
        $buyerCount = User::where('account_type', 'buyer')->count();
        $sellerCount = User::where('account_type', 'seller')->count();
        $riderCount = User::where('account_type', 'rider')->count();
        $pending = User::where('status', 'pending')->where('is_admin', false)->count();
        $approved = User::where('status', 'approved')->where('is_admin', false)->count();
        $rejected = User::where('status', 'rejected')->where('is_admin', false)->count();
        $paymentReport=app(\App\Services\PaymentReporting::class)->snapshot(null);

        return view('admin.reports', array_merge($counts, compact(
            'totalUsers', 'buyerCount', 'sellerCount', 'riderCount',
            'pending', 'approved', 'rejected', 'paymentReport'
        )));
    }

    public function exportSalesReport()
    {
        $report=app(\App\Services\PaymentReporting::class)->snapshot(null);
        $rows=[['Order ID','Seller ID','Completed order last updated','Item subtotal PHP','Quoted shipping PHP','Discount already applied PHP','Buyer order value PHP','Payment method']];
        foreach($report['orders'] as $o)$rows[]=[$o->id,$o->seller_id,$o->updated_at?->toIso8601String(),$o->subtotal,$o->shipping_amount,$o->discount_amount,$o->total,$o->payment_method];
        return $this->reportCsv($rows,'sales_report');
    }

    public function exportSalesReportPdf()
    {
        $paymentReport=app(\App\Services\PaymentReporting::class)->snapshot(null);
        return Pdf::loadView('admin.reports-sales-pdf',compact('paymentReport'))->setPaper('a4','portrait')
            ->download('sales_report_'.now()->format('Ymd').'.pdf')->header('Cache-Control','private, no-store');
    }

    // Keep the established route URL; its current report is payment activity, not a fee ledger.
    public function exportCommissionReport()
    {
        $report=app(\App\Services\PaymentReporting::class)->snapshot(null);
        $rows=[['Metric','PHP','Basis']];
        foreach($report['metrics'] as $key=>$value)$rows[]=[$key,(!$report['available'] && !in_array($key,['item_sales','quoted_shipping','discounts','buyer_order_value']))?'Unavailable':\App\Services\LogisticsReconciliation::money($value),in_array($key,['pending_cod_payouts','held_cod_payouts','unverified_platform_claims'])?'Current snapshot':(in_array($key,['item_sales','quoted_shipping','discounts','buyer_order_value'])?'Completed orders':'All recorded payment activity')];
        return $this->reportCsv($rows,'payment_summary');
    }

    private function reportCsv(array $rows,string $name)
    {
        $stream=fopen('php://temp','r+');
        foreach($rows as $row)fputcsv($stream,array_map([\App\Services\LogisticsReconciliation::class,'csvCell'],$row),',','"','');
        rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
        return response($csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$name.'_'.now()->format('Ymd').'.csv"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function settings()
    {
        $counts = $this->sidebarCounts();
        $setting = Setting::current();

        return view('admin.settings', array_merge($counts, compact('setting')));
    }

    /**
     * One page for every Terms & Conditions document on the platform: PocketFinds' own
     * per-role ones (admin-authored, saved directly), and every logistics company's own
     * (submitted by the company, only live once an admin approves it here).
     */
    public function policies()
    {
        $counts = $this->sidebarCounts();
        // One Terms & Conditions document per registration role — each independently
        // admin-editable, keyed by account_type.
        $terms = Policy::with('editor')->where('type', 'terms_and_conditions')
            ->get()->keyBy('account_type');
        $termsHistory = $terms->map(fn ($p) => $p->historyForDisplay()->take(10));
        $companies = LogisticsCompany::summaries();

        return view('admin.policies', array_merge($counts, compact('terms', 'termsHistory', 'companies')));
    }

    /**
     * Update one role's platform-wide Terms & Conditions document. Every save appends what
     * the content used to be onto that same row's `history` column — never a silent overwrite.
     */
    public function updatePolicy(Request $request, string $accountType)
    {
        $policy = Policy::where('type', 'terms_and_conditions')->where('account_type', $accountType)->firstOrFail();
        $data = $request->validate(['content' => 'required|string|max:20000']);
        $policy->updateContent($data['content'], auth()->id());

        return back()->with('success', ucfirst($accountType).' Terms & Conditions updated.');
    }

    /** Admin approves a logistics company's submitted Terms & Conditions — it goes live for new registrants immediately. */
    public function approveCompanyPolicy(string $companyName)
    {
        $policy = LogisticsCompany::policyFor($companyName);
        abort_if(! $policy || ! $policy->hasPending(), 404);
        $policy->approvePending(auth()->id());

        return back()->with('success', "{$companyName}'s Terms & Conditions approved and published.");
    }

    /**
     * Admin sends a company's submitted Terms & Conditions back for revision — the live
     * version (if any) is untouched, and the company sees exactly what to fix and can resubmit.
     */
    public function requestCompanyPolicyRevisions(Request $request, string $companyName)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:1000']);
        $policy = LogisticsCompany::policyFor($companyName);
        abort_if(! $policy || ! $policy->hasPending(), 404);
        $policy->rejectPending($data['rejection_reason']);

        return back()->with('success', "Revisions requested for {$companyName}'s Terms & Conditions.");
    }

    public function updateGeneralSettings(Request $request)
    {
        $data = $request->validate([
            'platform_name' => 'required|string|max:100',
            'support_email' => 'required|email|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);
        $data['updated_by'] = auth()->id();

        Setting::current()->update($data);

        return back()->with('success', 'General settings saved.');
    }

    /** Store an admin's replacement category photo directly in MySQL. */
    public function updateCategoryPhoto(Request $request, int $id)
    {
        $request->validate([
            'category_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:min_width=64,min_height=64,max_width=3000,max_height=3000',
        ]);
        $category = Category::findOrFail($id);
        $source = imagecreatefromstring(file_get_contents($request->file('category_photo')->getRealPath()));
        abort_unless($source, 422, 'This image could not be read.');
        $photo = imagecreatetruecolor(480, 480);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
        $side = min(imagesx($source), imagesy($source));
        imagecopyresampled($photo, $source, 0, 0,
            (int) ((imagesx($source) - $side) / 2), (int) ((imagesy($source) - $side) / 2),
            480, 480, $side, $side);
        ob_start();
        imagejpeg($photo, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($photo);
        $category->forceFill([
            'image_data' => $bytes,
            'image_mime' => 'image/jpeg',
            'image_sha256' => hash('sha256', $bytes),
            'image_source' => null,
        ])->save();

        return redirect()->route('admin.settings')->with('settings_tab', 'categories')
            ->with('success', $category->name.' photo updated.');
    }

    /** Admin uploads/sets the landing page hero banner image, tagline, and seasonal theme label. */
    public function updateHeroSettings(Request $request)
    {
        $data = $request->validate([
            'hero_tagline' => 'nullable|string|max:200',
            'hero_subtitle' => 'nullable|string|max:500',
            'hero_cta_text' => 'nullable|string|max:80',
            'hero_overlay' => 'nullable|in:dark,light,none',
            'hero_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'hero_image_2' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'hero_image_2_clear' => 'sometimes|boolean',
            'hero_crop_x' => 'sometimes|required|integer|between:0,100',
            'hero_crop_y' => 'sometimes|required|integer|between:0,100',
            'hero_crop_zoom' => 'sometimes|required|integer|between:100,200',
            'hero_2_crop_x' => 'sometimes|required|integer|between:0,100',
            'hero_2_crop_y' => 'sometimes|required|integer|between:0,100',
            'hero_2_crop_zoom' => 'sometimes|required|integer|between:100,200',
        ]);

        $setting = Setting::current();

        if ($request->boolean('hero_image_clear')) {
            $data['hero_image'] = null;
            app(MediaLifecycle::class)->deleteAfterCommit($setting->hero_image, 'public');
        } elseif ($request->hasFile('hero_image')) {
            $data['hero_image'] = app(MediaLifecycle::class)->store($request->file('hero_image'), 'hero_banners', 'public');
            app(MediaLifecycle::class)->deleteAfterCommit($setting->hero_image, 'public');
        }

        unset($data['hero_image_2_clear']);
        if ($request->boolean('hero_image_2_clear')) {
            $data['hero_image_2'] = null;
            app(MediaLifecycle::class)->deleteAfterCommit($setting->hero_image_2, 'public');
        } elseif ($request->hasFile('hero_image_2')) {
            $data['hero_image_2'] = app(MediaLifecycle::class)->store($request->file('hero_image_2'), 'hero_banners', 'public');
            app(MediaLifecycle::class)->deleteAfterCommit($setting->hero_image_2, 'public');
        }

        $data['updated_by'] = auth()->id();
        $setting->update($data);

        return back()->with('success', 'Hero banner settings saved.');
    }

    public function updateFeatureToggles(Request $request)
    {
        $data = [
            'google_signin_enabled' => $request->boolean('google_signin_enabled'),
            'new_registrations_enabled' => $request->boolean('new_registrations_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled'),
            'updated_by' => auth()->id(),
        ];

        Setting::current()->update($data);

        return back()->with('success', 'Feature toggles updated.');
    }

    public function clearCache()
    {
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        return back()->with('success', 'Application cache cleared.');
    }

    public function clearSessions()
    {
        $currentSessionId = session()->getId();
        $sessionPath = storage_path('framework/sessions');

        if (is_dir($sessionPath)) {
            foreach (glob($sessionPath.'/*') as $file) {
                $filename = basename($file);
                // Keep the session making this very request so the admin isn't
                // immediately logged out by the action they just took.
                if (is_file($file) && $filename !== $currentSessionId) {
                    @unlink($file);
                }
            }
        }

        return back()->with('success', 'All other sessions were cleared. You stayed signed in.');
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark,system',
            'preferred_language' => 'required|string|max:10',
        ]);

        auth()->user()->update($data);

        return back()->with('success', 'Preferences saved.');
    }

    public function announcements()
    {
        $counts = $this->sidebarCounts();
        $announcements = Announcement::latest()->get();

        $total = $announcements->count();
        $active = $announcements->where('is_active', true)->count();
        $byAudience = $announcements->groupBy('audience')->map->count();
        $latest = $announcements->first();

        return view('admin.announcements', array_merge($counts, compact(
            'announcements', 'total', 'active', 'byAudience', 'latest'
        )));
    }

    public function storeAnnouncement(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'required|in:all,buyer,seller,rider',
        ]);
        $announcement = Announcement::create([
            'title' => $request->title,
            'body' => $request->body,
            'audience' => $request->audience,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
        $announcement->notifyAudience();

        return back()->with('success', 'Announcement posted.');
    }

    public function destroyAnnouncement($id)
    {
        \DB::table('notifications')->where('notification_type', 'announcement')->where('reference_id', $id)->delete();
        Announcement::findOrFail($id)->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    public function messages(Request $request, ?User $user = null)
    {
        $counts = $this->sidebarCounts();
        $users = User::where('is_admin', false)
            ->orderBy('given_names')
            ->orderBy('last_name')
            ->get();

        // Enrich each user with their latest message + unread count against this admin,
        // so the inbox reads like a real conversation list, not a bare directory.
        $adminId = auth()->id();
        $threads = Message::where('sender_id', $adminId)->orWhere('receiver_id', $adminId)
            ->latest('created_at')->get()
            ->groupBy(fn ($m) => $m->sender_id === $adminId ? $m->receiver_id : $m->sender_id);

        $users = $users->map(function ($u) use ($threads, $adminId) {
            $thread = $threads->get($u->id);
            $u->last_message = $thread?->first();
            $u->unread_count = $thread ? $thread->where('receiver_id', $adminId)->where('read', false)->count() : 0;

            return $u;
        })->sortByDesc(fn ($u) => $u->last_message?->created_at ?? Carbon::createFromTimestamp(0))->values();

        $selectedUser = $user && ! $user->is_admin ? $user : $users->first();
        $messages = collect();

        if ($selectedUser) {
            Message::where('sender_id', $selectedUser->id)
                ->where('receiver_id', auth()->id())
                ->where('read', false)
                ->update(['read' => true]);

            $messages = Message::with(['sender', 'receiver', 'replyTo.sender', 'product'])
                ->where(function ($query) use ($selectedUser) {
                    $query->where('sender_id', auth()->id())
                        ->where('receiver_id', $selectedUser->id);
                })
                ->orWhere(function ($query) use ($selectedUser) {
                    $query->where('sender_id', $selectedUser->id)
                        ->where('receiver_id', auth()->id());
                })
                ->oldest()
                ->get();

            if ($selectedUser->account_type === 'rider') {
                $selectedUser->riderProfile = RiderProfile::where('user_id', $selectedUser->id)->first();
            }
        }

        // Lets the composer's "share a product" picker work without a
        // separate lookup endpoint — same idea as the buyer/seller composer's
        // own product picker, just admin-scoped to every live listing.
        $pickerProducts = Product::where('status', 'active')->latest()->limit(60)->get(['id', 'name', 'price', 'image']);

        return view('admin.messages', array_merge($counts, compact('users', 'selectedUser', 'messages', 'pickerProducts')));
    }

    /**
     * Admin's own render of a poll tick: reuses HandlesMessaging's read-receipt
     * and thread-fetch logic, but returns the same server-rendered bubble
     * partial the full page uses (admin.partials.messages-body) instead of
     * the trait's raw JSON array — the bubble markup here (reply quotes,
     * reaction picker, profile-linked header) is admin-specific and this
     * keeps that markup defined in exactly one place. Sending itself still
     * goes through the shared HandlesMessaging::messagesSend.
     */
    public function messagesPoll(Request $request)
    {
        $data = $request->validate(['receiver_id' => ['required', 'integer']]);
        $receiver = User::findOrFail($data['receiver_id']);
        abort_unless(! $receiver->is_admin, 403);

        Message::where('sender_id', $receiver->id)->where('receiver_id', auth()->id())
            ->where('read', false)->update(['read' => true]);

        $messages = Message::with(['sender', 'replyTo.sender', 'product'])
            ->where(function ($q) use ($receiver) {
                $q->where('sender_id', auth()->id())->where('receiver_id', $receiver->id);
            })->orWhere(function ($q) use ($receiver) {
                $q->where('sender_id', $receiver->id)->where('receiver_id', auth()->id());
            })->oldest()->get();

        return response()->json(['ok' => true, 'html' => view('admin.partials.messages-body', compact('messages'))->render()]);
    }

    public function reactMessage(Request $request, Message $message)
    {
        $request->validate(['emoji' => 'required|string|max:8']);

        $adminId = auth()->id();
        abort_unless($message->sender_id === $adminId || $message->receiver_id === $adminId, 403);

        $emoji = $request->emoji;
        $reactions = $message->reactions ?? [];

        // Toggle: remove the admin's own id from every emoji's list first,
        // then re-add it to the tapped emoji unless it was already there
        // (that's what makes tapping the same reaction twice remove it).
        $alreadyHadThisOne = in_array($adminId, $reactions[$emoji] ?? [], true);
        foreach ($reactions as $key => $ids) {
            $reactions[$key] = array_values(array_diff($ids, [$adminId]));
            if (empty($reactions[$key])) {
                unset($reactions[$key]);
            }
        }
        if (! $alreadyHadThisOne) {
            $reactions[$emoji] = array_merge($reactions[$emoji] ?? [], [$adminId]);
        }

        $message->update(['reactions' => $reactions]);

        if ($request->wantsJson()) {
            $otherId = $message->sender_id === $adminId ? $message->receiver_id : $message->sender_id;
            $messages = Message::with(['sender', 'replyTo.sender', 'product'])
                ->where(function ($q) use ($otherId, $adminId) {
                    $q->where('sender_id', $adminId)->where('receiver_id', $otherId);
                })->orWhere(function ($q) use ($otherId, $adminId) {
                    $q->where('sender_id', $otherId)->where('receiver_id', $adminId);
                })->oldest()->get();

            return response()->json(['ok' => true, 'html' => view('admin.partials.messages-body', compact('messages'))->render()]);
        }

        return back();
    }

    public function account()
    {
        $counts = $this->sidebarCounts();

        return view('admin.account', $counts);
    }

    public function accountUpdate(Request $request)
    {
        \App\Support\AccountDetails::normalize($request);
        $user = auth()->user();
        $request->validate([
            'given_names' => \App\Support\AccountDetails::name(),
            'last_name' => \App\Support\AccountDetails::name(),
            'email' => ['required', 'email', 'max:255', Rule::in([$user->email])],
            'contact_no' => ['required','regex:/^09[0-9]{9}$/'],
            'region' => 'required|string|max:255',
            'postal_code' => ['required','regex:/^[0-9]{4}$/D'],
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'house_no' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->only([
            'given_names',
            'last_name',
            'contact_no',
            'region',
            'postal_code',
            'province',
            'municipality',
            'barangay',
            'house_no',
            'street',
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                app(MediaLifecycle::class)->deleteAfterCommit($user->profile_picture, 'profile_images');
            }

            $data['profile_picture'] = app(MediaLifecycle::class)->store($request->file('profile_picture'), 'profile-pictures', 'profile_images');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required','confirmed',\Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (! Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated.');
    }

    public function updateRequests()
    {
        $counts = $this->sidebarCounts();
        $requests = AccountUpdateRequest::with('user')->latest()->get();
        $idTypes = \DB::table('id_types')->orderBy('id')->get()->keyBy('id');

        return view('admin.update-requests', array_merge($counts, compact('requests', 'idTypes')));
    }

    public function approveUpdateRequest($id)
    {
        $req = AccountUpdateRequest::findOrFail($id);
        $req->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        $req->apply();

        \DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $req->user_id,
            'title' => 'Update Request Approved',
            'message' => 'Your account update request has been approved and your account has been updated.',
            'notification_type' => 'doc_approved',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Request approved and the user notified.');
    }

    public function rejectUpdateRequest(Request $request, $id)
    {
        $req = AccountUpdateRequest::findOrFail($id);
        $req->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'note' => $request->input('note'),
        ]);

        \DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $req->user_id,
            'title' => 'Update Request Rejected',
            'message' => 'Your account update request was rejected.'.($request->note ? ' Reason: '.$request->note : ''),
            'notification_type' => 'doc_rejected',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Request rejected and the user notified.');
    }

    public function products()
    {
        $counts = $this->sidebarCounts();
        $products = Product::with(['seller.category', 'category'])->latest()->get();
        $categories = \DB::table('categories')->orderBy('name')->get()->keyBy('id');

        return view('admin.products', array_merge($counts, compact('products', 'categories')));
    }

    public function approveProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'active', 'rejection_note' => null]);

        \DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $product->seller_id,
            'title' => 'Product Approved',
            'message' => 'Your product "'.$product->name.'" has been approved and is now live.',
            'notification_type' => 'product_approved',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Product approved.');
    }

    public function rejectProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update([
            'status' => 'rejected',
            'rejection_note' => $request->input('note'),
        ]);

        \DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $product->seller_id,
            'title' => 'Product Rejected',
            'message' => 'Your product "'.$product->name.'" was rejected.'.($request->note ? ' Reason: '.$request->note : ''),
            'notification_type' => 'product_rejected',
            'is_read' => false,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Product rejected and seller notified.');
    }

    // ── Company Vehicle Review (platform admin gate) ─────────────────────────────────

    public function vehicles()
    {
        $counts = $this->sidebarCounts();
        $vehicles = CompanyVehicle::with(['hub', 'submitter'])
            ->latest()->get();
        $platformCounts = $vehicles->countBy('platform_status');

        return view('admin.vehicles', array_merge($counts, compact('vehicles', 'platformCounts')));
    }

    public function approveVehicle($id)
    {
        $vehicle = CompanyVehicle::findOrFail($id);
        $vehicle->update([
            'platform_status' => 'approved',
            'platform_status_reason' => null,
            'platform_reviewed_by' => auth()->id(),
            'platform_reviewed_at' => now(),
            'is_available' => true,
        ]);

        return back()->with('success', "{$vehicle->brand} {$vehicle->model} ({$vehicle->plate_number}) approved — it is now available for rider assignment at the {$vehicle->hub?->municipality} hub.");
    }

    public function rejectVehicle(Request $request, $id)
    {
        $vehicle = CompanyVehicle::findOrFail($id);
        $reason = $this->resolveReason($request);
        $vehicle->update([
            'platform_status' => 'rejected',
            'platform_status_reason' => $reason,
            'platform_reviewed_by' => auth()->id(),
            'platform_reviewed_at' => now(),
            'is_available' => false,
        ]);

        return back()->with('success', "{$vehicle->brand} {$vehicle->model} ({$vehicle->plate_number}) rejected.");
    }
}
