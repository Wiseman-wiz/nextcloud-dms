<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getTimestamp()
 * @method void setTimestamp(string $timestamp)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getTarget()
 * @method void setTarget(string $target)
 * @method string|null getDetails()
 * @method void setDetails(?string $details)
 * @method string|null getIpAddress()
 * @method void setIpAddress(?string $ipAddress)
 */
class AuditLog extends Entity {
    protected string $timestamp = '';
    protected string $userId = '';
    protected string $action = '';
    protected string $category = '';
    protected string $target = '';
    protected ?string $details = null;
    protected ?string $ipAddress = null;

    public function __construct() {
        $this->addType('timestamp', 'string');
        $this->addType('userId', 'string');
        $this->addType('action', 'string');
        $this->addType('category', 'string');
        $this->addType('target', 'string');
        $this->addType('details', 'string');
        $this->addType('ipAddress', 'string');
    }
}
