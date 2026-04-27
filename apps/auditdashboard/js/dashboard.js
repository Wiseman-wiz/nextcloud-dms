(function () {
    'use strict';

    const baseUrl = OC.generateUrl('/apps/auditdashboard');
    let currentPage = 0;
    const pageSize = 50;
    let totalLogs = 0;
    let currentLogs = [];
    let sortKey = 'timestamp';
    let sortDir = 'desc';

    function sortData(data, key, dir) {
        return data.slice().sort(function (a, b) {
            var valA = a[key];
            var valB = b[key];
            if (valA == null) valA = '';
            if (valB == null) valB = '';
            valA = String(valA).toLowerCase();
            valB = String(valB).toLowerCase();
            var cmp = 0;
            if (valA < valB) cmp = -1;
            else if (valA > valB) cmp = 1;
            return dir === 'asc' ? cmp : -cmp;
        });
    }

    function updateSortHeaders() {
        document.querySelectorAll('#audit-table th.audit-sortable').forEach(function (th) {
            th.classList.remove('audit-sorted');
            th.removeAttribute('data-sort-dir');
            if (th.dataset.sortKey === sortKey) {
                th.classList.add('audit-sorted');
                th.setAttribute('data-sort-dir', sortDir);
            }
        });
    }

    function initSortHeaders() {
        document.querySelectorAll('#audit-table th.audit-sortable').forEach(function (th) {
            th.addEventListener('click', function () {
                var key = th.dataset.sortKey;
                if (sortKey === key) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortKey = key;
                    sortDir = 'asc';
                }
                updateSortHeaders();
                renderTable(currentLogs);
            });
        });
    }

    function init() {
        loadStats();
        loadLogs();
        bindEvents();
        initSortHeaders();
    }

    function bindEvents() {
        document.getElementById('audit-refresh').addEventListener('click', function () {
            loadStats();
            loadLogs();
        });

        // Export dropdown
        var toggle = document.getElementById('export-toggle');
        var menu = document.getElementById('export-menu');
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('open');
        });
        document.addEventListener('click', function () {
            menu.classList.remove('open');
        });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        document.querySelectorAll('.export-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                exportData(btn.dataset.format);
                menu.classList.remove('open');
            });
        });

        document.getElementById('filter-apply').addEventListener('click', function () {
            currentPage = 0;
            loadLogs();
        });
        document.getElementById('filter-reset').addEventListener('click', function () {
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-category').value = '';
            document.getElementById('filter-action').value = '';
            document.getElementById('filter-user').value = '';
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            currentPage = 0;
            loadLogs();
        });
        document.getElementById('page-prev').addEventListener('click', function () {
            if (currentPage > 0) { currentPage--; loadLogs(); }
        });
        document.getElementById('page-next').addEventListener('click', function () {
            if ((currentPage + 1) * pageSize < totalLogs) { currentPage++; loadLogs(); }
        });
        document.getElementById('filter-search').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { currentPage = 0; loadLogs(); }
        });
    }

    function getFilters() {
        return {
            search: document.getElementById('filter-search').value,
            category: document.getElementById('filter-category').value,
            action: document.getElementById('filter-action').value,
            userId: document.getElementById('filter-user').value,
            dateFrom: document.getElementById('filter-date-from').value,
            dateTo: document.getElementById('filter-date-to').value,
        };
    }

    function loadStats() {
        fetch(baseUrl + '/api/stats', {
            headers: { 'requesttoken': OC.requestToken }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.querySelector('#stat-total .stat-number').textContent = data.total || 0;
                document.querySelector('#stat-file .stat-number').textContent = data.byCategory.file || 0;
                document.querySelector('#stat-share .stat-number').textContent = data.byCategory.share || 0;
                document.querySelector('#stat-filerequest .stat-number').textContent = data.byCategory.file_request || data.byCategory.filerequest || 0;

                var userSelect = document.getElementById('filter-user');
                var currentUserVal = userSelect.value;
                userSelect.innerHTML = '<option value="">All Users</option>';
                if (data.users) {
                    data.users.forEach(function (u) {
                        var opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.displayName;
                        userSelect.appendChild(opt);
                    });
                }
                userSelect.value = currentUserVal;

                var actionSelect = document.getElementById('filter-action');
                var currentActionVal = actionSelect.value;
                actionSelect.innerHTML = '<option value="">All Actions</option>';
                if (data.actions) {
                    data.actions.forEach(function (a) {
                        var opt = document.createElement('option');
                        opt.value = a;
                        opt.textContent = formatAction(a);
                        actionSelect.appendChild(opt);
                    });
                }
                actionSelect.value = currentActionVal;
            })
            .catch(function (err) {
                console.error('Failed to load stats:', err);
            });
    }

    function loadLogs() {
        var filters = getFilters();
        var params = new URLSearchParams({
            limit: pageSize,
            offset: currentPage * pageSize,
            category: filters.category,
            action: filters.action,
            userId: filters.userId,
            search: filters.search,
            dateFrom: filters.dateFrom,
            dateTo: filters.dateTo,
        });

        var tbody = document.getElementById('audit-table-body');
        tbody.innerHTML = '<tr><td colspan="5" class="loading">Loading...</td></tr>';

        fetch(baseUrl + '/api/logs?' + params.toString(), {
            headers: { 'requesttoken': OC.requestToken }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                totalLogs = data.total;
                currentLogs = data.logs || [];
                renderTable(currentLogs);
                updatePagination();
            })
            .catch(function (err) {
                console.error('Failed to load logs:', err);
                tbody.innerHTML = '<tr><td colspan="5" class="loading">Failed to load audit logs.</td></tr>';
            });
    }

    function renderTable(logs) {
        var tbody = document.getElementById('audit-table-body');
        if (!logs || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><h3>No audit logs found</h3><p>Events will appear here as users interact with the server.</p></td></tr>';
            return;
        }

        var sorted = sortData(logs, sortKey, sortDir);
        var html = '';
        sorted.forEach(function (log) {
            html += '<tr>';
            html += '<td style="white-space:nowrap">' + escapeHtml(log.timestamp) + '</td>';
            html += '<td><strong>' + escapeHtml(log.displayName || log.userId) + '</strong></td>';
            html += '<td>' + actionBadge(log.action) + '</td>';
            html += '<td>' + categoryBadge(log.category) + '</td>';
            html += '<td class="target-cell" title="' + escapeHtml(log.target) + '">' + escapeHtml(log.target) + '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    function updatePagination() {
        var totalPages = Math.ceil(totalLogs / pageSize);
        document.getElementById('page-info').textContent = 'Page ' + (currentPage + 1) + ' of ' + Math.max(totalPages, 1);
        document.getElementById('page-prev').disabled = currentPage === 0;
        document.getElementById('page-next').disabled = (currentPage + 1) >= totalPages;
    }

    function exportData(format) {
        var filters = getFilters();
        var params = new URLSearchParams({
            category: filters.category,
            userId: filters.userId,
            search: filters.search,
            dateFrom: filters.dateFrom,
            dateTo: filters.dateTo,
            format: format,
        });
        window.location.href = baseUrl + '/api/export?' + params.toString() + '&requesttoken=' + encodeURIComponent(OC.requestToken);
    }

    function categoryBadge(cat) {
        var icons = {
            file: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            share: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
            filerequest: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
            file_request: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>'
        };
        var icon = icons[cat] || '';
        return '<span class="badge badge-' + escapeHtml(cat) + '">' + icon + ' ' + escapeHtml(cat) + '</span>';
    }

    function actionBadge(action) {
        var cls = '';
        if (action.indexOf('deleted') !== -1 || action.indexOf('failed') !== -1) cls = 'danger';
        else if (action.indexOf('created') !== -1 || action.indexOf('success') !== -1) cls = 'success';
        else if (action.indexOf('renamed') !== -1 || action.indexOf('changed') !== -1) cls = 'warning';
        return '<span class="action-badge ' + cls + '">' + escapeHtml(formatAction(action)) + '</span>';
    }

    function formatAction(action) {
        return action.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    setInterval(function () {
        loadStats();
        loadLogs();
    }, 300000);

    document.addEventListener('DOMContentLoaded', init);
})();
