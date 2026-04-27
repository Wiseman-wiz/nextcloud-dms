<?php

declare(strict_types=1);

namespace OCA\DownloadControl\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

class LogController extends Controller {
    private IDBConnection $db;
    private IUserSession $userSession;

    public function __construct(
        string $appName,
        IRequest $request,
        IDBConnection $db,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->db = $db;
        $this->userSession = $userSession;
    }

    /**
     * @NoAdminRequired
     */
    public function logPurpose(string $fileName, string $filePath, string $fileType, string $purpose): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'User not logged in'], 403);
        }

        $query = $this->db->getQueryBuilder();
        $query->insert('download_logs')
            ->values([
                'user_id' => $query->createNamedParameter($user->getUID()),
                'file_name' => $query->createNamedParameter($fileName),
                'file_path' => $query->createNamedParameter($filePath),
                'file_type' => $query->createNamedParameter($fileType),
                'purpose' => $query->createNamedParameter($purpose),
                'created_at' => $query->createNamedParameter(date('Y-m-d H:i:s')),
            ]);
        $query->executeStatement();

        return new JSONResponse(['status' => 'success']);
    }

    /**
     * @NoAdminRequired
     */
    public function ackDisclaimer(string $fileName, string $filePath): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'User not logged in'], 403);
        }

        $query = $this->db->getQueryBuilder();
        $query->insert('disclaimer_logs')
            ->values([
                'user_id' => $query->createNamedParameter($user->getUID()),
                'file_name' => $query->createNamedParameter($fileName),
                'file_path' => $query->createNamedParameter($filePath),
                'acknowledged_at' => $query->createNamedParameter(date('Y-m-d H:i:s')),
            ]);
        $query->executeStatement();

        return new JSONResponse(['status' => 'success']);
    }
}
