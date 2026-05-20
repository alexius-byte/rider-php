<?php

declare(strict_types=1);

namespace Rider\System\Utils\Upload;

use Rider\System\Utils\Upload\Exception\UploadException;

class UploadFiles
{
    private const DEFAULT_MAX_MB = 5;

    private readonly string $storageDir;
    private readonly int $maxBytes;

    public function __construct(string $storageDir, int $maxMb = self::DEFAULT_MAX_MB)
    {
        $this->maxBytes = $maxMb * 1024 * 1024;
        $real = realpath($storageDir);

        if ($real === false || !is_dir($real)) {
            throw new UploadException("Invalid storage directory");
        }

        if (!is_writable($real)) {
            throw new UploadException("Storage directory is not writable");
        }

        $this->storageDir = $real;
    }

    public function upload(array $file, AllowedType $type): string
    {
        $this->assertNoUploadError($file);
        $this->assertFileSize($file);

        $mime = $this->detectMime($file['tmp_name']);

        $this->assertMimeAllowed($mime, $type);

        $destination = $this->buildDestination($type->extensionForMime($mime));

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new UploadException("Failed to move uploaded file");
        }

        return $destination;
    }

    private function assertNoUploadError(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new UploadException(match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => "File exceeds the allowed size",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            default => "File upload failed",
        });
    }

    private function assertFileSize(array $file): void
    {
        if (($file['size'] ?? 0) > $this->maxBytes) {
            $maxMb = $this->maxBytes / 1024 / 1024;
            throw new UploadException("File exceeds the maximum allowed size of {$maxMb}MB");
        }
    }

    private function detectMime(string $tmpPath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if ($mime === false) {
            throw new UploadException("Unable to determine file MIME type");
        }

        return $mime;
    }

    private function assertMimeAllowed(string $mime, AllowedType $type): void
    {
        if (!in_array($mime, $type->mimeTypes(), true)) {
            throw new UploadException("File type is not allowed");
        }
    }

    private function buildDestination(string $extension): string
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        return $this->storageDir . DIRECTORY_SEPARATOR . $filename;
    }
}