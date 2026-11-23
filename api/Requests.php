<?php
declare(strict_types=1);
use Illuminate\Support\Facades\DB;

/** App-local replay journal. An insert lock serializes even the first request. */
final class MobileRequests {
    public static function run(string $scope,string $key,array $payload,Closure $operation): mixed {
        validateData(['key'=>$key],['key'=>'required|uuid']);
        $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        return DB::transaction(function() use($scope,$key,$hash,$operation) {
            DB::table('mobile_request_journal')->insertOrIgnore(['scope'=>$scope,'request_id'=>$key,'request_hash'=>$hash,'created_at'=>now()]);
            $row=DB::table('mobile_request_journal')->where('scope',$scope)->where('request_id',$key)->lockForUpdate()->first();
            ensure(hash_equals($row->request_hash,$hash),'This request was already used for different details. Start a new action.',409);
            if ($row->result!==null) return json_decode($row->result,true);
            $result=$operation();
            DB::table('mobile_request_journal')->where('scope',$scope)->where('request_id',$key)->update(['result'=>json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        },3);
    }
}
