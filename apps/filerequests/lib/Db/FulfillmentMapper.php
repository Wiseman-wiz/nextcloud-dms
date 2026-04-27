<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FulfillmentMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'filereq_fulfillments', Fulfillment::class);
    }

    /**
     * @return Fulfillment[]
     */
    public function findByRequestId(int $requestId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('request_id', $qb->createNamedParameter($requestId)))
            ->orderBy('created_at', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * @return Fulfillment[]
     */
    public function findByNodeId(int $nodeId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId)));
        return $this->findEntities($qb);
    }
}
