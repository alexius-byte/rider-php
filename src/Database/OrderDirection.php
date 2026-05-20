<?php

declare(strict_types=1);

namespace Rider\System\Database;

enum OrderDirection: string
{
    case ASC  = 'ASC';
    case DESC = 'DESC';
}
