<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaLifecycle
{
    private array $uploads = [];

    private array $deletions = [];

    public function store(UploadedFile $file, string $directory, string $disk): string
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() === 0) {
            throw new RuntimeException('Media writes require a database transaction.');
        }
        $path = $file->hashName($directory);
        $storage = Storage::disk($disk);
        // Reusing an UploadedFile or a hash collision must never overwrite a valid existing file.
        while ($storage->exists($path)) {
            $path = trim($directory, '/').'/'.Str::random(40).'.'.$file->guessExtension();
        }
        $key = $disk.':'.$path;
        $this->uploads[$key] = ['disk' => $disk, 'path' => $path, 'connection' => $connection->getName(), 'level' => $connection->transactionLevel(), 'failed' => true];
        try {
            if (! $storage->putFileAs($directory, $file, basename($path))) {
                throw new RuntimeException('Upload could not be stored.');
            }
            $this->uploads[$key]['failed'] = false;

            return $path;
        } catch (Throwable $error) {
            if ($this->remove($disk, $path)) {
                unset($this->uploads[$key]);
            }
            throw $error;
        }
    }

    public function deleteAfterCommit(?string $path, string $disk): void
    {
        if (! $this->localPath($path)) {
            return;
        }
        $connection = DB::connection();
        if ($connection->transactionLevel() === 0) {
            throw new RuntimeException('Media replacement requires a database transaction.');
        }
        $this->deletions[$disk.':'.$path] = ['disk' => $disk, 'path' => $path, 'connection' => $connection->getName(), 'level' => $connection->transactionLevel()];
    }

    public function committed(Connection $connection): void
    {
        $level = $connection->transactionLevel();
        foreach ($this->uploads as $key => $entry) {
            if ($entry['connection'] !== $connection->getName()) {
                continue;
            }
            if ($level > 0) {
                $this->uploads[$key]['level'] = min($entry['level'], $level);

                continue;
            }
            unset($this->uploads[$key]);
            if ($entry['failed']) {
                $this->remove($entry['disk'], $entry['path']);
            }
        }
        foreach ($this->deletions as $key => $entry) {
            if ($entry['connection'] !== $connection->getName()) {
                continue;
            }
            if ($level > 0) {
                $this->deletions[$key]['level'] = min($entry['level'], $level);

                continue;
            }
            unset($this->deletions[$key]);
            try {
                if (! $this->referenced($entry['disk'], $entry['path'])) {
                    $this->remove($entry['disk'], $entry['path']);
                }
            } catch (Throwable $error) {
                $this->logFailure($entry['disk'], $entry['path'], $error);
            }
        }
    }

    public function rolledBack(Connection $connection): void
    {
        foreach ($this->uploads as $key => $entry) {
            if ($entry['connection'] === $connection->getName() && $entry['level'] > $connection->transactionLevel()) {
                unset($this->uploads[$key]);
                $this->remove($entry['disk'], $entry['path']);
            }
        }
        foreach ($this->deletions as $key => $entry) {
            if ($entry['connection'] === $connection->getName() && $entry['level'] > $connection->transactionLevel()) {
                unset($this->deletions[$key]);
            }
        }
    }

    private function localPath(?string $path): bool
    {
        return filled($path) && ! str_starts_with($path, '/') && ! preg_match('~(^|/)\.\.(/|$)|[:\\\\]~', $path);
    }

    private function remove(string $disk, string $path): bool
    {
        try {
            if (! $this->localPath($path) || (! in_array($disk, ['public', 'profile_images', 'messages'], true) && ! ($disk === 'local' && preg_match('#^gcash-proofs/[A-Za-z0-9-]+\.(jpg|jpeg|png|webp|pdf)$#D', $path)))) {
                throw new RuntimeException('Invalid cleanup target.');
            }
            if ($disk === 'local' && $this->referenced($disk, $path)) {
                return true;
            }
            if (Storage::disk($disk)->exists($path) && ! Storage::disk($disk)->delete($path)) {
                throw new RuntimeException('Cleanup could not complete.');
            }

            return true;
        } catch (Throwable $error) {
            $this->logFailure($disk, $path, $error);

            return false;
        }
    }

    private function logFailure(string $disk, string $path, Throwable $error): void
    {
        try {
            Log::warning('Media cleanup failed', ['disk' => $disk, 'path_hash' => hash('sha256', $path), 'exception_type' => $error::class]);
        } catch (Throwable) {
            // A logging outage must not mask the original operation/cleanup failure.
        }
    }

    private function referenced(string $disk, string $path): bool
    {
        $references = match ($disk) {
            'local' => ['gcash_proofs' => ['path']],
            'profile_images' => ['users' => ['profile_picture']],
            'messages' => ['messages' => ['attachment_path']],
            'public' => ['users' => ['id_file', 'selfie_file', 'license_file', 'business_permit_file', 'or_file', 'cr_file', 'resume_file', 'company_logo', 'id_photo_path', 'selfie_photo_path', 'license_photo_path', 'or_cr_path'],
                'settings' => ['hero_image', 'hero_image_2'], 'products' => ['image', 'video'], 'complaints' => ['evidence_path'],
                'document_update_requests' => ['id_file', 'business_permit_file'], 'rider_profiles' => ['id_file', 'selfie_file', 'license_file', 'or_file', 'cr_file']],
            default => [],
        };
        foreach ($references as $table => $fields) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = array_intersect($fields, Schema::getColumnListing($table));
            if ($columns && DB::table($table)->where(fn ($q) => collect($columns)->each(fn ($column) => $q->orWhere($column, $path)))->exists()) {
                return true;
            }
        }
        if ($disk === 'public') {
            foreach (['products' => ['images', 'variations'], 'account_update_requests' => ['requested_documents']] as $table => $fields) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $columns = array_intersect($fields, Schema::getColumnListing($table));
                if ($columns && DB::table($table)->where(function ($query) use ($columns, $path) {
                    foreach ($columns as $column) {
                        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);
                        if (DB::connection()->getDriverName() === 'mysql') {
                            $query->orWhereRaw("JSON_SEARCH($wrapped, 'one', ?, '!') IS NOT NULL", [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path)]);
                        } else {
                            // SQLite fixtures: JSON tree handles nested/escaped document paths.
                            $query->orWhereRaw("EXISTS (SELECT 1 FROM json_tree($wrapped) WHERE type = 'text' AND value = ?)", [$path]);
                        }
                    }
                })->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
