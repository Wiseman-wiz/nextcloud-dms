<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Service;

use OCA\AuditDashboard\Db\AuditLog;
use OCA\AuditDashboard\Db\AuditLogMapper;
use OCP\IRequest;

class AuditService {
    private AuditLogMapper $mapper;
    private ?IRequest $request;

    public function __construct(AuditLogMapper $mapper, ?IRequest $request = null) {
        $this->mapper = $mapper;
        $this->request = $request;
    }

    public function log(string $userId, string $action, string $category, string $target, ?string $details = null): void {
        $entry = new AuditLog();
        $entry->setTimestamp((new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'));
        $entry->setUserId($userId);
        $entry->setAction($action);
        $entry->setCategory($category);
        $entry->setTarget($target);
        $entry->setDetails($details);

        $ip = null;
        if ($this->request !== null) {
            try {
                $ip = $this->request->getRemoteAddress();
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $entry->setIpAddress($ip);

        $this->mapper->insert($entry);
    }
}
