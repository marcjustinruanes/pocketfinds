<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyerController extends Controller
{
    public function dashboard()
    {
        return view('buyer.dashboard');
    }

    public function browse()
    {
        return view('buyer.browse');
    }

    public function cart()
    {
        return view('buyer.cart');
    }

    public function orders()
    {
        return view('buyer.orders');
    }

    public function messages()
    {
        return view('buyer.messages');
    }

    public function account()
    {
        return view('buyer.account');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
