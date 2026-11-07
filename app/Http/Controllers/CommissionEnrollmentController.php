<?php

namespace App\Http\Controllers;

use App\Services\CommissionEnrollment;
use Illuminate\Http\Request;

class CommissionEnrollmentController extends Controller
{
    public function update(Request $request, CommissionEnrollment $enrollment)
    {
        $enrollment->change($request->user(),$request->all());
        return redirect()->route('admin.settings')->with('success','Commission enrollment '.($request->input('action')==='activate'?'activated for new eligible orders from the displayed activation time.':'deactivated. Existing obligations continue unchanged.'));
    }
}
