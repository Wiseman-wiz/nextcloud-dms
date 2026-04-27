<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class RequestActivityMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'filereq_activity', RequestActivity::class);
    }

    /**
     * @return RequestActivity[]
     */
    public function findByRequestId(int $requestId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('request_id', $qb->createNamedParameter($requestId)))
            ->orderBy('created_at', 'ASC');
        return $this->findEntities($qb);
    }

    public function hasRecentActivity(int $requestId, string $userId, string $action, string $message, int $withinMinutes = 60): bool {
        $since = (new \DateTime('now', new \DateTimeZone('Asia/Manila')))
            ->modify("-{$withinMinutes} minutes")
            ->format('Y-m-d H:i:s');
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*) AS cnt'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('request_id', $qb->createNamedParameter($requestId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)))
            ->andWhere($qb->expr()->eq('message', $qb->createNamedParameter($message)))
            ->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since)));
        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count > 0;
    }
}
