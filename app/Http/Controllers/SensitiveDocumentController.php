<?php

namespace App\Http\Controllers;

use App\Services\SensitiveDocuments;
use Illuminate\Http\Request;

class SensitiveDocumentController extends Controller
{
    public function show(Request $request, string $token, SensitiveDocuments $documents)
    {
        return $documents->response($request->user(), $token);
    }
}
