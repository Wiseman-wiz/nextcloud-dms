<?php

declare(strict_types=1);

namespace OCA\FileRequests\Listener;

use OCA\FileRequests\Db\FulfillmentMapper;
use OCA\FileRequests\Db\FileRequestMapper;
use OCA\FileRequests\Db\RequestActivity;
use OCA\FileRequests\Db\RequestActivityMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<BeforeNodeReadEvent>
 */
class FileDownloadListener implements IEventListener {
    private FulfillmentMapper $fulfillmentMapper;
    private FileRequestMapper $requestMapper;
    private RequestActivityMapper $activityMapper;
    private IUserSession $userSession;
    private LoggerInterface $logger;

    public function __construct(
        FulfillmentMapper $fulfillmentMapper,
        FileRequestMapper $requestMapper,
        RequestActivityMapper $activityMapper,
        IUserSession $userSession,
        LoggerInterface $logger
    ) {
        $this->fulfillmentMapper = $fulfillmentMapper;
        $this->requestMapper = $requestMapper;
        $this->activityMapper = $activityMapper;
        $this->userSession = $userSession;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        if (!($event instanceof BeforeNodeReadEvent)) {
            return;
        }

        $user = $this->userSession->getUser();
        if ($user === null) {
            return;
        }

        $node = $event->getNode();
        $nodeId = $node->getId();
        $userId = $user->getUID();

        try {
            $fulfillments = $this->fulfillmentMapper->findByNodeId($nodeId);
            if (empty($fulfillments)) {
                return;
            }

            foreach ($fulfillments as $fulfillment) {
                $requestId = $fulfillment->getRequestId();
                $request = $this->requestMapper->find($requestId);

                // Only log if the current user is the requester
                if ($request->getRequesterId() !== $userId) {
                    continue;
                }

                $message = 'Downloaded: ' . $fulfillment->getFileName();

                // Dedup: skip if same download was logged within the last 60 minutes
                if ($this->activityMapper->hasRecentActivity($requestId, $userId, 'downloaded', $message, 60)) {
                    continue;
                }

                $now = (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
                $activity = new RequestActivity();
                $activity->setRequestId($requestId);
                $activity->setUserId($userId);
                $activity->setAction('downloaded');
                $activity->setMessage($message);
                $activity->setCreatedAt($now);
                $this->activityMapper->insert($activity);
            }
        } catch (\Throwable $e) {
            $this->logger->debug('FileRequests: Download logging failed: ' . $e->getMessage());
        }
    }
}
