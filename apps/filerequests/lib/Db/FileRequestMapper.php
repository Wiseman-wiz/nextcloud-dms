<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FileRequestMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'filereq_requests', FileRequest::class);
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): FileRequest {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * @return FileRequest[]
     */
    public function findByUser(string $userId, ?string $role = null, ?string $status = null, int $limit = 50, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());

        if ($role === 'requester') {
            $qb->where($qb->expr()->eq('requester_id', $qb->createNamedParameter($userId)));
        } elseif ($role === 'custodian') {
            $qb->where($qb->expr()->eq('custodian_id', $qb->createNamedParameter($userId)));
        } else {
            $qb->where($qb->expr()->orX(
                $qb->expr()->eq('requester_id', $qb->createNamedParameter($userId)),
                $qb->expr()->eq('custodian_id', $qb->createNamedParameter($userId))
            ));
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
        }

        $qb->orderBy('updated_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }

    /**
     * @return FileRequest[]
     */
    public function findAll(?string $status = null, int $limit = 50, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());

        if ($status !== null && $status !== '') {
            $qb->where($qb->expr()->eq('status', $qb->createNamedParameter($status)));
        }

        $qb->orderBy('updated_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }

    /**
     * @return FileRequest[]
     */
    public function findExpired(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
            ->andWhere($qb->expr()->isNotNull('expires_at'))
            ->andWhere($qb->expr()->lte('expires_at', $qb->createNamedParameter((new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'))));
        return $this->findEntities($qb);
    }

    public function countByUser(string $userId, ?string $role = null, ?string $status = null): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName());

        if ($role === 'requester') {
            $qb->where($qb->expr()->eq('requester_id', $qb->createNamedParameter($userId)));
        } elseif ($role === 'custodian') {
            $qb->where($qb->expr()->eq('custodian_id', $qb->createNamedParameter($userId)));
        } else {
            $qb->where($qb->expr()->orX(
                $qb->expr()->eq('requester_id', $qb->createNamedParameter($userId)),
                $qb->expr()->eq('custodian_id', $qb->createNamedParameter($userId))
            ));
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
        }

        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }

    public function countAll(?string $status = null): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName());

        if ($status !== null && $status !== '') {
            $qb->where($qb->expr()->eq('status', $qb->createNamedParameter($status)));
        }

        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }
}
