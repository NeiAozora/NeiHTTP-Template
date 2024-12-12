<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HttpServerProviderInterface
{
    public function run(string $uriAddress): void;

    public function handleHttpRequest(ServerRequestInterface $request): ResponseInterface;

    public function setRouterFromFile(string $fileLoc): void;
}