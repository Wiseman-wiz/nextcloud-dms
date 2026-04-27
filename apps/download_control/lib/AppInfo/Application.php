<?php

declare(strict_types=1);

namespace OCA\DownloadControl\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;

class Application extends App implements IBootstrap {
    public const APP_ID = 'download_control';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Nothing to register in the DI container for now
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (IEventDispatcher $eventDispatcher): void {
            $eventDispatcher->addListener(
                BeforeTemplateRenderedEvent::class,
                function (BeforeTemplateRenderedEvent $event): void {
                    // Only inject into full-page HTML responses (not AJAX sub-requests)
                    if (!$event->isLoggedIn()) {
                        return;
                    }
                    Util::addScript(self::APP_ID, 'files-hook');
                    Util::addStyle(self::APP_ID, 'styles');
                }
            );
        });
    }
}
