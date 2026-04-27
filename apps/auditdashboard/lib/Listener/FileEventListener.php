<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Listener;

use OCA\AuditDashboard\Service\AuditService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class FileEventListener implements IEventListener {
    private AuditService $auditService;
    private IUserSession $userSession;
    private IRequest $request;
    private LoggerInterface $logger;

    public function __construct(AuditService $auditService, IUserSession $userSession, IRequest $request, LoggerInterface $logger) {
        $this->auditService = $auditService;
        $this->userSession = $userSession;
        $this->request = $request;
        $this->logger = $logger;
    }

    private function isUserFilePath(string $path): bool {
        // Only audit files under a user's /files/ directory (skip internal/cache paths)
        return str_contains($path, '/files/');
    }

    private function isDownloadRequest(): bool {
        try {
            $uri = $this->request->getRequestUri();
            $method = $this->request->getMethod();

            if ($method !== 'GET') {
                return false;
            }

            // WebDAV file access (direct download)
            if (str_contains($uri, '/remote.php/dav/files/') || str_contains($uri, '/remote.php/webdav/')) {
                return true;
            }

            // Download endpoints
            if (str_contains($uri, '/download')) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function handle(Event $event): void {
        try {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : 'anonymous';

            if ($event instanceof BeforeNodeReadEvent) {
                $node = $event->getNode();
                if (!$this->isUserFilePath($node->getPath())) return;
                $action = $this->isDownloadRequest() ? 'file_downloaded' : 'file_read';
                $this->auditService->log($userId, $action, 'file', $node->getPath());
            } elseif ($event instanceof NodeCreatedEvent) {
                $node = $event->getNode();
                if (!$this->isUserFilePath($node->getPath())) return;
                $this->auditService->log($userId, 'file_created', 'file', $node->getPath(), json_encode(['size' => $node->getSize()]));
            } elseif ($event instanceof NodeWrittenEvent) {
                $node = $event->getNode();
                if (!$this->isUserFilePath($node->getPath())) return;
                $this->auditService->log($userId, 'file_written', 'file', $node->getPath(), json_encode(['size' => $node->getSize()]));
            } elseif ($event instanceof NodeDeletedEvent) {
                $node = $event->getNode();
                if (!$this->isUserFilePath($node->getPath())) return;
                $this->auditService->log($userId, 'file_deleted', 'file', $node->getPath());
            } elseif ($event instanceof NodeRenamedEvent) {
                $source = $event->getSource();
                $target = $event->getTarget();
                if (!$this->isUserFilePath($source->getPath()) && !$this->isUserFilePath($target->getPath())) return;
                $this->auditService->log($userId, 'file_renamed', 'file', $target->getPath(), json_encode(['from' => $source->getPath()]));
            } elseif ($event instanceof NodeCopiedEvent) {
                $source = $event->getSource();
                $target = $event->getTarget();
                if (!$this->isUserFilePath($source->getPath()) && !$this->isUserFilePath($target->getPath())) return;
                $this->auditService->log($userId, 'file_copied', 'file', $target->getPath(), json_encode(['from' => $source->getPath()]));
            } elseif ($event instanceof NodeTouchedEvent) {
                $node = $event->getNode();
                if (!$this->isUserFilePath($node->getPath())) return;
                $this->auditService->log($userId, 'file_touched', 'file', $node->getPath());
            }
        } catch (\Throwable $e) {
            $this->logger->error('AuditDashboard: Failed to log file event: ' . $e->getMessage());
        }
    }
}
