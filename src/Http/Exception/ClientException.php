<?php

declare(strict_types=1);

namespace Rider\System\Http\Exception;

use Rider\System\Http\ClientResponse;
use RuntimeException;

class ClientException extends RuntimeException
{
    private readonly ClientResponse|null $response;

    public function __construct(string $message, ClientResponse|null $response = null)
    {
        parent::__construct($message);
        $this->response = $response;
    }

    public function getResponse(): ClientResponse|null
    {
        return $this->response;
    }
}
