<?php

declare(strict_types=1);

namespace Rider\System\Paginator;

final class Page
{
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $from,
        public readonly int $to,
        public readonly bool $hasNext,
        public readonly bool $hasPrev,
    ) {}
}
