<?php

declare(strict_types=1);

namespace Rider\System\Paginator;

final class Paginator
{
    private const MAX_PER_PAGE = 1_000;

    public function create(callable $query, int $page, int $perPage, ?callable $count = null): Page
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);

        if ($count !== null) {
            $total = (int) $count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            if ($page > $lastPage) {
                return $this->emptyPage($total, $perPage, $page, $lastPage);
            }

            $offset = ($page - 1) * $perPage;
            $data = $query($perPage, $offset);
            if (!is_array($data)) {
                $data = [];
            }
        } else {
            $all = $query();
            if (!is_array($all)) {
                $all = [];
            }
            $total = count($all);
            $lastPage = max(1, (int) ceil($total / $perPage));

            if ($page > $lastPage) {
                return $this->emptyPage($total, $perPage, $page, $lastPage);
            }

            $offset = ($page - 1) * $perPage;
            $data = array_slice($all, $offset, $perPage);
        }

        return new Page(
            data: $data,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            lastPage: $lastPage,
            from: $total > 0 ? $offset + 1 : 0,
            to: min($offset + $perPage, $total),
            hasNext: $page < $lastPage,
            hasPrev: $page > 1,
        );
    }

    private function emptyPage(int $total, int $perPage, int $page, int $lastPage): Page
    {
        return new Page(
            data: [],
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            lastPage: $lastPage,
            from: 0,
            to: 0,
            hasNext: false,
            hasPrev: false,
        );
    }
}
