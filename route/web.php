<?php

use Tnapf\Router\Routing\RouteRunner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tnapf\Router\Exceptions\HttpNotFound;
use App\Http\Controller\DiscordImageTranceiverController;
use App\Http\Controller\IndexController;
use React\Http\Message\Response;

$router = new \Tnapf\Router\Router();

$router->get(
    "/",
    Closure::fromCallable([IndexController::class, 'index'])
);


$router->catch(
    HttpNotFound::class,
    static function (
      ServerRequestInterface $request,
      ResponseInterface $response,
      RouteRunner $route
    ) {
      $response = new Response(Response::STATUS_NOT_FOUND, [
        "Content-Type" => "text/html"
    ], "{$request->getUri()->getPath()} does not exist");
    return $response;
    }
  );

return $router;