<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonnelReasons
{
    public const REJECT=['Incomplete or unclear documents',"Document details don't match provided information",'Suspicious or potentially fraudulent submission','Duplicate application',"Doesn't meet role requirements"];
    public const SUSPEND=['Violation of platform policies','Fraudulent or suspicious activity','Multiple complaints from other users','Failure to fulfill orders or deliveries','Non-compliance with verification requirements'];
    public const ACTIVATE=['Review completed; operational access restored','Temporary restriction resolved','Administrative correction approved'];

    public function resolve(Request $request,string $action): string
    {
        $request->validate(['reason_preset'=>'required|string','reason_details'=>'nullable|string']);
        $request->merge(['reason_details'=>trim((string)$request->input('reason_details'))]);
        $presets=match($action){'reject'=>self::REJECT,'suspend'=>self::SUSPEND,'activate'=>self::ACTIVATE};
        $d=$request->validate(['reason_preset'=>['required',Rule::in([...$presets,'other'])],'reason_details'=>[ $request->input('reason_preset')==='other'?'required':'nullable','string', $request->input('reason_preset')==='other'?'min:3':'min:0','regex:/[\p{L}\p{N}]/u','max:1000']]);
        return $d['reason_preset']==='other'?$d['reason_details']:$d['reason_preset'].(filled($d['reason_details']??null)?' — '.$d['reason_details']:'');
    }
}
