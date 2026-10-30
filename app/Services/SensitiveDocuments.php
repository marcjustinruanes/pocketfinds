<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SensitiveDocuments
{
    public const DIRECTORIES = ['id_files', 'selfie_files', 'license_files', 'vehicle_docs', 'or_files', 'cr_files', 'resume_files'];
    public const FIELDS = ['id_file', 'selfie_file', 'license_file', 'or_file', 'cr_file', 'resume_file', 'id_photo_path', 'selfie_photo_path', 'license_photo_path', 'or_cr_path'];

    public static function safe(string $path): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)+$~D', $path)
            && ! preg_match('~(^|/)\.{1,2}(/|$)~', $path);
    }

    public static function directory(string $path): bool
    {
        return in_array(explode('/', $path)[0], self::DIRECTORIES, true);
    }

    public function paths(): array
    {
        $paths = [];
        foreach (['users', 'rider_profiles', 'document_update_requests'] as $table) {
            if (! Schema::hasTable($table)) continue;
            $fields = array_intersect(self::FIELDS, Schema::getColumnListing($table));
            if (! $fields) continue;
            foreach (DB::table($table)->select($fields)->cursor() as $row) {
                foreach ((array) $row as $path) if (is_string($path) && self::safe($path)) $paths[$path] = true;
            }
        }
        if (Schema::hasTable('account_update_requests')) {
            foreach (DB::table('account_update_requests')->select('requested_documents')->cursor() as $row) {
                foreach (json_decode($row->requested_documents ?? '{}', true) ?? [] as $field => $path) {
                    if (in_array($field, self::FIELDS, true) && is_string($path) && self::safe($path)) $paths[$path] = true;
                }
            }
        }
        return array_keys($paths);
    }

    public function url(?string $path): ?string
    {
        if (! $path) return null;
        if (self::directory($path) || in_array($path, $this->paths(), true)) {
            return route('sensitive-documents.show', ['token' => Crypt::encryptString($path)]);
        }
        return Storage::disk('public')->url($path);
    }

    public function authorize(User $actor, string $path): void
    {
        $actor = User::findOrFail($actor->id);
        abort_unless($actor->status === 'approved' && self::safe($path), 404);
        $owners = User::query();
        if (! $actor->is_admin) {
            $owners->where(function ($q) use ($actor) {
                $q->whereKey($actor->id);
                if ($actor->isLogisticsAdmin() && filled($actor->business_name)) {
                    $q->orWhere(fn ($q) => $q->where('business_name', $actor->business_name)->where(fn ($q) => $q->where('account_type', 'rider')->orWhere('logistics_role', 'hub_staff')));
                }
            });
        }
        $columns = array_intersect(self::FIELDS, Schema::getColumnListing('users'));
        $direct = clone $owners;
        if ($columns && $direct->where(fn ($q) => collect($columns)->each(fn ($field) => $q->orWhere($field, $path)))->exists()) return;
        foreach (['document_update_requests', 'rider_profiles'] as $table) {
            if (! Schema::hasTable($table)) continue;
            $fields = array_intersect(self::FIELDS, Schema::getColumnListing($table));
            if ($fields && DB::table($table)->whereIn('user_id', (clone $owners)->select('id'))->where(fn ($q) => collect($fields)->each(fn ($field) => $q->orWhere($field, $path)))->exists()) return;
        }
        if (Schema::hasTable('account_update_requests')) {
            foreach (DB::table('account_update_requests')->whereIn('user_id', (clone $owners)->select('id'))->select('requested_documents')->cursor() as $row) {
                foreach (json_decode($row->requested_documents ?? '{}', true) ?? [] as $field => $value) {
                    if (in_array($field, self::FIELDS, true) && $value === $path) return;
                }
            }
        }
        abort(404);
    }

    public function response(User $actor, string $token)
    {
        try { $path = Crypt::decryptString($token); } catch (\Throwable) { abort(404); }
        $this->authorize($actor, $path);
        $disk = Storage::disk('sensitive_documents');
        // Authenticated fallback supports a staged deployment; the migration removes public originals.
        if (! $disk->exists($path)) $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true), 415);
        \Illuminate\Support\Facades\Log::info('Sensitive document accessed', ['actor_id' => $actor->id, 'document_hash' => hash('sha256', $path)]);
        $response = response()->file($disk->path($path), ['Content-Type' => $mime,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'", 'Referrer-Policy' => 'no-referrer']);
        $response->setPrivate();
        $response->setContentDisposition('inline', 'document.'.($mime === 'application/pdf' ? 'pdf' : ($mime === 'image/jpeg' ? 'jpg' : substr($mime, 6))));
        return $response;
    }
}
