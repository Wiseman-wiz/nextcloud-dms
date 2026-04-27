<?php

declare(strict_types=1);

namespace OCA\FileRequests\Notification;

use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
    private IFactory $l10nFactory;
    private IURLGenerator $urlGenerator;
    private IUserManager $userManager;

    public function __construct(IFactory $l10nFactory, IURLGenerator $urlGenerator, IUserManager $userManager) {
        $this->l10nFactory = $l10nFactory;
        $this->urlGenerator = $urlGenerator;
        $this->userManager = $userManager;
    }

    public function getID(): string {
        return 'filerequests';
    }

    public function getName(): string {
        return 'File Requests';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== 'filerequests') {
            throw new \InvalidArgumentException('Unhandled app');
        }

        $params = $notification->getSubjectParameters();
        $requestId = $notification->getObjectId();
        $link = $this->urlGenerator->linkToRouteAbsolute('filerequests.page.index') . '?open=' . $requestId;

        $notification->setLink($link);
        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('filerequests', 'app-dark.svg')));

        switch ($notification->getSubject()) {
            case 'new_request':
                $user = $this->getDisplayName($params['requesterId'] ?? '');
                $title = $params['title'] ?? 'a file';
                $notification->setRichSubject('New file request from {user}', [
                    'user' => [
                        'type' => 'user',
                        'id' => $params['requesterId'] ?? '',
                        'name' => $user,
                    ],
                ]);
                $notification->setParsedSubject($user . ' requested files from you: ' . $title);
                break;

            case 'request_accepted':
                $user = $this->getDisplayName($params['custodianId'] ?? '');
                $notification->setRichSubject('{user} accepted your file request', [
                    'user' => [
                        'type' => 'user',
                        'id' => $params['custodianId'] ?? '',
                        'name' => $user,
                    ],
                ]);
                $notification->setParsedSubject($user . ' accepted your request: ' . ($params['title'] ?? ''));
                break;

            case 'request_rejected':
                $user = $this->getDisplayName($params['custodianId'] ?? '');
                $reason = $params['reason'] ?? '';
                $notification->setRichSubject('{user} rejected your file request', [
                    'user' => [
                        'type' => 'user',
                        'id' => $params['custodianId'] ?? '',
                        'name' => $user,
                    ],
                ]);
                $notification->setParsedSubject($user . ' rejected your request: ' . ($params['title'] ?? '') . ($reason ? ' — ' . $reason : ''));
                break;

            case 'request_fulfilled':
                $user = $this->getDisplayName($params['custodianId'] ?? '');
                $count = $params['fileCount'] ?? '1';
                $notification->setRichSubject('{user} shared {count} file(s) for your request', [
                    'user' => [
                        'type' => 'user',
                        'id' => $params['custodianId'] ?? '',
                        'name' => $user,
                    ],
                    'count' => [
                        'type' => 'highlight',
                        'id' => 'count',
                        'name' => $count,
                    ],
                ]);
                $notification->setParsedSubject($user . ' completed your request "' . ($params['title'] ?? '') . '" with ' . $count . ' file(s)');
                break;

            case 'request_cancelled':
                $user = $this->getDisplayName($params['requesterId'] ?? '');
                $notification->setRichSubject('{user} cancelled their file request', [
                    'user' => [
                        'type' => 'user',
                        'id' => $params['requesterId'] ?? '',
                        'name' => $user,
                    ],
                ]);
                $notification->setParsedSubject($user . ' cancelled request: ' . ($params['title'] ?? ''));
                break;

            case 'request_expired':
                $notification->setParsedSubject('File request expired: ' . ($params['title'] ?? ''));
                $notification->setRichSubject('File request expired: {title}', [
                    'title' => [
                        'type' => 'highlight',
                        'id' => 'title',
                        'name' => $params['title'] ?? '',
                    ],
                ]);
                break;

            default:
                throw new \InvalidArgumentException('Unhandled subject: ' . $notification->getSubject());
        }

        return $notification;
    }

    private function getDisplayName(string $userId): string {
        $user = $this->userManager->get($userId);
        return $user ? $user->getDisplayName() : $userId;
    }
}
