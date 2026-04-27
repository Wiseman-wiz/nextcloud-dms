<?php
\OCP\Util::addStyle('filerequests', 'style');
\OCP\Util::addScript('filerequests', 'app');
$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
$uid = \OC::$server->get(\OCP\IUserSession::class)->getUser()->getUID();
$isApprover = $groupManager->isInGroup($uid, \OCA\FileRequests\Middleware\FileRequestAccessMiddleware::GROUP_APPROVERS);
?>
<div id="app-content">
    <div id="filereq-app" data-is-admin="0" data-is-approver="<?php echo $isApprover ? '1' : '0'; ?>" data-current-user="<?php echo $uid; ?>" data-open-request="<?php echo (int)($_GET['open'] ?? 0); ?>">

        <div class="fr-header">
            <h2>File Requests</h2>
            <button id="fr-new-request" class="fr-btn fr-btn-primary">New Request</button>
        </div>

        <!-- Hidden tab trigger for JS -->
        <div style="display:none" id="fr-tabs">
            <?php if (!$isApprover): ?>
            <button class="fr-tab active" data-tab="sent">My Requests</button>
            <?php endif; ?>
            <?php if ($isApprover): ?>
            <button class="fr-tab active" data-tab="admin">All Requests</button>
            <?php endif; ?>
        </div>

        <!-- Sent Requests (requestors only) -->
        <?php if (!$isApprover): ?>
        <div class="fr-panel active" id="panel-sent">
            <div class="fr-stats" id="fr-sent-stats"></div>
            <div class="fr-admin-toolbar">
                <div class="fr-admin-toolbar-left">
                    <input type="text" id="fr-sent-search" class="fr-admin-search" placeholder="Search my requests..." />
                    <select id="filter-sent-status" class="fr-admin-filter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="fulfilled">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="fr-admin-toolbar-right">
                    <button id="fr-sent-refresh" class="fr-btn fr-btn-sm" title="Refresh">&#x21bb; Refresh</button>
                    <button id="fr-sent-export-csv" class="fr-btn fr-btn-sm" title="Export as CSV">&#x2913; CSV</button>
                    <button id="fr-sent-export-xlsx" class="fr-btn fr-btn-sm" title="Export as XLSX">&#x2913; XLSX</button>
                </div>
            </div>
            <div class="fr-table-wrapper">
                <table class="fr-table" id="fr-sent-table">
                    <thead>
                        <tr>
                            <th class="fr-sortable fr-sorted" data-sort-key="id" data-sort-dir="desc">ID</th>
                            <th class="fr-sortable" data-sort-key="permitDocumentName">Permit / Document</th>
                            <th class="fr-sortable" data-sort-key="department">Department</th>
                            <th class="fr-sortable" data-sort-key="status">Status</th>
                            <th class="fr-sortable" data-sort-key="dateNeeded">Date Needed</th>
                            <th class="fr-sortable" data-sort-key="createdAt">Created</th>
                        </tr>
                    </thead>
                    <tbody id="fr-sent-tbody">
                        <tr><td colspan="6" class="fr-empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Requests (approvers and admins) -->
        <?php if ($isApprover): ?>
        <div class="fr-panel active" id="panel-admin">
            <div class="fr-stats" id="fr-admin-stats"></div>
            <div class="fr-admin-toolbar">
                <div class="fr-admin-toolbar-left">
                    <input type="text" id="fr-admin-search" class="fr-admin-search" placeholder="Search requests..." />
                    <select id="filter-admin-status" class="fr-admin-filter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="fulfilled">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="fr-admin-toolbar-right">
                    <button id="fr-admin-refresh" class="fr-btn fr-btn-sm" title="Refresh">&#x21bb; Refresh</button>
                    <button id="fr-admin-export-csv" class="fr-btn fr-btn-sm" title="Export as CSV">&#x2913; CSV</button>
                    <button id="fr-admin-export-xlsx" class="fr-btn fr-btn-sm" title="Export as XLSX">&#x2913; XLSX</button>
                </div>
            </div>
            <div class="fr-table-wrapper">
                <table class="fr-table" id="fr-admin-table">
                    <thead>
                        <tr>
                            <th class="fr-sortable fr-sorted" data-sort-key="id" data-sort-dir="desc">ID</th>
                            <th class="fr-sortable" data-sort-key="permitDocumentName">Permit / Document</th>
                            <th class="fr-sortable" data-sort-key="requesterDisplayName">Requester</th>
                            <th class="fr-sortable" data-sort-key="custodianDisplayName">Completed By</th>
                            <th class="fr-sortable" data-sort-key="department">Department</th>
                            <th class="fr-sortable" data-sort-key="status">Status</th>
                            <th class="fr-sortable" data-sort-key="dateNeeded">Date Needed</th>
                            <th class="fr-sortable" data-sort-key="createdAt">Created</th>
                        </tr>
                    </thead>
                    <tbody id="fr-admin-tbody">
                        <tr><td colspan="8" class="fr-empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Request Detail Overlay -->
        <div class="fr-overlay" id="fr-detail-overlay" style="display:none">
            <div class="fr-detail-panel" id="fr-detail-content"></div>
        </div>

        <!-- New Request Modal -->
        <div class="fr-overlay" id="fr-new-overlay" style="display:none">
            <div class="fr-modal fr-modal-fixed">
                <div class="fr-modal-header">
                    <h3>New File Request</h3>
                    <button class="fr-modal-close" id="fr-new-close">&times;</button>
                </div>
                <form id="fr-new-form">
                    <div class="fr-modal-body">
                        <div class="fr-form-group">
                            <label for="fr-new-department">Department / Section</label>
                            <input type="text" id="fr-new-department" placeholder="e.g. Engineering, HR, Finance" maxlength="256">
                        </div>
                        <div class="fr-form-group">
                            <label for="fr-new-date-needed">Date Needed</label>
                            <input type="date" id="fr-new-date-needed">
                        </div>
                        <div class="fr-form-group">
                            <label for="fr-new-desc">Reason</label>
                            <textarea id="fr-new-desc" rows="4" placeholder="Provide the reason for this request..."></textarea>
                        </div>
                        <p class="fr-form-hint">Please provide the details of the file or document you are requesting.</p>
                        <div class="fr-form-group">
                            <label for="fr-new-province">Province</label>
                            <input type="text" id="fr-new-province" placeholder="e.g. Batangas" maxlength="256">
                        </div>
                        <div class="fr-form-group">
                            <label for="fr-new-municipality">Municipality / City</label>
                            <input type="text" id="fr-new-municipality" placeholder="e.g. Anilao" maxlength="256">
                        </div>
                        <div class="fr-form-group">
                            <label for="fr-new-project">Project</label>
                            <input type="text" id="fr-new-project" placeholder="e.g. Brightwood Villas" maxlength="256">
                        </div>
                        <div class="fr-form-group">
                            <label for="fr-new-permit">Permit / Document Name</label>
                            <input type="text" id="fr-new-permit" placeholder="e.g. Building Permit, ECC" maxlength="256">
                        </div>
                    </div>
                    <div class="fr-form-actions">
                        <button type="button" class="fr-btn" id="fr-new-cancel">Cancel</button>
                        <button type="submit" class="fr-btn fr-btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="fr-overlay" id="fr-reject-overlay" style="display:none">
            <div class="fr-modal fr-modal-sm">
                <div class="fr-modal-header">
                    <h3>Reject Request</h3>
                    <button class="fr-modal-close" id="fr-reject-close">&times;</button>
                </div>
                <form id="fr-reject-form">
                    <input type="hidden" id="fr-reject-id">
                    <div class="fr-form-group">
                        <label for="fr-reject-reason">Reason for rejection</label>
                        <textarea id="fr-reject-reason" rows="3" required placeholder="Explain why this request is being rejected..."></textarea>
                    </div>
                    <div class="fr-form-actions">
                        <button type="button" class="fr-btn" id="fr-reject-cancel">Cancel</button>
                        <button type="submit" class="fr-btn fr-btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div class="fr-overlay" id="fr-cancel-overlay" style="display:none">
            <div class="fr-modal fr-modal-sm">
                <div class="fr-modal-header">
                    <h3>Cancel Request</h3>
                    <button class="fr-modal-close" id="fr-cancel-close">&times;</button>
                </div>
                <form id="fr-cancel-form">
                    <input type="hidden" id="fr-cancel-id">
                    <div class="fr-form-group">
                        <label for="fr-cancel-reason">Reason for cancellation</label>
                        <textarea id="fr-cancel-reason" rows="3" required placeholder="Explain why you are cancelling this request..."></textarea>
                    </div>
                    <div class="fr-form-actions">
                        <button type="button" class="fr-btn" id="fr-cancel-back">Back</button>
                        <button type="submit" class="fr-btn fr-btn-danger">Cancel Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Share Files/Folders Modal -->
        <div class="fr-overlay" id="fr-fulfill-overlay" style="display:none">
            <div class="fr-modal fr-modal-lg">
                <div class="fr-modal-header">
                    <h3>Share Files/Folders</h3>
                    <button class="fr-modal-close" id="fr-fulfill-close">&times;</button>
                </div>
                <div id="fr-fulfill-request-info"></div>
                <div class="fr-fulfill-files" id="fr-fulfill-files">
                    <p class="fr-empty">No files or folders selected yet. Click below to add.</p>
                </div>
                <button type="button" class="fr-btn" id="fr-add-files">+ Add Files / Folders</button>
                <input type="hidden" id="fr-fulfill-id">
                <div class="fr-form-group" style="margin-top:16px">
                    <label>Expiration of Access</label>
                    <div class="fr-expiry-group">
                        <input type="checkbox" id="fr-expiry-toggle" class="fr-checkbox">
                        <input type="date" id="fr-fulfill-expiry" class="fr-expiry-date" disabled>
                    </div>
                </div>
                <div class="fr-form-actions" style="margin-top:16px">
                    <button type="button" class="fr-btn" id="fr-fulfill-cancel">Cancel</button>
                    <button type="button" class="fr-btn fr-btn-primary" id="fr-fulfill-submit" disabled>Share and Complete</button>
                </div>
            </div>
        </div>
    </div>
</div>
