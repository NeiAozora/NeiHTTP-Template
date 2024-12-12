<?php

namespace App\Provider;

use App\Core\HttpServerProviderInterface;
use App\Exception\InstanceException;
use App\Exception\ZanExceptionHandler;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Http\HttpServer as ReactHttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Tnapf\Router\Router;

class ReactHttpServerProvider implements HttpServerProviderInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var ReactHttpServer
     */
    private $server;

    /**
     * @var array
     */
    private $context = [];

    /**
     * @var Router|null
     */
    private $router;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * HttpServer constructor.
     *
     * @param LoopInterface|null $loop
     * @param array $context
     * @param Logger|null $logger
     */
    public function __construct(?LoopInterface $loop = null, array $context = [], ?Logger $logger = null)
    {
        $this->loop = $loop ?? Loop::get();
        $this->server = new ReactHttpServer([$this, 'handleHttpRequest']);
        $this->context = $context;
        $this->logger = $logger ?? $this->createLogger();
        $this->configureErrorHandling();
    }

    /**
     * Runs the HTTP server.
     *
     * @param string $uriAddress
     * @throws InstanceException
     */
    public function run(string $uriAddress): void
    {
        $this->checkInstance($uriAddress);
    }

    /**
     * Handles the HTTP request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handleHttpRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->router === null) {
            throw new Exception("Server fatal error: Routes file location not initialized or Object Router is not initialized");
        }

        $this->logRequest($request);

        $response = $this->router->run($request);

        if ($response === false) {
            return new Response(Response::STATUS_NOT_FOUND, ['Content-Type' => 'text/plain'], 'Result Not Found');
        }

        return $response;
    }

    /**
     * Set the location of the routes file.
     *
     * @param string $fileLoc
     * @throws Exception
     */
    public function setRouterFromFile(string $fileLoc): void
    {
        if (!file_exists($fileLoc)) {
            throw new Exception("Routes file location does not exist!");
        }

        $this->router = require $fileLoc;
    }

    /**
     * Configure error handling for the server.
     */
    private function configureErrorHandling()
    {
        $this->server->on('error', function (\Throwable $error) {
            // Customize the error response
            $response = new Response(
                Response::STATUS_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain'],
                'Internal Server Fatal Error'
            );
            $this->logger->error($error->__toString());
            // Send the customized error response
            $this->server->emit('response', [$response]);
        });
    }

    /**
     * Log the HTTP request.
     *
     * @param ServerRequestInterface $request
     */
    private function logRequest(ServerRequestInterface $request)
    {
        $logMessage = date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri();
        $this->logger->info($logMessage);
    }

    /**
     * Create a logger instance.
     *
     * @return Logger
     */
    private function createLogger(): Logger
    {
        $logger = new Logger('http');
        $logger->pushHandler(new StreamHandler('logs/http.log', Logger::INFO));
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        return $logger;
    }

    /**
     * Check if there is another instance running on the same URI address.
     *
     * @param string $uriAddress
     */
    private function checkInstance(string $uriAddress)
    {
        $browser = new Browser;

        $browser->get("http://" . $uriAddress)->then(function (ResponseInterface $response) {
            $e = new InstanceException("Address already in use by another instance");
            ZanExceptionHandler::handle($e);
        }, function () use ($uriAddress) {
            // which means there is no instance running on the same URI
            $socket = new SocketServer($uriAddress, $this->context, $this->loop);
            $this->server->listen($socket);
            echo "HTTP server running at {$uriAddress}\n";
            $this->loop->run();
        });
    }

    /**
     * Returns a quick response for debugging purposes.
     *
     * @return Response
     */
    public static function quickRespond(): Response
    {
        return new Response(Response::STATUS_OK, ["Content-Type" => "text/plain"], "quickResponse!");
    }
}
