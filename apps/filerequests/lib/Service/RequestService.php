<?php

declare(strict_types=1);

namespace OCA\FileRequests\Service;

use OCA\FileRequests\Db\FileRequest;
use OCA\FileRequests\Db\FileRequestMapper;
use OCA\FileRequests\Db\Fulfillment;
use OCA\FileRequests\Db\FulfillmentMapper;
use OCA\FileRequests\Db\RequestActivity;
use OCA\FileRequests\Db\RequestActivityMapper;
use OCA\FileRequests\Event\FileRequestEvent;
use OCA\FileRequests\Middleware\FileRequestAccessMiddleware;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\Lock\ILock;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Lock\LockContext;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class RequestService {
    private FileRequestMapper $requestMapper;
    private FulfillmentMapper $fulfillmentMapper;
    private RequestActivityMapper $activityMapper;
    private IShareManager $shareManager;
    private IRootFolder $rootFolder;
    private INotificationManager $notificationManager;
    private IUserManager $userManager;
    private LoggerInterface $logger;
    private IEventDispatcher $eventDispatcher;
    private IGroupManager $groupManager;
    private ?ILockManager $lockManager;

    public function __construct(
        FileRequestMapper $requestMapper,
        FulfillmentMapper $fulfillmentMapper,
        RequestActivityMapper $activityMapper,
        IShareManager $shareManager,
        IRootFolder $rootFolder,
        INotificationManager $notificationManager,
        IUserManager $userManager,
        LoggerInterface $logger,
        IEventDispatcher $eventDispatcher,
        IGroupManager $groupManager
    ) {
        $this->requestMapper = $requestMapper;
        $this->fulfillmentMapper = $fulfillmentMapper;
        $this->activityMapper = $activityMapper;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->notificationManager = $notificationManager;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->groupManager = $groupManager;

        try {
            $this->lockManager = \OC::$server->get(ILockManager::class);
        } catch (\Throwable $e) {
            $this->lockManager = null;
        }
    }

    public function createRequest(string $requesterId, string $title, ?string $description, array $extraFields = []): FileRequest {
        $now = $this->now();

        $request = new FileRequest();
        $request->setRequesterId($requesterId);
        $request->setCustodianId('');
        $request->setTitle(trim($title));
        $request->setDescription($description ? trim($description) : null);
        $request->setStatus('pending');
        $request->setExpiresAt(null);
        $request->setCreatedAt($now);
        $request->setUpdatedAt($now);

        if (!empty($extraFields['department'])) {
            $request->setDepartment(trim($extraFields['department']));
        }
        if (!empty($extraFields['province'])) {
            $request->setProvince(trim($extraFields['province']));
        }
        if (!empty($extraFields['municipalityCity'])) {
            $request->setMunicipalityCity(trim($extraFields['municipalityCity']));
        }
        if (!empty($extraFields['project'])) {
            $request->setProject(trim($extraFields['project']));
        }
        if (!empty($extraFields['permitDocumentName'])) {
            $request->setPermitDocumentName(trim($extraFields['permitDocumentName']));
        }
        if (!empty($extraFields['dateNeeded'])) {
            $request->setDateNeeded(trim($extraFields['dateNeeded']));
        }

        $request = $this->requestMapper->insert($request);

        $this->logActivity((int)$request->getId(), $requesterId, 'created', 'Request created');
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_CREATED, $request, $requesterId, 'Request created'));

        // Notify all File Approvers
        $approverGroup = $this->groupManager->get(FileRequestAccessMiddleware::GROUP_APPROVERS);
        if ($approverGroup !== null) {
            foreach ($approverGroup->getUsers() as $approver) {
                if ($approver->getUID() !== $requesterId) {
                    $this->sendNotification($approver->getUID(), 'new_request', (int)$request->getId(), [
                        'requesterId' => $requesterId,
                        'title' => $request->getTitle(),
                    ]);
                }
            }
        }

        return $request;
    }

    public function acceptRequest(int $requestId, string $approverId): FileRequest {
        $request = $this->requestMapper->find($requestId);
        $this->verifyApprover($approverId);

        if ($request->getStatus() !== 'pending') {
            throw new \RuntimeException('Only pending requests can be accepted');
        }

        $request->setStatus('accepted');
        $request->setCustodianId($approverId);
        $request->setUpdatedAt($this->now());
        $this->requestMapper->update($request);

        $this->logActivity($requestId, $approverId, 'accepted', 'Request accepted by ' . $approverId);
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_ACCEPTED, $request, $approverId, 'Request accepted by ' . $approverId));

        $this->sendNotification($request->getRequesterId(), 'request_accepted', $requestId, [
            'custodianId' => $approverId,
            'title' => $request->getTitle(),
        ]);

        return $request;
    }

    public function rejectRequest(int $requestId, string $approverId, string $reason): FileRequest {
        $request = $this->requestMapper->find($requestId);
        $this->verifyApprover($approverId);

        if (!in_array($request->getStatus(), ['pending', 'accepted'])) {
            throw new \RuntimeException('This request cannot be rejected');
        }

        $request->setStatus('rejected');
        $request->setCustodianId($approverId);
        $request->setRejectReason(trim($reason));
        $request->setUpdatedAt($this->now());
        $this->requestMapper->update($request);

        $this->logActivity($requestId, $approverId, 'rejected', 'Rejected by ' . $approverId . ': ' . $reason);
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_REJECTED, $request, $approverId, $reason));

        $this->sendNotification($request->getRequesterId(), 'request_rejected', $requestId, [
            'custodianId' => $approverId,
            'title' => $request->getTitle(),
            'reason' => $reason,
        ]);

        return $request;
    }

    public function cancelRequest(int $requestId, string $requesterId, string $reason): FileRequest {
        $request = $this->requestMapper->find($requestId);

        if ($request->getRequesterId() !== $requesterId) {
            throw new \RuntimeException('Only the requester can cancel');
        }

        if (!in_array($request->getStatus(), ['pending', 'accepted'])) {
            throw new \RuntimeException('This request cannot be cancelled');
        }

        $request->setStatus('cancelled');
        $request->setRejectReason($reason);
        $request->setUpdatedAt($this->now());
        $this->requestMapper->update($request);

        $this->logActivity($requestId, $requesterId, 'cancelled', 'Request cancelled by requester: ' . $reason);
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_CANCELLED, $request, $requesterId, $reason));

        // Notify the approver if one was assigned
        if ($request->getCustodianId() !== '') {
            $this->sendNotification($request->getCustodianId(), 'request_cancelled', $requestId, [
                'requesterId' => $requesterId,
                'title' => $request->getTitle(),
                'reason' => $reason,
            ]);
        }

        return $request;
    }

    public function fulfillRequest(int $requestId, string $approverId, array $files, ?string $expiryDate = null): array {
        $request = $this->requestMapper->find($requestId);
        $this->verifyApprover($approverId);
        $custodianId = $approverId;

        if (!in_array($request->getStatus(), ['pending', 'accepted'])) {
            throw new \RuntimeException('Request must be pending or accepted before fulfillment');
        }

        if (empty($files)) {
            throw new \InvalidArgumentException('At least one file must be provided');
        }

        // Auto-accept if still pending (internal state transition only — the
        // fulfilled event/activity/notification that follows covers this)
        if ($request->getStatus() === 'pending') {
            $request->setStatus('accepted');
            $request->setCustodianId($custodianId);
            $request->setUpdatedAt($this->now());
            $this->requestMapper->update($request);
        }

        $userFolder = $this->rootFolder->getUserFolder($custodianId);
        $fulfillments = [];

        foreach ($files as $fileData) {
            $path = $fileData['path'] ?? '';
            if (empty($path)) {
                continue;
            }

            try {
                $node = $userFolder->get($path);
            } catch (\OCP\Files\NotFoundException $e) {
                throw new \InvalidArgumentException('File not found: ' . $path);
            }

            $share = $this->shareManager->newShare();
            $share->setNode($node)
                ->setShareType(IShare::TYPE_USER)
                ->setPermissions(1)
                ->setSharedBy($custodianId)
                ->setSharedWith($request->getRequesterId());

            if (!empty($expiryDate)) {
                $share->setExpirationDate(new \DateTime($expiryDate));
            }

            $share = $this->shareManager->createShare($share);

            $fulfillment = new Fulfillment();
            $fulfillment->setRequestId($requestId);
            $fulfillment->setNodeId($node->getId());
            $fulfillment->setFilePath($path);
            $fulfillment->setFileName($node->getName());
            $fulfillment->setShareId((string)$share->getId());
            $fulfillment->setShareType(IShare::TYPE_USER);
            $fulfillment->setShareToken(null);
            $fulfillment->setPasswordProtected(0);
            $fulfillment->setShareExpiry($expiryDate);
            $fulfillment->setPermissions(1);
            $fulfillment->setLocked(0);
            $fulfillment->setCreatedAt($this->now());

            $fulfillment = $this->fulfillmentMapper->insert($fulfillment);
            $fulfillments[] = $fulfillment;
        }

        $request->setStatus('fulfilled');
        $request->setUpdatedAt($this->now());
        $this->requestMapper->update($request);

        $this->logActivity($requestId, $custodianId, 'fulfilled', 'Fulfilled with ' . count($fulfillments) . ' file(s)');
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_FULFILLED, $request, $custodianId, 'Fulfilled with ' . count($fulfillments) . ' file(s)'));

        $this->sendNotification($request->getRequesterId(), 'request_fulfilled', $requestId, [
            'custodianId' => $custodianId,
            'title' => $request->getTitle(),
            'fileCount' => (string)count($fulfillments),
        ]);

        return $fulfillments;
    }

    public function expireRequest(FileRequest $request): void {
        $request->setStatus('expired');
        $request->setUpdatedAt($this->now());
        $this->requestMapper->update($request);

        $requestId = (int)$request->getId();
        $this->logActivity($requestId, 'system', 'expired', 'Request expired automatically');
        $this->eventDispatcher->dispatchTyped(new FileRequestEvent(FileRequestEvent::TYPE_EXPIRED, $request, 'system', 'Request expired automatically'));

        $this->sendNotification($request->getRequesterId(), 'request_expired', $requestId, [
            'title' => $request->getTitle(),
        ]);
        if ($request->getCustodianId() !== '') {
            $this->sendNotification($request->getCustodianId(), 'request_expired', $requestId, [
                'title' => $request->getTitle(),
            ]);
        }
    }

    public function getRequest(int $id): FileRequest {
        return $this->requestMapper->find($id);
    }

    public function getRequestsByUser(string $userId, ?string $role = null, ?string $status = null, int $limit = 50, int $offset = 0): array {
        return $this->requestMapper->findByUser($userId, $role, $status, $limit, $offset);
    }

    public function getAllRequests(?string $status = null, int $limit = 50, int $offset = 0): array {
        return $this->requestMapper->findAll($status, $limit, $offset);
    }

    public function getExpiredRequests(): array {
        return $this->requestMapper->findExpired();
    }

    public function getFulfillments(int $requestId): array {
        return $this->fulfillmentMapper->findByRequestId($requestId);
    }

    public function getActivity(int $requestId): array {
        return $this->activityMapper->findByRequestId($requestId);
    }

    public function getStats(string $userId, bool $isAdmin = false): array {
        if ($isAdmin) {
            return [
                'total' => $this->requestMapper->countAll(),
                'pending' => $this->requestMapper->countAll('pending'),
                'accepted' => $this->requestMapper->countAll('accepted'),
                'fulfilled' => $this->requestMapper->countAll('fulfilled'),
                'rejected' => $this->requestMapper->countAll('rejected'),
                'cancelled' => $this->requestMapper->countAll('cancelled'),
            ];
        }

        return [
            'sent' => $this->requestMapper->countByUser($userId, 'requester'),
            'received' => $this->requestMapper->countByUser($userId, 'custodian'),
            'pendingSent' => $this->requestMapper->countByUser($userId, 'requester', 'pending'),
            'pendingReceived' => $this->requestMapper->countByUser($userId, 'custodian', 'pending'),
            'fulfilled' => $this->requestMapper->countByUser($userId, null, 'fulfilled'),
            'rejected' => $this->requestMapper->countByUser($userId, null, 'rejected'),
        ];
    }

    public function searchUsers(string $term, int $limit = 20): array {
        $users = $this->userManager->search($term, $limit);
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
            ];
        }
        return $result;
    }

    public function isApprover(string $userId): bool {
        return $this->groupManager->isInGroup($userId, FileRequestAccessMiddleware::GROUP_APPROVERS);
    }

    private function verifyApprover(string $userId): void {
        if (!$this->isApprover($userId)) {
            throw new \RuntimeException('Not authorized: you are not a File Approver');
        }
    }

    private function now(): string {
        return (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    }

    private function logActivity(int $requestId, string $userId, string $action, ?string $message = null): void {
        $activity = new RequestActivity();
        $activity->setRequestId($requestId);
        $activity->setUserId($userId);
        $activity->setAction($action);
        $activity->setMessage($message);
        $activity->setCreatedAt($this->now());
        $this->activityMapper->insert($activity);
    }

    private function sendNotification(string $targetUserId, string $subject, int $requestId, array $params): void {
        try {
            $notification = $this->notificationManager->createNotification();
            $notification->setApp('filerequests')
                ->setUser($targetUserId)
                ->setDateTime(new \DateTime())
                ->setObject('request', (string)$requestId)
                ->setSubject($subject, $params);

            $this->notificationManager->notify($notification);
        } catch (\Throwable $e) {
            $this->logger->error('FileRequests: Failed to send notification: ' . $e->getMessage());
        }
    }
}
