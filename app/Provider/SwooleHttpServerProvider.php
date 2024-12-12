<?php

namespace App\Provider;

use App\Core\HttpServerProviderInterface;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Chubbyphp\Swoole\Psr7\ServerRequestFactory;
use Chubbyphp\Swoole\Psr7\ResponseFactory;
use Swoole\Http\Server as SwooleHttpServer;
use Tnapf\Router\Router;

class SwooleHttpServerProvider implements HttpServerProviderInterface
{
    private SwooleHttpServer $server;
    private array $context = [];
    private ?Router $router = null;
    private Logger $logger;

    public function __construct(array $context = [], ?Logger $logger = null)
    {
        $this->server = new SwooleHttpServer("0.0.0.0", 9501);
        $this->context = $context;
        $this->logger = $logger ?? $this->createLogger();
    }

    /**
     * Runs the HTTP server.
     *
     * @param string $uriAddress
     * @throws Exception
     */
    public function run(string $uriAddress): void
    {
        $this->server->set([
            'worker_num' => 4,
            'daemonize' => false,
        ]);

        $this->server->on('start', function () use ($uriAddress) {
            echo "Server running at {$uriAddress}\n";
        });

        $this->server->on('request', function ($swooleRequest, $swooleResponse) {
            $this->handleRequest($swooleRequest, $swooleResponse);
        });

        $this->server->start();
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
            throw new Exception("Router not initialized. Use setRouterFromFile to load routes.");
        }

        $this->logRequest($request);

        $response = $this->router->run($request);

        if (!$response) {
            return (new ResponseFactory())->createResponse(404)
                ->withHeader('Content-Type', 'text/plain')
                ->getBody()->write('Result Not Found');
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
            throw new Exception("Routes file not found: {$fileLoc}");
        }

        $this->router = require $fileLoc;

        if (!$this->router instanceof Router) {
            throw new Exception("Invalid router instance in file: {$fileLoc}");
        }
    }

    /**
     * Handles the Swoole HTTP request and sends a PSR-7 response.
     *
     * @param \Swoole\Http\Request $swooleRequest
     * @param \Swoole\Http\Response $swooleResponse
     */
    private function handleRequest($swooleRequest, $swooleResponse): void
    {
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();

        try {
            $psrRequest = $requestFactory->createServerRequestFromSwoole($swooleRequest);
            $psrResponse = $this->handleHttpRequest($psrRequest);

            // Send response back to Swoole
            $swooleResponse->status($psrResponse->getStatusCode());
            foreach ($psrResponse->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $swooleResponse->header($name, $value);
                }
            }
            $swooleResponse->end((string)$psrResponse->getBody());
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $swooleResponse->status(500);
            $swooleResponse->end('Internal Server Error');
        }
    }

    /**
     * Log the HTTP request.
     *
     * @param ServerRequestInterface $request
     */
    private function logRequest(ServerRequestInterface $request): void
    {
        $logMessage = sprintf(
            '[%s] %s %s',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            (string)$request->getUri()
        );
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
}
