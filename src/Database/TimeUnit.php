<?php

declare(strict_types=1);

namespace Rider\System\Database;

enum TimeUnit: string
{
    case SECONDS = 'seconds';
    case MINUTES = 'minutes';
    case HOURS   = 'hours';
    case DAYS    = 'days';
}
