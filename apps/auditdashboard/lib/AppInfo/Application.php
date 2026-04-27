<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\AppInfo;

use OCA\AuditDashboard\Listener\FileEventListener;
use OCA\AuditDashboard\Listener\FileRequestEventListener;
use OCA\AuditDashboard\Listener\AuthEventListener;
use OCA\AuditDashboard\Listener\ShareEventListener;
use OCA\AuditDashboard\Listener\UserEventListener;
use OCA\AuditDashboard\Middleware\AuditGroupMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedOutEvent;
use OCP\User\Events\LoginFailedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\PasswordUpdatedEvent;
use OCA\FileRequests\Event\FileRequestEvent;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

class Application extends App implements IBootstrap {
    public const APP_ID = 'auditdashboard';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerMiddleware(AuditGroupMiddleware::class);

        // File events
        $context->registerEventListener(NodeCreatedEvent::class, FileEventListener::class);
        $context->registerEventListener(NodeWrittenEvent::class, FileEventListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, FileEventListener::class);
        $context->registerEventListener(NodeRenamedEvent::class, FileEventListener::class);
        $context->registerEventListener(NodeCopiedEvent::class, FileEventListener::class);
        $context->registerEventListener(NodeTouchedEvent::class, FileEventListener::class);
        $context->registerEventListener(BeforeNodeReadEvent::class, FileEventListener::class);

        // Auth events
        $context->registerEventListener(UserLoggedInEvent::class, AuthEventListener::class);
        $context->registerEventListener(UserLoggedOutEvent::class, AuthEventListener::class);
        $context->registerEventListener(LoginFailedEvent::class, AuthEventListener::class);

        // Share events
        $context->registerEventListener(ShareCreatedEvent::class, ShareEventListener::class);
        $context->registerEventListener(ShareDeletedEvent::class, ShareEventListener::class);

        // User management events
        $context->registerEventListener(UserCreatedEvent::class, UserEventListener::class);
        $context->registerEventListener(UserDeletedEvent::class, UserEventListener::class);
        $context->registerEventListener(PasswordUpdatedEvent::class, UserEventListener::class);

        // File request events (from filerequests app)
        $context->registerEventListener(FileRequestEvent::class, FileRequestEventListener::class);
    }

    public function boot(IBootContext $context): void {
        $serverContainer = $context->getServerContainer();
        $userSession = $serverContainer->get(IUserSession::class);
        $groupManager = $serverContainer->get(IGroupManager::class);

        $user = $userSession->getUser();
        if ($user === null || !$groupManager->isInGroup($user->getUID(), AuditGroupMiddleware::REQUIRED_GROUP)) {
            return;
        }

        $navigationManager = $serverContainer->get(INavigationManager::class);
        $urlGenerator = $serverContainer->get(IURLGenerator::class);

        $navigationManager->add([
            'id' => self::APP_ID,
            'name' => 'Audit Dashboard',
            'href' => $urlGenerator->linkToRoute('auditdashboard.page.index'),
            'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
            'order' => 90,
        ]);
    }
}
