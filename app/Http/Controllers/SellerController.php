<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerController extends Controller
{
    public function dashboard()   { return view('seller.dashboard'); }
    public function orders()      { return view('seller.orders'); }
    public function inventory()   { return view('seller.inventory'); }
    public function notifications(){ return view('seller.notifications'); }
    public function prepare()     { return view('seller.prepare'); }
    public function shipments()   { return view('seller.shipments'); }
    public function deliveries()  { return view('seller.deliveries'); }
    public function feedback()    { return view('seller.feedback'); }
    public function reports()     { return view('seller.reports'); }
    public function messages()    { return view('seller.messages'); }
    public function account()     { return view('seller.account'); }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
