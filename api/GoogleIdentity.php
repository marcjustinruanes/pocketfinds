<?php
declare(strict_types=1);

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GoogleIdentity {
    public static function clientId(): string {
        return (string)config('services.google.client_id');
    }
    public static function verify(string $token): array {
        ensure(self::clientId()!=='','Google sign-in is not configured on the server.',503);
        ensure(strlen($token)<8000 && count(explode('.',$token))===3,'Google could not verify this account.',401);
        $fetch=static function () {
            $response=Http::timeout(15)->get('https://www.googleapis.com/oauth2/v3/certs');
            ensure($response->successful() && is_array($response->json('keys')),'Google verification is temporarily unavailable.',503);
            return $response->json();
        };
        try {
            $keys=Cache::remember('pocketfinds-mobile-google-keys',3600,$fetch);
            try { $claims=(array)JWT::decode($token,JWK::parseKeySet($keys,'RS256')); }
            catch (UnexpectedValueException $e) {
                // Google rotates signing keys. Refresh once before rejecting a token.
                Cache::forget('pocketfinds-mobile-google-keys');
                $claims=(array)JWT::decode($token,JWK::parseKeySet(Cache::remember('pocketfinds-mobile-google-keys',3600,$fetch),'RS256'));
            }
        } catch (ApiError $e) { throw $e; }
        catch (Throwable) { throw new ApiError('Google could not verify this account. Please try again.',401); }
        ensure(in_array(self::clientId(),(array)($claims['aud']??[]),true)
            && in_array($claims['iss']??'', ['accounts.google.com','https://accounts.google.com'],true)
            && (int)($claims['exp']??0)>time() && !empty($claims['sub'])
            && filter_var($claims['email']??'',FILTER_VALIDATE_EMAIL)
            && filter_var($claims['email_verified']??false,FILTER_VALIDATE_BOOLEAN), 'Google could not verify this account.',401);
        return $claims;
    }
}
