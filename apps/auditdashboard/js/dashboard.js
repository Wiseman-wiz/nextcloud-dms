(function () {
    'use strict';

    const baseUrl = OC.generateUrl('/apps/auditdashboard');
    let currentPage = 0;
    let pageSize = 50;
    let totalLogs = 0;
    let currentLogs = [];
    let sortKey = 'timestamp';
    let sortDir = 'desc';

    /* ---------- Helpers ---------- */

    function userInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(function (n) { return n[0]; }).join('').slice(0, 2).toUpperCase();
    }

    function userColorIndex(name) {
        var h = 0;
        for (var i = 0; i < name.length; i++) {
            h = ((h * 31) + name.charCodeAt(i)) >>> 0;
        }
        return h % 6;
    }

    /* ---------- Sort ---------- */

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

    /* ---------- Init ---------- */

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
        document.getElementById('page-size').addEventListener('change', function () {
            pageSize = parseInt(this.value, 10);
            currentPage = 0;
            loadLogs();
        });
    }

    function getFilters() {
        return {
            search: document.getElementById('filter-search').value,
            category: '',
            action: document.getElementById('filter-action').value,
            userId: document.getElementById('filter-user').value,
            dateFrom: document.getElementById('filter-date-from').value,
            dateTo: document.getElementById('filter-date-to').value,
        };
    }

    /* ---------- Stats ---------- */

    function loadStats() {
        fetch(baseUrl + '/api/stats', {
            headers: { 'requesttoken': OC.requestToken }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.querySelector('#stat-total .stat-number').textContent = data.total || 0;
                document.querySelector('#stat-fileview .stat-number').textContent = data.fileView || 0;
                document.querySelector('#stat-filedownload .stat-number').textContent = data.fileDownload || 0;
                document.querySelector('#stat-other .stat-number').textContent = data.other || 0;

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

    /* ---------- Logs ---------- */

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
                updateFilterInfo();
            })
            .catch(function (err) {
                console.error('Failed to load logs:', err);
                tbody.innerHTML = '<tr><td colspan="5" class="loading">Failed to load audit logs.</td></tr>';
            });
    }

    /* ---------- Render ---------- */

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
            html += '<td class="ts-cell">' + escapeHtml(log.timestamp) + '</td>';
            html += '<td>' + userCell(log.displayName || log.userId) + '</td>';
            html += '<td>' + actionBadge(log.action) + '</td>';
            html += '<td class="file-cell" title="' + escapeHtml(log.target) + '">' + escapeHtml(log.fileName || '') + '</td>';
            html += '<td class="purpose-cell">' + purposeContent(log.action, log.purpose) + '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    function updateFilterInfo() {
        var showingEl = document.getElementById('showing-count');
        var totalEl = document.getElementById('total-count');
        if (showingEl) showingEl.textContent = currentLogs.length;
        if (totalEl) totalEl.textContent = totalLogs;
    }

    function updatePagination() {
        var totalPages = Math.ceil(totalLogs / pageSize);
        var rangeStart = totalLogs === 0 ? 0 : currentPage * pageSize + 1;
        var rangeEnd = Math.min((currentPage + 1) * pageSize, totalLogs);
        document.getElementById('page-range').textContent = 'Shows ' + rangeStart + ' to ' + rangeEnd + ' of ' + totalLogs + ' entries';
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

    /* ---------- Cell renderers ---------- */

    function userCell(name) {
        var idx = userColorIndex(name);
        var inits = userInitials(name);
        return '<div class="user-cell">'
            + '<div class="user-avatar user-avatar-' + idx + '">' + escapeHtml(inits) + '</div>'
            + '<span class="user-name">' + escapeHtml(name) + '</span>'
            + '</div>';
    }

    function categoryBadge(cat) {
        var labels = {
            file: 'File',
            share: 'Share',
            filerequest: 'Request',
            file_request: 'Request'
        };
        var label = labels[cat] || cat;
        return '<span class="badge badge-' + escapeHtml(cat) + '">'
            + '<span class="badge-dot"></span> '
            + escapeHtml(label)
            + '</span>';
    }

    function actionBadge(action) {
        var icons = {
            file_read: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            file_downloaded: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17V3"/><path d="m6 11 6 6 6-6"/><path d="M19 21H5"/></svg>',
            share_created: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
            file_deleted: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
            file_created: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
        };
        var icon = icons[action] || '';
        var iconHtml = icon ? '<span class="action-icon">' + icon + '</span>' : '';
        return '<span class="action-badge">'
            + iconHtml
            + escapeHtml(formatAction(action))
            + '</span>';
    }

    function formatAction(action) {
        return action.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function purposeContent(action, purpose) {
        if (action !== 'file_downloaded' || !purpose) {
            return '<span class="purpose-empty">—</span>';
        }
        return '<span class="purpose-badge">' + escapeHtml(purpose) + '</span>';
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ---------- Auto-refresh ---------- */

    setInterval(function () {
        loadStats();
        loadLogs();
    }, 300000);

    document.addEventListener('DOMContentLoaded', init);
})();
