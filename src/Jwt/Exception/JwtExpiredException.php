<?php

declare(strict_types=1);

namespace Rider\System\Jwt\Exception;

class JwtExpiredException extends JwtException
{
    private readonly object $payload;

    public function __construct(object $payload)
    {
        parent::__construct('Token expired');
        $this->payload = $payload;
    }

    public function getPayload(): object
    {
        return $this->payload;
    }
}
