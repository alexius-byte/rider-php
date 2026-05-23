<?php

namespace Rider\System\Utils\Cache;

class DeleteCacheFiles
{
    public function __construct(private readonly array $dirs)
    {
    }

    public function delete(): int
    {
        $deleted = 0;
        foreach ($this->dirs as $dir) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        return $deleted;
    }
}