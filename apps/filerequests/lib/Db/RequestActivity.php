<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getRequestId()
 * @method void setRequestId(int $requestId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string|null getMessage()
 * @method void setMessage(?string $message)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class RequestActivity extends Entity {
    protected int $requestId = 0;
    protected string $userId = '';
    protected string $action = '';
    protected ?string $message = null;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('requestId', 'integer');
        $this->addType('userId', 'string');
        $this->addType('action', 'string');
        $this->addType('message', 'string');
        $this->addType('createdAt', 'string');
    }

    public function toArray(): array {
        return [
            'id' => $this->getId(),
            'requestId' => $this->getRequestId(),
            'userId' => $this->getUserId(),
            'action' => $this->getAction(),
            'message' => $this->getMessage(),
            'createdAt' => $this->getCreatedAt(),
        ];
    }
}
