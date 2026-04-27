<?php

declare(strict_types=1);

namespace OCA\FileRequests\Event;

use OCA\FileRequests\Db\FileRequest;
use OCP\EventDispatcher\Event;

class FileRequestEvent extends Event {
    public const TYPE_CREATED = 'created';
    public const TYPE_ACCEPTED = 'accepted';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_FULFILLED = 'fulfilled';
    public const TYPE_EXPIRED = 'expired';

    private string $type;
    private FileRequest $request;
    private string $actorId;
    private ?string $message;

    public function __construct(string $type, FileRequest $request, string $actorId, ?string $message = null) {
        parent::__construct();
        $this->type = $type;
        $this->request = $request;
        $this->actorId = $actorId;
        $this->message = $message;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getRequest(): FileRequest {
        return $this->request;
    }

    public function getActorId(): string {
        return $this->actorId;
    }

    public function getMessage(): ?string {
        return $this->message;
    }
}
