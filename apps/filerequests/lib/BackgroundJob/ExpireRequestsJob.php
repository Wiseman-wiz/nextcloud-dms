<?php

declare(strict_types=1);

namespace OCA\FileRequests\BackgroundJob;

use OCA\FileRequests\Service\RequestService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ExpireRequestsJob extends TimedJob {
    private RequestService $service;
    private LoggerInterface $logger;

    public function __construct(ITimeFactory $time, RequestService $service, LoggerInterface $logger) {
        parent::__construct($time);
        $this->service = $service;
        $this->logger = $logger;
        // Run every hour
        $this->setInterval(3600);
    }

    protected function run($argument): void {
        try {
            $expired = $this->service->getExpiredRequests();
            foreach ($expired as $request) {
                $this->service->expireRequest($request);
                $this->logger->info('FileRequests: Expired request #' . $request->getId());
            }
        } catch (\Throwable $e) {
            $this->logger->error('FileRequests: Error expiring requests: ' . $e->getMessage());
        }
    }
}
