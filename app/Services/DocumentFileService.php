<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * @return array{disk:string,path:string,original_name:string,mime_type:?string,size_bytes:int}
     */
    public function storeTemporaryUploadedFile(UploadedFile $file, ?int $userId = null): array
    {
        $disk = 'local';
        $directory = 'temp/document-upload-drafts/'.($userId ?: 'guest').'/'.now()->format('Y/m/d');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $file->storeAs($directory, $filename, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function promoteTemporaryFile(string $tempPath, ?string $tempDisk = 'local', ?string $preferredFilename = null): string
    {
        $sourceDisk = $tempDisk ?: 'local';
        $targetDisk = $this->getStorageDisk();

        $contents = Storage::disk($sourceDisk)->get($tempPath);
        $extension = pathinfo($preferredFilename ?: $tempPath, PATHINFO_EXTENSION);
        $basename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $targetDirectory = trim((string) config('documents.files.storage_path', 'documents'), '/');
        $targetPath = $targetDirectory.'/'.$basename;

        Storage::disk($targetDisk)->put($targetPath, $contents);

        if ($this->isEncryptionEnabled()) {
            $this->encryptStoredFile($targetDisk, $targetPath);
        }

        if (Storage::disk($sourceDisk)->exists($tempPath)) {
            Storage::disk($sourceDisk)->delete($tempPath);
        }

        return $targetPath;
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

    public function downloadResponse(string $path): StreamedResponse
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

    public function inlineResponse(string $path): Response|StreamedResponse
    {
        $disk = $this->getStorageDisk();
        $filename = basename($path);
        $mimeType = $this->resolveMimeType($disk, $path);

        if (! $this->isEncryptionEnabled()) {
            try {
                return Storage::disk($disk)->response(
                    $path,
                    $filename,
                    ['Content-Type' => $mimeType],
                    'inline'
                );
            } catch (\Throwable) {
                return response()->stream(function () use ($disk, $path) {
                    $stream = Storage::disk($disk)->readStream($path);

                    if (! is_resource($stream)) {
                        throw new FileNotFoundException('No se pudo abrir el archivo para previsualización.');
                    }

                    fpassthru($stream);
                    fclose($stream);
                }, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="'.$filename.'"',
                ]);
            }
        }

        $contents = $this->decryptStoredFile($disk, $path);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function resolveMimeType(string $disk, string $path): string
    {
        try {
            return Storage::disk($disk)->mimeType($path) ?: $this->guessMimeTypeFromExtension($path);
        } catch (\Throwable) {
            return $this->guessMimeTypeFromExtension($path);
        }
    }

    private function guessMimeTypeFromExtension(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
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
