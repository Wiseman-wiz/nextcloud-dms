<?php

declare(strict_types=1);

namespace OCA\FileRequests\Controller;

use OCA\FileRequests\AppInfo\Application;
use OCA\FileRequests\Service\RequestService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class RequestController extends Controller {
    private RequestService $service;
    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private IUserManager $userManager;

    public function __construct(
        IRequest $request,
        RequestService $service,
        IUserSession $userSession,
        IGroupManager $groupManager,
        IUserManager $userManager
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->service = $service;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
    }

    private function currentUserId(): string {
        return $this->userSession->getUser()->getUID();
    }

    private function isApprover(): bool {
        return $this->service->isApprover($this->currentUserId());
    }

    private function getDisplayName(string $userId): string {
        $user = $this->userManager->get($userId);
        return $user ? $user->getDisplayName() : $userId;
    }

    private function enrichRequest(array $data): array {
        $data['requesterDisplayName'] = $this->getDisplayName($data['requesterId']);
        $data['custodianDisplayName'] = $data['custodianId'] ? $this->getDisplayName($data['custodianId']) : '';
        return $data;
    }

    private function enrichActivity(array $data): array {
        $data['userDisplayName'] = $data['userId'] === 'system' ? 'System' : $this->getDisplayName($data['userId']);
        return $data;
    }

    /**
     * @NoAdminRequired
     */
    public function index(): JSONResponse {
        $userId = $this->currentUserId();
        $role = $this->request->getParam('role');
        $status = $this->request->getParam('status');
        $limit = (int)($this->request->getParam('limit') ?: 50);
        $offset = (int)($this->request->getParam('offset') ?: 0);
        $admin = $this->request->getParam('admin') === '1';
        $incoming = $this->request->getParam('incoming') === '1';

        if (($admin || $incoming) && $this->isApprover()) {
            // File Approvers see all requests in the Incoming tab
            $requests = $this->service->getAllRequests($status ?: null, $limit, $offset);
        } elseif ($role === 'requester') {
            $requests = $this->service->getRequestsByUser($userId, 'requester', $status ?: null, $limit, $offset);
        } else {
            $requests = $this->service->getRequestsByUser($userId, $role ?: null, $status ?: null, $limit, $offset);
        }

        return new JSONResponse([
            'requests' => array_map(fn($r) => $this->enrichRequest($r->toArray()), $requests),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function create(): JSONResponse {
        $userId = $this->currentUserId();
        $title = trim((string)$this->request->getParam('title'));
        $description = $this->request->getParam('description');
        $extraFields = [
            'department' => $this->request->getParam('department'),
            'province' => $this->request->getParam('province'),
            'municipalityCity' => $this->request->getParam('municipalityCity'),
            'project' => $this->request->getParam('project'),
            'permitDocumentName' => $this->request->getParam('permitDocumentName'),
            'dateNeeded' => $this->request->getParam('dateNeeded'),
        ];

        if (empty($title)) {
            return new JSONResponse(['error' => 'Title is required'], Http::STATUS_BAD_REQUEST);
        }

        if (mb_strlen($title) > 256) {
            return new JSONResponse(['error' => 'Title must be 256 characters or fewer'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $request = $this->service->createRequest($userId, $title, $description, $extraFields);
            return new JSONResponse(['request' => $request->toArray()], Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): JSONResponse {
        try {
            $request = $this->service->getRequest($id);
            $userId = $this->currentUserId();

            if ($request->getRequesterId() !== $userId && !$this->isApprover() && !$this->isAdmin()) {
                return new JSONResponse(['error' => 'Not authorized'], Http::STATUS_FORBIDDEN);
            }

            $fulfillments = $this->service->getFulfillments($id);

            return new JSONResponse([
                'request' => $this->enrichRequest($request->toArray()),
                'fulfillments' => array_map(fn($f) => $f->toArray(), $fulfillments),
            ]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function accept(int $id): JSONResponse {
        try {
            $request = $this->service->acceptRequest($id, $this->currentUserId());
            return new JSONResponse(['request' => $request->toArray()]);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function reject(int $id): JSONResponse {
        $reason = trim((string)$this->request->getParam('reason'));
        if (empty($reason)) {
            return new JSONResponse(['error' => 'Rejection reason is required'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $request = $this->service->rejectRequest($id, $this->currentUserId(), $reason);
            return new JSONResponse(['request' => $request->toArray()]);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function cancel(int $id): JSONResponse {
        $reason = trim((string)$this->request->getParam('reason'));
        if (empty($reason)) {
            return new JSONResponse(['error' => 'Cancellation reason is required'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $request = $this->service->cancelRequest($id, $this->currentUserId(), $reason);
            return new JSONResponse(['request' => $request->toArray()]);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function fulfill(int $id): JSONResponse {
        $files = $this->request->getParam('files');
        if (!is_array($files) || empty($files)) {
            return new JSONResponse(['error' => 'At least one file is required'], Http::STATUS_BAD_REQUEST);
        }

        $expiryDate = $this->request->getParam('expiryDate');
        if ($expiryDate !== null) {
            $expiryDate = trim((string)$expiryDate);
            if ($expiryDate === '') {
                $expiryDate = null;
            }
        }

        try {
            $fulfillments = $this->service->fulfillRequest($id, $this->currentUserId(), $files, $expiryDate);
            return new JSONResponse([
                'fulfillments' => array_map(fn($f) => $f->toArray(), $fulfillments),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function activity(int $id): JSONResponse {
        try {
            $request = $this->service->getRequest($id);
            $userId = $this->currentUserId();

            if ($request->getRequesterId() !== $userId && !$this->isApprover() && !$this->isAdmin()) {
                return new JSONResponse(['error' => 'Not authorized'], Http::STATUS_FORBIDDEN);
            }

            $activity = $this->service->getActivity($id);
            return new JSONResponse([
                'activity' => array_map(fn($a) => $this->enrichActivity($a->toArray()), $activity),
            ]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function searchUsers(): JSONResponse {
        $term = (string)$this->request->getParam('term', '');
        if (mb_strlen($term) < 1) {
            return new JSONResponse(['users' => []]);
        }
        $users = $this->service->searchUsers($term);
        return new JSONResponse(['users' => $users]);
    }

    /**
     * @NoAdminRequired
     */
    public function stats(): JSONResponse {
        $userId = $this->currentUserId();
        $isApproverUser = $this->isApprover();
        $stats = $this->service->getStats($userId, $isApproverUser);
        return new JSONResponse([
            'stats' => $stats,
            'isAdmin' => $isApproverUser,
            'isApprover' => $isApproverUser,
        ]);
    }
}
