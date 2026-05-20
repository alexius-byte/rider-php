<?php

declare(strict_types=1);

namespace Rider\System\Utils\Upload;

enum AllowedType: string
{
    case Image = 'image';
    case Zip = 'zip';
    case Pdf = 'pdf';
    case Video = 'video';
    case Document = 'document';

    public function mimeExtensionMap(): array
    {
        return match ($this) {
            self::Image => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ],
            self::Zip => [
                'application/zip' => 'zip',
                'application/x-zip-compressed' => 'zip',
            ],
            self::Pdf => [
                'application/pdf' => 'pdf',
            ],
            self::Video => [
                'video/mp4' => 'mp4',
                'video/mpeg' => 'mpeg',
                'video/quicktime' => 'mov',
                'video/x-msvideo' => 'avi',
            ],
            self::Document => [
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            ],
        };
    }

    public function mimeTypes(): array
    {
        return array_keys($this->mimeExtensionMap());
    }

    public function extensionForMime(string $mime): string
    {
        return $this->mimeExtensionMap()[$mime];
    }
}
