<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AuditLogMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'audit_log', AuditLog::class);
    }

    /**
     * @param string[] $excludeCategories
     * @return AuditLog[]
     */
    public function findAll(int $limit = 200, int $offset = 0, ?string $category = null, ?string $userId = null, ?string $action = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, array $excludeCategories = []): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('timestamp', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($category !== null && $category !== '') {
            $qb->andWhere($qb->expr()->eq('category', $qb->createNamedParameter($category)));
        }
        if ($userId !== null && $userId !== '') {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }
        if ($action !== null && $action !== '') {
            $qb->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)));
        }
        if ($search !== null && $search !== '') {
            $searchParam = '%' . $this->db->escapeLikeParameter($search) . '%';
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->iLike('target', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('user_id', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('action', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('category', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('timestamp', $qb->createNamedParameter($searchParam))
            ));
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $qb->andWhere($qb->expr()->gte('timestamp', $qb->createNamedParameter($dateFrom)));
        }
        if ($dateTo !== null && $dateTo !== '') {
            $qb->andWhere($qb->expr()->lte('timestamp', $qb->createNamedParameter($dateTo . ' 23:59:59')));
        }
        foreach ($excludeCategories as $exCat) {
            $qb->andWhere($qb->expr()->neq('category', $qb->createNamedParameter($exCat)));
        }

        return $this->findEntities($qb);
    }

    /**
     * @param string[] $excludeCategories
     */
    public function countAll(?string $category = null, ?string $userId = null, ?string $action = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, array $excludeCategories = []): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from($this->getTableName());

        if ($category !== null && $category !== '') {
            $qb->andWhere($qb->expr()->eq('category', $qb->createNamedParameter($category)));
        }
        if ($userId !== null && $userId !== '') {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }
        if ($action !== null && $action !== '') {
            $qb->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)));
        }
        if ($search !== null && $search !== '') {
            $searchParam = '%' . $this->db->escapeLikeParameter($search) . '%';
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->iLike('target', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('user_id', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('action', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('category', $qb->createNamedParameter($searchParam)),
                $qb->expr()->iLike('timestamp', $qb->createNamedParameter($searchParam))
            ));
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $qb->andWhere($qb->expr()->gte('timestamp', $qb->createNamedParameter($dateFrom)));
        }
        if ($dateTo !== null && $dateTo !== '') {
            $qb->andWhere($qb->expr()->lte('timestamp', $qb->createNamedParameter($dateTo . ' 23:59:59')));
        }
        foreach ($excludeCategories as $exCat) {
            $qb->andWhere($qb->expr()->neq('category', $qb->createNamedParameter($exCat)));
        }

        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('category', $qb->createFunction('COUNT(*) as count'))
            ->from($this->getTableName())
            ->groupBy('category');

        $result = $qb->executeQuery();
        $stats = [];
        while ($row = $result->fetch()) {
            $stats[$row['category']] = (int)$row['count'];
        }
        $result->closeCursor();
        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getActionStats(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('action', $qb->createFunction('COUNT(*) as count'))
            ->from($this->getTableName())
            ->groupBy('action');

        $result = $qb->executeQuery();
        $stats = [];
        while ($row = $result->fetch()) {
            $stats[$row['action']] = (int)$row['count'];
        }
        $result->closeCursor();
        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getUserStats(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_id', $qb->createFunction('COUNT(*) as count'))
            ->from($this->getTableName())
            ->groupBy('user_id')
            ->orderBy($qb->createFunction('COUNT(*)'), 'DESC')
            ->setMaxResults(20);

        $result = $qb->executeQuery();
        $stats = [];
        while ($row = $result->fetch()) {
            $stats[$row['user_id']] = (int)$row['count'];
        }
        $result->closeCursor();
        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function getHourlyStats(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('HOUR(timestamp) as hour'), $qb->createFunction('COUNT(*) as count'))
            ->from($this->getTableName())
            ->where($qb->expr()->gte('timestamp', $qb->createNamedParameter((new \DateTime('-7 days', new \DateTimeZone('Asia/Manila')))->format('Y-m-d'))))
            ->groupBy($qb->createFunction('HOUR(timestamp)'))
            ->orderBy($qb->createFunction('HOUR(timestamp)'));

        $result = $qb->executeQuery();
        $stats = [];
        while ($row = $result->fetch()) {
            $stats[(string)$row['hour']] = (int)$row['count'];
        }
        $result->closeCursor();
        return $stats;
    }

    public function getDistinctActions(array $excludeCategories = []): array {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('action')
            ->from($this->getTableName())
            ->orderBy('action');

        foreach ($excludeCategories as $exCat) {
            $qb->andWhere($qb->expr()->neq('category', $qb->createNamedParameter($exCat)));
        }

        $result = $qb->executeQuery();
        $actions = [];
        while ($row = $result->fetch()) {
            $actions[] = $row['action'];
        }
        $result->closeCursor();
        return $actions;
    }

    public function getDistinctUsers(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('user_id')
            ->from($this->getTableName())
            ->where($qb->expr()->neq('user_id', $qb->createNamedParameter('')))
            ->orderBy('user_id');

        $result = $qb->executeQuery();
        $users = [];
        while ($row = $result->fetch()) {
            $users[] = $row['user_id'];
        }
        $result->closeCursor();
        return $users;
    }
}
