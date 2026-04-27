<?php

declare(strict_types=1);

namespace OCA\FileRequests\Controller;

use OCA\FileRequests\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addStyle(Application::APP_ID, 'style');
        Util::addScript(Application::APP_ID, 'app');
        return new TemplateResponse(Application::APP_ID, 'index');
    }
}
