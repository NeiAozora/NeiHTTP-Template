<?php 

namespace App\Http;

use App\Core\HttpServerProviderInterface;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class HttpServer
{
    private Logger $logger;
    private ?HttpServerProviderInterface $provider = null;
    private string $routeLocation = '';

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? $this->createLogger();
    }

    public function setHttpServerProvider(HttpServerProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    public function run(string $uriAddress): void
    {
        $this->checkProvider();
        $this->provider->setRouterFromFile($this->routeLocation);
        $this->provider->run($uriAddress);
    }

    private function checkProvider(){
        if (!$this->provider) {
            throw new Exception("HTTP Server provider not set!");
        }
    }

    public function setRouterFromFile(string $fileLoc){
        $this->routeLocation = $fileLoc;
    }

    private function createLogger(): Logger
    {
        $logger = new Logger('HTTP');
        $logger->pushHandler(new StreamHandler('storage/logs/http.log', Logger::INFO));
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        return $logger;
    }
}