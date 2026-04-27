<?php

declare(strict_types=1);

namespace OCA\FileRequests\AppInfo;

use OCA\FileRequests\Listener\FileDownloadListener;
use OCA\FileRequests\Middleware\FileRequestAccessMiddleware;
use OCA\FileRequests\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

class Application extends App implements IBootstrap {
    public const APP_ID = 'filerequests';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);
        $context->registerMiddleware(FileRequestAccessMiddleware::class);
        $context->registerEventListener(BeforeNodeReadEvent::class, FileDownloadListener::class);
    }

    public function boot(IBootContext $context): void {
        $serverContainer = $context->getServerContainer();
        $userSession = $serverContainer->get(IUserSession::class);
        $groupManager = $serverContainer->get(IGroupManager::class);

        $user = $userSession->getUser();
        if ($user === null) {
            return;
        }

        $uid = $user->getUID();
        $hasAccess = false;
        foreach (FileRequestAccessMiddleware::ALLOWED_GROUPS as $group) {
            if ($groupManager->isInGroup($uid, $group)) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            return;
        }

        $navigationManager = $serverContainer->get(INavigationManager::class);
        $urlGenerator = $serverContainer->get(IURLGenerator::class);

        $navigationManager->add([
            'id' => self::APP_ID,
            'name' => 'File Requests',
            'href' => $urlGenerator->linkToRoute('filerequests.page.index'),
            'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
            'order' => 80,
        ]);
    }
}
