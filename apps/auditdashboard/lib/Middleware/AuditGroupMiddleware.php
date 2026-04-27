<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Middleware;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\IGroupManager;
use OCP\IUserSession;

class AuditGroupMiddleware extends Middleware {
    public const REQUIRED_GROUP = 'Auditor';

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

        if (!$this->groupManager->isInGroup($uid, self::REQUIRED_GROUP)) {
            throw new AccessDeniedException('Access restricted to Auditor group members');
        }
    }

    public function afterException($controller, $methodName, \Exception $exception): \OCP\AppFramework\Http\Response {
        if ($exception instanceof AccessDeniedException) {
            if ($methodName === 'index') {
                $response = new TemplateResponse(
                    'core',
                    'error',
                    ['errors' => [['error' => 'Access denied', 'hint' => 'This page is restricted to Auditor group members.']]],
                    'error'
                );
                $response->setStatus(Http::STATUS_FORBIDDEN);
                return $response;
            }

            return new JSONResponse(
                ['error' => 'Access restricted to Auditor group members'],
                Http::STATUS_FORBIDDEN
            );
        }

        throw $exception;
    }
}
