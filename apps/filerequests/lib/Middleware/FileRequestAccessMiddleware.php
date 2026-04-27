<?php

declare(strict_types=1);

namespace OCA\FileRequests\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IGroupManager;
use OCP\IUserSession;

class FileRequestAccessMiddleware extends Middleware {
    public const GROUP_REQUESTERS = 'File Requesters';
    public const GROUP_APPROVERS = 'File Approvers';
    public const ALLOWED_GROUPS = [self::GROUP_REQUESTERS, self::GROUP_APPROVERS];

    private IUserSession $userSession;
    private IGroupManager $groupManager;

    public function __construct(IUserSession $userSession, IGroupManager $groupManager) {
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    public function beforeController($controller, $methodName): void {
        $user = $this->userSession->getUser();

        if ($user === null) {
            throw new AccessDeniedException('Authentication required');
        }

        $uid = $user->getUID();

        foreach (self::ALLOWED_GROUPS as $group) {
            if ($this->groupManager->isInGroup($uid, $group)) {
                return;
            }
        }

        throw new AccessDeniedException('Access restricted to File Requesters and File Approvers');
    }

    public function afterException($controller, $methodName, \Exception $exception): \OCP\AppFramework\Http\Response {
        if ($exception instanceof AccessDeniedException) {
            if ($methodName === 'index' && $controller instanceof \OCA\FileRequests\Controller\PageController) {
                $response = new TemplateResponse(
                    'core',
                    'error',
                    ['errors' => [['error' => 'Access denied', 'hint' => 'This page is restricted to File Requesters and File Approvers.']]],
                    'error'
                );
                $response->setStatus(Http::STATUS_FORBIDDEN);
                return $response;
            }

            return new JSONResponse(
                ['error' => 'Access restricted to File Requesters and File Approvers'],
                Http::STATUS_FORBIDDEN
            );
        }

        throw $exception;
    }
}
