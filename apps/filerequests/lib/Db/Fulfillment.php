<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getRequestId()
 * @method void setRequestId(int $requestId)
 * @method int|null getNodeId()
 * @method void setNodeId(?int $nodeId)
 * @method string getFilePath()
 * @method void setFilePath(string $filePath)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method string|null getShareId()
 * @method void setShareId(?string $shareId)
 * @method int getShareType()
 * @method void setShareType(int $shareType)
 * @method string|null getShareToken()
 * @method void setShareToken(?string $shareToken)
 * @method int getPasswordProtected()
 * @method void setPasswordProtected(int $passwordProtected)
 * @method string|null getShareExpiry()
 * @method void setShareExpiry(?string $shareExpiry)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 * @method int getLocked()
 * @method void setLocked(int $locked)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Fulfillment extends Entity {
    protected int $requestId = 0;
    protected ?int $nodeId = null;
    protected string $filePath = '';
    protected string $fileName = '';
    protected ?string $shareId = null;
    protected int $shareType = 0;
    protected ?string $shareToken = null;
    protected int $passwordProtected = 0;
    protected ?string $shareExpiry = null;
    protected int $permissions = 1;
    protected int $locked = 0;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('requestId', 'integer');
        $this->addType('nodeId', 'integer');
        $this->addType('filePath', 'string');
        $this->addType('fileName', 'string');
        $this->addType('shareId', 'string');
        $this->addType('shareType', 'integer');
        $this->addType('shareToken', 'string');
        $this->addType('passwordProtected', 'integer');
        $this->addType('shareExpiry', 'string');
        $this->addType('permissions', 'integer');
        $this->addType('locked', 'integer');
        $this->addType('createdAt', 'string');
    }

    public function toArray(): array {
        return [
            'id' => $this->getId(),
            'requestId' => $this->getRequestId(),
            'nodeId' => $this->getNodeId(),
            'filePath' => $this->getFilePath(),
            'fileName' => $this->getFileName(),
            'shareId' => $this->getShareId(),
            'shareType' => $this->getShareType(),
            'shareToken' => $this->getShareToken(),
            'passwordProtected' => (bool)$this->getPasswordProtected(),
            'shareExpiry' => $this->getShareExpiry(),
            'permissions' => $this->getPermissions(),
            'locked' => (bool)$this->getLocked(),
            'createdAt' => $this->getCreatedAt(),
        ];
    }
}
