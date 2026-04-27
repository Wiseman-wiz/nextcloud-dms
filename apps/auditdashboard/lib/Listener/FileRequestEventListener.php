<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Listener;

use OCA\AuditDashboard\Service\AuditService;
use OCA\FileRequests\Event\FileRequestEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<FileRequestEvent> */
class FileRequestEventListener implements IEventListener {
    private AuditService $auditService;
    private LoggerInterface $logger;

    public function __construct(AuditService $auditService, LoggerInterface $logger) {
        $this->auditService = $auditService;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        if (!($event instanceof FileRequestEvent)) {
            return;
        }

        try {
            $request = $event->getRequest();

            $typeMap = [
                'created' => 'submitted',
                'fulfilled' => 'completed',
                'accepted' => 'accepted',
                'rejected' => 'rejected',
                'cancelled' => 'cancelled',
                'expired' => 'expired',
            ];
            $mappedType = $typeMap[$event->getType()] ?? $event->getType();
            $action = 'file_request_' . $mappedType;
            $target = $request->getTitle();

            $details = json_encode([
                'requestId' => $request->getId(),
                'requester' => $request->getRequesterId(),
                'custodian' => $request->getCustodianId(),
                'status' => $request->getStatus(),
                'message' => $event->getMessage(),
            ]);

            $this->auditService->log(
                $event->getActorId(),
                $action,
                'file_request',
                $target,
                $details
            );
        } catch (\Throwable $e) {
            $this->logger->error('AuditDashboard: Failed to log file request event: ' . $e->getMessage());
        }
    }
}
