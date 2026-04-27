<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Listener;

use OCA\AuditDashboard\Service\AuditService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class UserEventListener implements IEventListener {
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
            $currentUser = $this->userSession->getUser();
            $currentUserId = $currentUser ? $currentUser->getUID() : 'system';

            if ($event instanceof UserCreatedEvent) {
                $this->auditService->log(
                    $currentUserId,
                    'user_created',
                    'user',
                    $event->getUser()->getUID(),
                    json_encode(['displayName' => $event->getUser()->getDisplayName()])
                );
            } elseif ($event instanceof UserDeletedEvent) {
                $this->auditService->log(
                    $currentUserId,
                    'user_deleted',
                    'user',
                    $event->getUser()->getUID()
                );
            } elseif ($event instanceof PasswordUpdatedEvent) {
                $this->auditService->log(
                    $currentUserId,
                    'password_changed',
                    'user',
                    $event->getUser()->getUID()
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('AuditDashboard: Failed to log user event: ' . $e->getMessage());
        }
    }
}
