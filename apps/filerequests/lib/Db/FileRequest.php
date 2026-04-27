<?php

declare(strict_types=1);

namespace OCA\FileRequests\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getRequesterId()
 * @method void setRequesterId(string $requesterId)
 * @method string getCustodianId()
 * @method void setCustodianId(string $custodianId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getRejectReason()
 * @method void setRejectReason(?string $rejectReason)
 * @method string|null getExpiresAt()
 * @method void setExpiresAt(?string $expiresAt)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 * @method string|null getDepartment()
 * @method void setDepartment(?string $department)
 * @method string|null getProvince()
 * @method void setProvince(?string $province)
 * @method string|null getMunicipalityCity()
 * @method void setMunicipalityCity(?string $municipalityCity)
 * @method string|null getProject()
 * @method void setProject(?string $project)
 * @method string|null getPermitDocumentName()
 * @method void setPermitDocumentName(?string $permitDocumentName)
 * @method string|null getDateNeeded()
 * @method void setDateNeeded(?string $dateNeeded)
 */
class FileRequest extends Entity {
    protected string $requesterId = '';
    protected string $custodianId = '';
    protected string $title = '';
    protected ?string $description = null;
    protected string $status = 'pending';
    protected ?string $rejectReason = null;
    protected ?string $expiresAt = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';
    protected ?string $department = null;
    protected ?string $province = null;
    protected ?string $municipalityCity = null;
    protected ?string $project = null;
    protected ?string $permitDocumentName = null;
    protected ?string $dateNeeded = null;

    public function __construct() {
        $this->addType('requesterId', 'string');
        $this->addType('custodianId', 'string');
        $this->addType('title', 'string');
        $this->addType('description', 'string');
        $this->addType('status', 'string');
        $this->addType('rejectReason', 'string');
        $this->addType('expiresAt', 'string');
        $this->addType('createdAt', 'string');
        $this->addType('updatedAt', 'string');
        $this->addType('department', 'string');
        $this->addType('province', 'string');
        $this->addType('municipalityCity', 'string');
        $this->addType('project', 'string');
        $this->addType('permitDocumentName', 'string');
        $this->addType('dateNeeded', 'string');
    }

    public function toArray(): array {
        return [
            'id' => $this->getId(),
            'requesterId' => $this->getRequesterId(),
            'custodianId' => $this->getCustodianId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'rejectReason' => $this->getRejectReason(),
            'expiresAt' => $this->getExpiresAt(),
            'createdAt' => $this->getCreatedAt(),
            'updatedAt' => $this->getUpdatedAt(),
            'department' => $this->getDepartment(),
            'province' => $this->getProvince(),
            'municipalityCity' => $this->getMunicipalityCity(),
            'project' => $this->getProject(),
            'permitDocumentName' => $this->getPermitDocumentName(),
            'dateNeeded' => $this->getDateNeeded(),
        ];
    }
}
