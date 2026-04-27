<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Listener;

use OCA\AuditDashboard\Service\AuditService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\LoginFailedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedOutEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class AuthEventListener implements IEventListener {
    private AuditService $auditService;
    private LoggerInterface $logger;

    public function __construct(AuditService $auditService, LoggerInterface $logger) {
        $this->auditService = $auditService;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        try {
            if ($event instanceof UserLoggedInEvent) {
                $user = $event->getUser();
                $this->auditService->log(
                    $user->getUID(),
                    'login_success',
                    'auth',
                    'Login',
                    json_encode(['loginName' => $event->getLoginName()])
                );
            } elseif ($event instanceof UserLoggedOutEvent) {
                $user = $event->getUser();
                $userId = $user ? $user->getUID() : 'unknown';
                $this->auditService->log($userId, 'logout', 'auth', 'Logout');
            } elseif ($event instanceof LoginFailedEvent) {
                $this->auditService->log(
                    $event->getLoginName(),
                    'login_failed',
                    'auth',
                    'Login Failed',
                    json_encode(['loginName' => $event->getLoginName()])
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('AuditDashboard: Failed to log auth event: ' . $e->getMessage());
        }
    }
}
