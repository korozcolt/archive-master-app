<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class DocumentFileService
{
    public function isEncryptionEnabled(): bool
    {
        return (bool) config('documents.security.encrypt_files', false);
    }

    public function getStorageDisk(): string
    {
        if ($this->isEncryptionEnabled()) {
            return 'private';
        }

        return config('documents.files.storage_disk', 'local');
    }

    public function storeUploadedFile(UploadedFile $file): string
    {
        $disk = $this->getStorageDisk();
        $path = $file->store(config('documents.files.storage_path', 'documents'), $disk);

        if ($this->isEncryptionEnabled()) {
            $this->encryptStoredFile($disk, $path);
        }

        return $path;
    }

    public function replaceFile(?string $existingPath, UploadedFile $file): string
    {
        if ($existingPath) {
            $this->deleteFile($existingPath);
        }

        return $this->storeUploadedFile($file);
    }

    public function deleteFile(string $path): void
    {
        $disk = $this->getStorageDisk();
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function fileExists(string $path): bool
    {
        return Storage::disk($this->getStorageDisk())->exists($path);
    }

    public function downloadResponse(string $path)
    {
        $disk = $this->getStorageDisk();

        if (! $this->isEncryptionEnabled()) {
            return Storage::disk($disk)->download($path);
        }

        $contents = $this->decryptStoredFile($disk, $path);
        $filename = basename($path);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename);
    }

    private function encryptStoredFile(string $disk, string $path): void
    {
        $contents = Storage::disk($disk)->get($path);
        $encrypted = Crypt::encrypt($contents);
        Storage::disk($disk)->put($path, $encrypted);
    }

    private function decryptStoredFile(string $disk, string $path): string
    {
        $encrypted = Storage::disk($disk)->get($path);
        $decrypted = Crypt::decrypt($encrypted);

        if (! is_string($decrypted)) {
            throw new FileNotFoundException('No se pudo descifrar el archivo.');
        }

        return $decrypted;
    }
}
