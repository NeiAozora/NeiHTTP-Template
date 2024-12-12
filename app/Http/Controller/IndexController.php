<?php

namespace App\Http\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Tnapf\Router\Routing\RouteRunner;
use Psr\Http\Message\ResponseInterface;

class IndexController
{
    public static function index(ServerRequestInterface $request, ResponseInterface $response, RouteRunner $route): ResponseInterface
    {
        return view('welcome');
    }
}