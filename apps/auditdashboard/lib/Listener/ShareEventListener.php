<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Listener;

use OCA\AuditDashboard\Service\AuditService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class ShareEventListener implements IEventListener {
    private AuditService $auditService;
    private IUserSession $userSession;
    private LoggerInterface $logger;

    public function __construct(AuditService $auditService, IUserSession $userSession, LoggerInterface $logger) {
        $this->auditService = $auditService;
        $this->userSession = $userSession;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        try {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : 'anonymous';

            if ($event instanceof ShareCreatedEvent) {
                $share = $event->getShare();
                $nodePath = '';
                try {
                    $nodePath = $share->getNode()->getPath();
                } catch (\Throwable $e) {
                    $nodePath = $share->getTarget();
                }
                $this->auditService->log(
                    $userId,
                    'share_created',
                    'share',
                    $nodePath ?: $share->getTarget(),
                    json_encode([
                        'shareType' => $share->getShareType(),
                        'sharedWith' => $share->getSharedWith(),
                        'permissions' => $share->getPermissions(),
                        'target' => $share->getTarget(),
                    ])
                );
            } elseif ($event instanceof ShareDeletedEvent) {
                $share = $event->getShare();
                $nodePath = '';
                try {
                    $nodePath = $share->getNode()->getPath();
                } catch (\Throwable $e) {
                    $nodePath = $share->getTarget();
                }
                $this->auditService->log(
                    $userId,
                    'share_deleted',
                    'share',
                    $nodePath ?: $share->getTarget(),
                    json_encode([
                        'shareType' => $share->getShareType(),
                        'sharedWith' => $share->getSharedWith(),
                        'target' => $share->getTarget(),
                    ])
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('AuditDashboard: Failed to log share event: ' . $e->getMessage());
        }
    }
}
