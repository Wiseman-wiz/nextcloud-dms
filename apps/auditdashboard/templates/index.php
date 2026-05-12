<?php
\OCP\Util::addStyle('auditdashboard', 'dashboard');
\OCP\Util::addScript('auditdashboard', 'dashboard');
?>

<div id="app-content">
    <div class="audit-header">
        <div class="audit-header-inner">
            <div>
                <h2>Audit Dashboard</h2>
                <!-- <span class="audit-header-subtitle">Activity across files, shares, and requests</span> -->
            </div>
            <div class="audit-actions">
                <button id="audit-refresh" class="audit-btn">
                    <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg></span>
                    Refresh
                </button>
                <div class="export-dropdown" id="export-dropdown">
                    <button class="audit-btn audit-btn-primary" id="export-toggle">
                        <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
                        Export
                        <span class="btn-icon" style="opacity:0.7"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                    </button>
                    <div class="export-menu" id="export-menu">
                        <button class="export-option" data-format="csv">
                            <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg></span>
                            Export as CSV
                        </button>
                        <button class="export-option" data-format="xlsx">
                            <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h2v4H8z"/><path d="M14 13h2v4h-2z"/></svg></span>
                            Export as XLSX
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="audit-dashboard">
        <div class="audit-stats" id="audit-stats">
            <div class="stat-card" id="stat-total">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total File Actions</div>
                    <div class="stat-number">-</div>
                </div>
            </div>
            <div class="stat-card stat-fileview" id="stat-fileview">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">File View</div>
                    <div class="stat-number">-</div>
                </div>
            </div>
            <div class="stat-card stat-filedownload" id="stat-filedownload">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">File Download</div>
                    <div class="stat-number">-</div>
                </div>
            </div>
            <div class="stat-card stat-other" id="stat-other">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Other Actions</div>
                    <div class="stat-number">-</div>
                </div>
            </div>
        </div>

        <!-- Filters + Table wrapped together -->
        <div class="audit-toolbar-wrap">
            <div class="audit-toolbar">
                <div class="audit-toolbar-left">
                    <div class="audit-search-wrap">
                        <input type="text" id="filter-search" class="audit-search" placeholder="Search">
                    </div>
                    <div class="multi-filter-dropdown" id="multi-filter-dropdown">
                        <button class="audit-btn audit-btn-icon" id="multi-filter-toggle" title="Filters">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        </button>
                        <div class="multi-filter-menu" id="multi-filter-menu">
                            <div class="multi-filter-group">
                                <label for="filter-action">Action</label>
                                <select id="filter-action" class="audit-filter">
                                    <option value="">All Actions</option>
                                </select>
                            </div>
                            <div class="multi-filter-group">
                                <label for="filter-user">User</label>
                                <select id="filter-user" class="audit-filter">
                                    <option value="">All Users</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="audit-toolbar-right">
                    <input type="date" id="filter-date-from" class="audit-filter-date" title="From date">
                    <span class="audit-filter-sep">→</span>
                    <input type="date" id="filter-date-to" class="audit-filter-date" title="To date">
                    <button id="filter-apply" class="audit-btn audit-btn-icon audit-btn-primary" title="Apply Filters">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <button id="filter-reset" class="audit-btn audit-btn-icon" title="Reset Filters">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/></svg>
                    </button>
                </div>
            </div>

            <!-- Filter info bar -->
            <!-- <div class="audit-filter-info" id="audit-filter-info">
                <div class="filter-count">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Showing <strong id="showing-count">-</strong> of <span id="total-count">-</span> events
                </div>
                <div class="auto-refresh">Auto-refresh in 5m</div>
            </div> -->

            <!-- Table Header (fixed) -->
            <div class="audit-table-scroll-container">
                <table class="audit-table audit-table-head" id="audit-table">
                    <thead>
                        <tr>
                            <th class="audit-sortable audit-sorted" data-sort-key="timestamp" data-sort-dir="desc">Timestamp</th>
                            <th class="audit-sortable" data-sort-key="displayName">User</th>
                            <th class="audit-sortable" data-sort-key="action">Action</th>
                            <th class="audit-sortable" data-sort-key="fileName">File</th>
                            <th class="audit-sortable" data-sort-key="purpose">Purpose</th>
                        </tr>
                    </thead>
                </table>

                <!-- Table Body (scrollable) -->
                <div class="audit-table-wrapper">
                    <table class="audit-table audit-table-body">
                        <tbody id="audit-table-body">
                            <tr><td colspan="5" class="loading">Loading audit logs...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="audit-pagination" id="audit-pagination">
                <div class="audit-pagination-left">
                    <span id="page-range" class="pagination-range">Shows 1 to 50 of 0 entries</span>
                </div>
                <div class="audit-pagination-right">
                    <span class="pagination-label">Rows per page</span>
                    <select id="page-size" class="page-size-select">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="page-info" class="pagination-page">Page 1 of 1</span>
                    <button id="page-prev" class="page-nav-btn" disabled title="Previous page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button id="page-next" class="page-nav-btn" title="Next page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
