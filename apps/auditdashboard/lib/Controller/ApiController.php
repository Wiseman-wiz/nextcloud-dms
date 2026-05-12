<?php

declare(strict_types=1);

namespace OCA\AuditDashboard\Controller;

use OCA\AuditDashboard\AppInfo\Application;
use OCA\AuditDashboard\Db\AuditLogMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserManager;

class ApiController extends Controller {
    private AuditLogMapper $mapper;
    private IUserManager $userManager;
    private IDBConnection $db;

    private const EXCLUDED_CATEGORIES = ['auth', 'user'];
    private const EXCLUDED_ACTIONS = ['file_touched', 'file_written'];

    public function __construct(IRequest $request, AuditLogMapper $mapper, IUserManager $userManager, IDBConnection $db) {
        parent::__construct(Application::APP_ID, $request);
        $this->mapper = $mapper;
        $this->userManager = $userManager;
        $this->db = $db;
    }

    private function getDisplayName(string $userId): string {
        $user = $this->userManager->get($userId);
        return $user ? $user->getDisplayName() : $userId;
    }

    /**
     * @NoAdminRequired
     */
    public function list(): JSONResponse {
        $limit = (int)$this->request->getParam('limit', '50');
        $offset = (int)$this->request->getParam('offset', '0');
        $category = $this->request->getParam('category', '');
        $userId = $this->request->getParam('userId', '');
        $action = $this->request->getParam('action', '');
        $search = $this->request->getParam('search', '');
        $dateFrom = $this->request->getParam('dateFrom', '');
        $dateTo = $this->request->getParam('dateTo', '');

        $limit = min(max($limit, 1), 500);

        $matchedUserIds = [];
        $matchedActionIds = [];

        if ($search !== '') {
            $trimmedSearch = trim($search);
            if ($trimmedSearch === '' || 
                str_contains($search, '/') || 
                str_contains($search, '\\') ||
                preg_match('/^[^a-zA-Z0-9]+$/', $trimmedSearch)) {
                return new JSONResponse([
                    'logs' => [],
                    'total' => 0,
                ]);
            }

            $users = $this->userManager->search($trimmedSearch);
            foreach ($users as $u) {
                $matchedUserIds[] = $u->getUID();
            }

            $allActions = $this->mapper->getDistinctActions(self::EXCLUDED_CATEGORIES, self::EXCLUDED_ACTIONS);
            foreach ($allActions as $a) {
                $formatted = str_replace('_', ' ', $a);
                $formatted = ucwords($formatted);
                if (stripos($formatted, $trimmedSearch) !== false || stripos($a, $trimmedSearch) !== false) {
                    $matchedActionIds[] = $a;
                }
            }
        }

        $logs = $this->mapper->findAll(
            $limit,
            $offset,
            $category ?: null,
            $userId ?: null,
            $action ?: null,
            $search ?: null,
            $dateFrom ?: null,
            $dateTo ?: null,
            self::EXCLUDED_CATEGORIES,
            self::EXCLUDED_ACTIONS,
            $matchedUserIds,
            $matchedActionIds
        );

        $total = $this->mapper->countAll(
            $category ?: null,
            $userId ?: null,
            $action ?: null,
            $search ?: null,
            $dateFrom ?: null,
            $dateTo ?: null,
            self::EXCLUDED_CATEGORIES,
            self::EXCLUDED_ACTIONS,
            $matchedUserIds,
            $matchedActionIds
        );

        $data = array_map(function ($log) {
            $target = $log->getTarget();
            $fileName = $target ? basename($target) : '';
            $purpose = '';
            if ($log->getAction() === 'file_downloaded' && $fileName !== '') {
                $purpose = $this->lookupDownloadPurpose($log->getUserId(), $fileName, $log->getTimestamp());
            }
            return [
                'id' => $log->getId(),
                'timestamp' => $log->getTimestamp(),
                'userId' => $log->getUserId(),
                'displayName' => $this->getDisplayName($log->getUserId()),
                'action' => $log->getAction(),
                'category' => $log->getCategory(),
                'target' => $target,
                'fileName' => $fileName,
                'purpose' => $purpose,
            ];
        }, $logs);

        return new JSONResponse([
            'logs' => $data,
            'total' => $total,
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function stats(): JSONResponse {
        $actionStats = $this->mapper->getActionStats(self::EXCLUDED_CATEGORIES, self::EXCLUDED_ACTIONS);

        $fileView = $actionStats['file_read'] ?? 0;
        $fileDownload = $actionStats['file_downloaded'] ?? 0;

        // Other = everything except file_read and file_downloaded
        $other = 0;
        foreach ($actionStats as $action => $count) {
            if ($action !== 'file_read' && $action !== 'file_downloaded') {
                $other += $count;
            }
        }

        $total = $fileView + $fileDownload + $other;

        $userIds = $this->mapper->getDistinctUsers();
        $users = array_map(function ($uid) {
            return ['id' => $uid, 'displayName' => $this->getDisplayName($uid)];
        }, $userIds);

        return new JSONResponse([
            'total' => $total,
            'fileView' => $fileView,
            'fileDownload' => $fileDownload,
            'other' => $other,
            'users' => $users,
            'actions' => $this->mapper->getDistinctActions(self::EXCLUDED_CATEGORIES, self::EXCLUDED_ACTIONS),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function export(): DataDownloadResponse {
        $category = $this->request->getParam('category', '');
        $userId = $this->request->getParam('userId', '');
        $search = $this->request->getParam('search', '');
        $dateFrom = $this->request->getParam('dateFrom', '');
        $dateTo = $this->request->getParam('dateTo', '');
        $format = $this->request->getParam('format', 'csv');

        $matchedUserIds = [];
        $matchedActionIds = [];

        if ($search !== '') {
            $trimmedSearch = trim($search);
            if ($trimmedSearch === '' || 
                str_contains($search, '/') || 
                str_contains($search, '\\') ||
                preg_match('/^[^a-zA-Z0-9]+$/', $trimmedSearch)) {
                
                $dateStr = (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
                if ($format === 'xlsx') {
                    return $this->exportXlsx([], $dateStr);
                }
                return $this->exportCsv([], $dateStr);
            }

            $users = $this->userManager->search($trimmedSearch);
            foreach ($users as $u) {
                $matchedUserIds[] = $u->getUID();
            }

            $allActions = $this->mapper->getDistinctActions(self::EXCLUDED_CATEGORIES, self::EXCLUDED_ACTIONS);
            foreach ($allActions as $a) {
                $formatted = str_replace('_', ' ', $a);
                $formatted = ucwords($formatted);
                if (stripos($formatted, $trimmedSearch) !== false || stripos($a, $trimmedSearch) !== false) {
                    $matchedActionIds[] = $a;
                }
            }
        }

        $logs = $this->mapper->findAll(
            10000,
            0,
            $category ?: null,
            $userId ?: null,
            null,
            $search ?: null,
            $dateFrom ?: null,
            $dateTo ?: null,
            self::EXCLUDED_CATEGORIES,
            self::EXCLUDED_ACTIONS,
            $matchedUserIds,
            $matchedActionIds
        );

        $dateStr = (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');

        if ($format === 'xlsx') {
            return $this->exportXlsx($logs, $dateStr);
        }

        return $this->exportCsv($logs, $dateStr);
    }

    /**
     * Look up the download purpose from the download_logs table.
     * Matches by user_id, file_name, and the closest timestamp.
     */
    private function lookupDownloadPurpose(string $userId, string $fileName, string $timestamp): string {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('purpose')
                ->from('download_logs')
                ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
                ->andWhere($qb->expr()->eq('file_name', $qb->createNamedParameter($fileName)))
                ->orderBy($qb->createFunction('ABS(TIMESTAMPDIFF(SECOND, created_at, ' . $qb->createNamedParameter($timestamp) . '))'), 'ASC')
                ->setMaxResults(1);

            $result = $qb->executeQuery();
            $row = $result->fetch();
            $result->closeCursor();

            return $row ? (string)$row['purpose'] : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function exportCsv(array $logs, string $dateStr): DataDownloadResponse {
        $csv = "Timestamp,User,Action,Category,File,Purpose\n";
        foreach ($logs as $log) {
            $target = $log->getTarget();
            $fileName = $target ? basename($target) : '';
            $purpose = '';
            if ($log->getAction() === 'file_downloaded' && $fileName !== '') {
                $purpose = $this->lookupDownloadPurpose($log->getUserId(), $fileName, $log->getTimestamp());
            }
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $this->escapeCsv($log->getTimestamp()),
                $this->escapeCsv($this->getDisplayName($log->getUserId())),
                $this->escapeCsv($log->getAction()),
                $this->escapeCsv($log->getCategory()),
                $this->escapeCsv($fileName),
                $this->escapeCsv($purpose)
            );
        }

        return new DataDownloadResponse($csv, 'audit-log-' . $dateStr . '.csv', 'text/csv');
    }

    private function exportXlsx(array $logs, string $dateStr): DataDownloadResponse {
        $headers = ['Timestamp', 'User', 'Action', 'Category', 'File', 'Purpose'];
        $rows = [];
        foreach ($logs as $log) {
            $target = $log->getTarget();
            $fileName = $target ? basename($target) : '';
            $purpose = '';
            if ($log->getAction() === 'file_downloaded' && $fileName !== '') {
                $purpose = $this->lookupDownloadPurpose($log->getUserId(), $fileName, $log->getTimestamp());
            }
            $rows[] = [
                $log->getTimestamp(),
                $this->getDisplayName($log->getUserId()),
                $log->getAction(),
                $log->getCategory(),
                $fileName,
                $purpose,
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Header row
        $xml .= '<row>';
        foreach ($headers as $h) {
            $xml .= '<c t="inlineStr"><is><t>' . $this->escapeXml($h) . '</t></is></c>';
        }
        $xml .= '</row>';

        // Data rows
        foreach ($rows as $row) {
            $xml .= '<row>';
            foreach ($row as $cell) {
                $xml .= '<c t="inlineStr"><is><t>' . $this->escapeXml($cell ?? '') . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        // Build minimal XLSX (ZIP with required structure)
        $xlsxContent = $this->buildXlsx($xml, count($rows));

        return new DataDownloadResponse($xlsxContent, 'audit-log-' . $dateStr . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function buildXlsx(string $sheetXml, int $rowCount): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Audit Log" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        // xl/worksheets/sheet1.xml
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $content;
    }

    private function escapeCsv(string $value): string {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    private function escapeXml(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
