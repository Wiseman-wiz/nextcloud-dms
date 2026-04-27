<?php
\OCP\Util::addStyle('auditdashboard', 'dashboard');
\OCP\Util::addScript('auditdashboard', 'dashboard');
?>

<div id="app-content">
    <div id="audit-dashboard">
        <div class="audit-header">
            <h2>Audit Dashboard</h2>
            <div class="audit-actions">
                <button id="audit-refresh" class="audit-btn audit-btn-primary">&#x21bb; Refresh</button>
                <div class="export-dropdown" id="export-dropdown">
                    <button class="audit-btn" id="export-toggle">&#x2913; Export &#x25BE;</button>
                    <div class="export-menu" id="export-menu">
                        <button class="export-option" data-format="csv">Export as CSV</button>
                        <button class="export-option" data-format="xlsx">Export as XLSX</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="audit-stats" id="audit-stats">
            <div class="stat-card" id="stat-total">
                <div class="stat-number">-</div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card stat-file" id="stat-file">
                <div class="stat-number">-</div>
                <div class="stat-label">File Events</div>
            </div>
            <div class="stat-card stat-share" id="stat-share">
                <div class="stat-number">-</div>
                <div class="stat-label">Share Events</div>
            </div>
            <div class="stat-card stat-filerequest" id="stat-filerequest">
                <div class="stat-number">-</div>
                <div class="stat-label">File Request Events</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="audit-toolbar">
            <div class="audit-toolbar-left">
                <input type="text" id="filter-search" class="audit-search" placeholder="Search all columns...">
                <select id="filter-category" class="audit-filter">
                    <option value="">All Categories</option>
                    <option value="file">File</option>
                    <option value="share">Share</option>
                    <option value="file_request">File Request</option>
                </select>
                <select id="filter-action" class="audit-filter">
                    <option value="">All Actions</option>
                </select>
                <select id="filter-user" class="audit-filter">
                    <option value="">All Users</option>
                </select>
            </div>
            <div class="audit-toolbar-right">
                <input type="date" id="filter-date-from" class="audit-filter-date" title="From date">
                <span class="audit-filter-sep">to</span>
                <input type="date" id="filter-date-to" class="audit-filter-date" title="To date">
                <button id="filter-apply" class="audit-btn audit-btn-sm audit-btn-primary">Apply</button>
                <button id="filter-reset" class="audit-btn audit-btn-sm">Reset</button>
            </div>
        </div>

        <!-- Log Table -->
        <div class="audit-table-wrapper">
            <table class="audit-table" id="audit-table">
                <thead>
                    <tr>
                        <th class="audit-sortable audit-sorted" data-sort-key="timestamp" data-sort-dir="desc">Timestamp</th>
                        <th class="audit-sortable" data-sort-key="displayName">User</th>
                        <th class="audit-sortable" data-sort-key="action">Action</th>
                        <th class="audit-sortable" data-sort-key="category">Category</th>
                        <th class="audit-sortable" data-sort-key="target">Target</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body">
                    <tr><td colspan="5" class="loading">Loading audit logs...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="audit-pagination" id="audit-pagination">
            <span id="page-info">Page 1</span>
            <div class="audit-pagination-right">
                <button id="page-prev" disabled>Prev</button>
                <button id="page-next">Next</button>
            </div>
        </div>
    </div>
</div>
