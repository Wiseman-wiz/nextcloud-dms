(function () {
    'use strict';

    var baseUrl = OC.generateUrl('/apps/filerequests');
    var appEl = document.getElementById('filereq-app');
    var isAdmin = appEl && appEl.dataset.isAdmin === '1';
    var isApprover = appEl && appEl.dataset.isApprover === '1';
    var currentUser = appEl ? appEl.dataset.currentUser : '';
    var selectedFiles = [];

    // ── API helpers ──────────────────────────────────────────────────────
    function api(method, path, body) {
        var opts = {
            method: method,
            headers: {
                'requesttoken': OC.requestToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(baseUrl + path, opts).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.error || 'Request failed');
                return data;
            });
        });
    }

    // ── Toast ────────────────────────────────────────────────────────────
    function toast(message, type) {
        var el = document.createElement('div');
        el.className = 'fr-toast fr-toast-' + (type || 'success');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    // ── Escape HTML ──────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ── Format date ──────────────────────────────────────────────────────
    function fmtDate(str) {
        if (!str) return '-';
        var d = new Date(str.replace(' ', 'T'));
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    function fmtDateShort(str) {
        if (!str) return '-';
        var d = new Date(str.replace(' ', 'T'));
        return d.toLocaleDateString();
    }

    // ── Status label helper ─────────────────────────────────────────────
    function statusLabel(status) {
        if (status === 'fulfilled') return 'Completed';
        return esc(status);
    }

    // ── Permissions helper ───────────────────────────────────────────────
    function permLabel(p) {
        var parts = [];
        if (p & 1) parts.push('Read');
        if (p & 2) parts.push('Edit');
        if (p & 4) parts.push('Create');
        if (p & 8) parts.push('Delete');
        if (p & 16) parts.push('Reshare');
        return parts.join(', ') || 'None';
    }

    // ── Tabs ─────────────────────────────────────────────────────────────
    function initTabs() {
        var tabs = document.querySelectorAll('.fr-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                document.querySelectorAll('.fr-panel').forEach(function (p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var panel = document.getElementById('panel-' + tab.dataset.tab);
                if (panel) panel.classList.add('active');
                loadTab(tab.dataset.tab);
            });
        });
    }

    function loadTab(tab) {
        switch (tab) {
            case 'sent': loadSent(); break;
            case 'received': loadReceived(); break;
            case 'admin': loadAdmin(); break;
        }
    }

    // ── Stats cards ─────────────────────────────────────────────────────
    function loadStats(containerId) {
        var el = document.getElementById(containerId);
        if (!el) return;
        api('GET', '/api/stats').then(function (data) {
            var s = data.stats;
            var html = '';
            if (data.isAdmin) {
                html += statCard(s.total, 'Total', '');
                html += statCard(s.pending, 'Pending', 'pending');
                html += statCard(s.fulfilled, 'Completed', 'fulfilled');
                html += statCard(s.rejected, 'Rejected', 'rejected');
            } else {
                html += statCard(s.sent, 'Total Sent', '');
                html += statCard(s.pendingSent, 'Pending', 'pending');
                html += statCard(s.fulfilled, 'Completed', 'fulfilled');
                html += statCard(s.rejected, 'Rejected', 'rejected');
            }
            el.innerHTML = html;
        });
    }

    function statCard(num, label, cls) {
        return '<div class="fr-stat ' + cls + '">' +
            '<div class="fr-stat-number">' + (num || 0) + '</div>' +
            '<div class="fr-stat-label">' + esc(label) + '</div></div>';
    }

    // ── List loaders ─────────────────────────────────────────────────────

    // ── Sorting state ───────────────────────────────────────────────────
    var sentSortKey = 'id';
    var sentSortDir = 'desc';
    var adminSortKey = 'id';
    var adminSortDir = 'desc';

    function sortData(data, key, dir) {
        return data.slice().sort(function (a, b) {
            var valA = a[key];
            var valB = b[key];
            // Treat null/undefined as empty string for comparison
            if (valA == null) valA = '';
            if (valB == null) valB = '';
            // Numeric comparison for id
            if (key === 'id') {
                valA = Number(valA);
                valB = Number(valB);
            } else {
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
            }
            var cmp = 0;
            if (valA < valB) cmp = -1;
            else if (valA > valB) cmp = 1;
            return dir === 'asc' ? cmp : -cmp;
        });
    }

    function updateSortHeaders(tableId, activeKey, activeDir) {
        var table = document.getElementById(tableId);
        if (!table) return;
        table.querySelectorAll('th.fr-sortable').forEach(function (th) {
            th.classList.remove('fr-sorted');
            th.removeAttribute('data-sort-dir');
            if (th.dataset.sortKey === activeKey) {
                th.classList.add('fr-sorted');
                th.setAttribute('data-sort-dir', activeDir);
            }
        });
    }

    // ── My Requests table (sent) ────────────────────────────────────────
    var sentAllData = [];

    function loadSent() {
        loadStats('fr-sent-stats');
        var statusEl = document.getElementById('filter-sent-status');
        var status = statusEl ? statusEl.value : '';
        api('GET', '/api/requests?role=requester&status=' + encodeURIComponent(status) + '&limit=500').then(function (data) {
            sentAllData = data.requests || [];
            renderSentTable();
        });
    }

    function getFilteredSentData() {
        var searchEl = document.getElementById('fr-sent-search');
        var search = searchEl ? searchEl.value.toLowerCase() : '';
        var filtered = sentAllData.filter(function (r) {
            if (!search) return true;
            var haystack = [
                String(r.id), r.department || '', r.province || '',
                r.municipalityCity || '', r.project || '', r.permitDocumentName || '',
                r.status, r.description || ''
            ].join(' ').toLowerCase();
            return haystack.indexOf(search) !== -1;
        });
        return sortData(filtered, sentSortKey, sentSortDir);
    }

    function renderSentTable() {
        var tbody = document.getElementById('fr-sent-tbody');
        if (!tbody) return;
        var filtered = getFilteredSentData();

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="fr-empty">No requests found.</td></tr>';
            return;
        }

        var html = '';
        filtered.forEach(function (r) {
            html += '<tr class="fr-table-row" data-id="' + r.id + '">';
            html += '<td>' + r.id + '</td>';
            html += '<td>' + esc(r.permitDocumentName || '-') + '</td>';
            html += '<td>' + esc(r.department || '-') + '</td>';
            html += '<td><span class="fr-status fr-status-' + r.status + '">' + statusLabel(r.status) + '</span></td>';
            html += '<td>' + (r.dateNeeded ? fmtDateShort(r.dateNeeded) : '-') + '</td>';
            html += '<td>' + fmtDate(r.createdAt) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.fr-table-row').forEach(function (row) {
            row.addEventListener('click', function () {
                openDetail(parseInt(row.dataset.id));
            });
        });
    }

    function initSentTable() {
        var searchEl = document.getElementById('fr-sent-search');
        if (searchEl) {
            searchEl.addEventListener('input', renderSentTable);
        }

        var refreshBtn = document.getElementById('fr-sent-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadSent();
                toast('Refreshed');
            });
        }

        var csvBtn = document.getElementById('fr-sent-export-csv');
        if (csvBtn) {
            csvBtn.addEventListener('click', function () {
                exportSentData('csv');
            });
        }

        var xlsxBtn = document.getElementById('fr-sent-export-xlsx');
        if (xlsxBtn) {
            xlsxBtn.addEventListener('click', function () {
                exportSentData('xlsx');
            });
        }
    }

    function exportSentData(format) {
        var rows = getFilteredSentData();
        if (rows.length === 0) {
            toast('No data to export', 'error');
            return;
        }

        var headers = ['ID', 'Department', 'Province', 'Municipality/City',
            'Project', 'Permit/Document', 'Status', 'Description',
            'Reject Reason', 'Date Needed', 'Created At', 'Updated At'];

        function rowValues(r) {
            return [
                r.id, r.department || '', r.province || '',
                r.municipalityCity || '', r.project || '', r.permitDocumentName || '',
                statusLabel(r.status), r.description || '', r.rejectReason || '',
                r.dateNeeded || '', fmtDate(r.createdAt), fmtDate(r.updatedAt)
            ];
        }

        if (format === 'csv') {
            var csvContent = headers.join(',') + '\n';
            rows.forEach(function (r) {
                var vals = rowValues(r).map(function (v) {
                    return '"' + String(v).replace(/"/g, '""') + '"';
                });
                csvContent += vals.join(',') + '\n';
            });
            var blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
            downloadBlob(blob, 'my_requests.csv');
        } else {
            function xmlEsc(s) {
                if (s === null || s === undefined) return '';
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            var xml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
                '<?mso-application progid="Excel.Sheet"?>\n' +
                '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"\n' +
                ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n' +
                '<Styles><Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#F5F5F5" ss:Pattern="Solid"/></Style></Styles>\n' +
                '<Worksheet ss:Name="My Requests"><Table>\n';

            xml += '<Row ss:StyleID="hdr">';
            headers.forEach(function (h) {
                xml += '<Cell><Data ss:Type="String">' + xmlEsc(h) + '</Data></Cell>';
            });
            xml += '</Row>\n';

            rows.forEach(function (r) {
                var vals = rowValues(r);
                xml += '<Row>';
                vals.forEach(function (v, i) {
                    var type = (i === 0) ? 'Number' : 'String';
                    xml += '<Cell><Data ss:Type="' + type + '">' + xmlEsc(v) + '</Data></Cell>';
                });
                xml += '</Row>\n';
            });

            xml += '</Table></Worksheet></Workbook>';
            var blob = new Blob([xml], { type: 'application/vnd.ms-excel' });
            downloadBlob(blob, 'my_requests.xls');
        }
    }

    function loadReceived() {
        var status = document.getElementById('filter-received-status') ? document.getElementById('filter-received-status').value : '';
        api('GET', '/api/requests?incoming=1&status=' + encodeURIComponent(status)).then(function (data) {
            renderRequestList(data.requests, 'fr-received-list');
        });
    }

    // ── All Requests table (admin) ─────────────────────────────────────
    var adminAllData = [];

    function loadAdmin() {
        loadStats('fr-admin-stats');
        var status = document.getElementById('filter-admin-status') ? document.getElementById('filter-admin-status').value : '';
        api('GET', '/api/requests?admin=1&status=' + encodeURIComponent(status) + '&limit=500').then(function (data) {
            adminAllData = data.requests || [];
            renderAdminTable();
        });
    }

    function getFilteredAdminData() {
        var searchEl = document.getElementById('fr-admin-search');
        var search = searchEl ? searchEl.value.toLowerCase() : '';
        var filtered = adminAllData.filter(function (r) {
            if (!search) return true;
            var haystack = [
                String(r.id), r.requesterId, r.custodianId || '',
                r.department || '', r.province || '', r.municipalityCity || '',
                r.project || '', r.permitDocumentName || '', r.status,
                r.description || ''
            ].join(' ').toLowerCase();
            return haystack.indexOf(search) !== -1;
        });
        return sortData(filtered, adminSortKey, adminSortDir);
    }

    function renderAdminTable() {
        var tbody = document.getElementById('fr-admin-tbody');
        if (!tbody) return;
        var filtered = getFilteredAdminData();

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="fr-empty">No requests found.</td></tr>';
            return;
        }

        var html = '';
        filtered.forEach(function (r) {
            html += '<tr class="fr-table-row" data-id="' + r.id + '">';
            html += '<td>' + r.id + '</td>';
            html += '<td>' + esc(r.permitDocumentName || '-') + '</td>';
            html += '<td>' + esc(r.requesterDisplayName || r.requesterId) + '</td>';
            html += '<td>' + esc(r.custodianDisplayName || r.custodianId || '-') + '</td>';
            html += '<td>' + esc(r.department || '-') + '</td>';
            html += '<td><span class="fr-status fr-status-' + r.status + '">' + statusLabel(r.status) + '</span></td>';
            html += '<td>' + (r.dateNeeded ? fmtDateShort(r.dateNeeded) : '-') + '</td>';
            html += '<td>' + fmtDate(r.createdAt) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        // Bind row click to open detail
        tbody.querySelectorAll('.fr-table-row').forEach(function (row) {
            row.addEventListener('click', function () {
                openDetail(parseInt(row.dataset.id));
            });
        });
    }

    function initAdminTable() {
        var searchEl = document.getElementById('fr-admin-search');
        if (searchEl) {
            searchEl.addEventListener('input', renderAdminTable);
        }

        var refreshBtn = document.getElementById('fr-admin-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadAdmin();
                toast('Refreshed');
            });
        }

        var csvBtn = document.getElementById('fr-admin-export-csv');
        if (csvBtn) {
            csvBtn.addEventListener('click', function () {
                exportAdminData('csv');
            });
        }

        var xlsxBtn = document.getElementById('fr-admin-export-xlsx');
        if (xlsxBtn) {
            xlsxBtn.addEventListener('click', function () {
                exportAdminData('xlsx');
            });
        }
    }

    function exportAdminData(format) {
        var rows = getFilteredAdminData();
        if (rows.length === 0) {
            toast('No data to export', 'error');
            return;
        }

        var headers = ['ID', 'Requester', 'Handled By', 'Department', 'Province',
            'Municipality/City', 'Project', 'Permit/Document', 'Status', 'Description',
            'Reject Reason', 'Date Needed', 'Created At', 'Updated At'];

        function rowValues(r) {
            return [
                r.id, r.requesterId, r.custodianId || '', r.department || '',
                r.province || '', r.municipalityCity || '', r.project || '',
                r.permitDocumentName || '', statusLabel(r.status), r.description || '',
                r.rejectReason || '', r.dateNeeded || '', fmtDate(r.createdAt), fmtDate(r.updatedAt)
            ];
        }

        if (format === 'csv') {
            var csvContent = headers.join(',') + '\n';
            rows.forEach(function (r) {
                var vals = rowValues(r).map(function (v) {
                    return '"' + String(v).replace(/"/g, '""') + '"';
                });
                csvContent += vals.join(',') + '\n';
            });
            var blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
            downloadBlob(blob, 'file_requests.csv');
        } else {
            // Excel 2003 XML Spreadsheet
            function xmlEsc(s) {
                if (s === null || s === undefined) return '';
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            var xml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
                '<?mso-application progid="Excel.Sheet"?>\n' +
                '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"\n' +
                ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n' +
                '<Styles><Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#F5F5F5" ss:Pattern="Solid"/></Style></Styles>\n' +
                '<Worksheet ss:Name="All Requests"><Table>\n';

            // Header
            xml += '<Row ss:StyleID="hdr">';
            headers.forEach(function (h) {
                xml += '<Cell><Data ss:Type="String">' + xmlEsc(h) + '</Data></Cell>';
            });
            xml += '</Row>\n';

            // Data
            rows.forEach(function (r) {
                var vals = rowValues(r);
                xml += '<Row>';
                vals.forEach(function (v, i) {
                    var type = (i === 0) ? 'Number' : 'String';
                    xml += '<Cell><Data ss:Type="' + type + '">' + xmlEsc(v) + '</Data></Cell>';
                });
                xml += '</Row>\n';
            });

            xml += '</Table></Worksheet></Workbook>';
            var blob = new Blob([xml], { type: 'application/vnd.ms-excel' });
            downloadBlob(blob, 'file_requests.xls');
        }
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // ── Render request list ──────────────────────────────────────────────
    function renderRequestList(requests, containerId) {
        var container = document.getElementById(containerId);
        if (!requests || requests.length === 0) {
            container.innerHTML = '<p class="fr-empty">No requests found.</p>';
            return;
        }

        var html = '';
        requests.forEach(function (req) {
            var isRequester = req.requesterId === currentUser;
            var canApprove = isApprover && !isRequester;

            html += '<div class="fr-request-card" data-id="' + req.id + '">';
            html += '<div class="fr-card-body">';
            html += '<div class="fr-card-title">' + esc(req.permitDocumentName || req.department || 'Request #' + req.id) + '</div>';
            html += '<div class="fr-card-meta">';
            html += '<span>Requested by: ' + esc(req.requesterDisplayName || req.requesterId) + '</span>';
            if (req.custodianId) {
                var actionLabel = (req.status === 'rejected') ? 'Rejected by' : 'Handled by';
                html += '<span>' + actionLabel + ': ' + esc(req.custodianDisplayName || req.custodianId) + '</span>';
            }
            html += '<span>' + fmtDate(req.createdAt) + '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="fr-card-actions">';
            html += '<span class="fr-status fr-status-' + req.status + '">' + statusLabel(req.status) + '</span>';

            if (canApprove && req.status === 'pending') {
                html += ' <button class="fr-btn fr-btn-success fr-btn-sm fr-action-accept" data-id="' + req.id + '">Accept</button>';
                html += ' <button class="fr-btn fr-btn-danger fr-btn-sm fr-action-reject" data-id="' + req.id + '">Reject</button>';
            }
            if (canApprove && req.status === 'accepted') {
                html += ' <button class="fr-btn fr-btn-primary fr-btn-sm fr-action-accept" data-id="' + req.id + '">Share Files</button>';
            }
            if (isRequester && (req.status === 'pending' || req.status === 'accepted')) {
                html += ' <button class="fr-btn fr-btn-sm fr-action-cancel" data-id="' + req.id + '">Cancel</button>';
            }

            html += '</div></div>';
        });
        container.innerHTML = html;

        // Bind card click for detail
        container.querySelectorAll('.fr-request-card').forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e.target.closest('button')) return;
                openDetail(parseInt(card.dataset.id));
            });
        });

        // Bind action buttons
        container.querySelectorAll('.fr-action-accept').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                acceptRequest(parseInt(btn.dataset.id));
            });
        });
        container.querySelectorAll('.fr-action-reject').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                openRejectModal(parseInt(btn.dataset.id));
            });
        });
        container.querySelectorAll('.fr-action-cancel').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                openCancelModal(parseInt(btn.dataset.id));
            });
        });
    }

    // ── Request Detail ───────────────────────────────────────────────────
    function openDetail(id) {
        var overlay = document.getElementById('fr-detail-overlay');
        var content = document.getElementById('fr-detail-content');
        content.innerHTML = '<p class="fr-empty">Loading...</p>';
        overlay.style.display = 'flex';
        history.pushState(null, '', window.location.pathname + '?open=' + id);

        Promise.all([
            api('GET', '/api/requests/' + id),
            api('GET', '/api/requests/' + id + '/activity')
        ]).then(function (results) {
            var req = results[0].request;
            var fulfillments = results[0].fulfillments || [];
            var activity = results[1].activity || [];
            var isRequester = req.requesterId === currentUser;
            var canApprove = isApprover && !isRequester;

            var html = '<div class="fr-detail-header">';
            html += '<h3>' + esc(req.permitDocumentName || req.department || 'Request #' + req.id) + '</h3>';
            html += '<button class="fr-modal-close fr-detail-close-btn">&times;</button>';
            html += '</div>';

            html += '<span class="fr-status fr-status-' + req.status + '">' + statusLabel(req.status) + '</span>';

            html += '<div class="fr-detail-section"><h4>Details</h4>';
            html += '<dl class="fr-detail-meta">';
            html += '<dt>Requester</dt><dd>' + esc(req.requesterDisplayName || req.requesterId) + '</dd>';
            if (req.custodianId) {
                var handlerLabel = (req.status === 'rejected') ? 'Rejected by' : (req.status === 'fulfilled') ? 'Completed by' : 'Approved by';
                html += '<dt>' + handlerLabel + '</dt><dd>' + esc(req.custodianDisplayName || req.custodianId) + '</dd>';
            }
            if (req.department) html += '<dt>Department</dt><dd>' + esc(req.department) + '</dd>';
            if (req.province) html += '<dt>Province</dt><dd class="fr-highlight">' + esc(req.province) + '</dd>';
            if (req.municipalityCity) html += '<dt>Municipality / City</dt><dd class="fr-highlight">' + esc(req.municipalityCity) + '</dd>';
            if (req.project) html += '<dt>Project</dt><dd class="fr-highlight">' + esc(req.project) + '</dd>';
            if (req.permitDocumentName) html += '<dt>Permit / Document</dt><dd class="fr-highlight">' + esc(req.permitDocumentName) + '</dd>';
            if (req.dateNeeded) html += '<dt>Date Needed</dt><dd>' + fmtDateShort(req.dateNeeded) + '</dd>';
            html += '<dt>Created</dt><dd>' + fmtDate(req.createdAt) + '</dd>';
            html += '<dt>Last Updated</dt><dd>' + fmtDate(req.updatedAt) + '</dd>';
            html += '</dl></div>';

            if (req.description) {
                html += '<div class="fr-detail-section"><h4>Reason</h4>';
                html += '<div class="fr-detail-description">' + esc(req.description) + '</div></div>';
            }

            if (req.rejectReason) {
                var reasonLabel = req.status === 'cancelled' ? 'Cancellation Reason' : 'Rejection Reason';
                html += '<div class="fr-detail-section"><h4>' + reasonLabel + '</h4>';
                html += '<div class="fr-reject-reason">' + esc(req.rejectReason) + '</div></div>';
            }

            // Fulfilled files
            if (fulfillments.length > 0) {
                html += '<div class="fr-detail-section"><h4>Shared Files (' + fulfillments.length + ')</h4>';
                html += '<div class="fr-shared-files">';
                fulfillments.forEach(function (f) {
                    var fileUrl;
                    var origin = window.location.protocol + '//' + window.location.host;
                    if (f.shareToken) {
                        fileUrl = origin + OC.generateUrl('/s/' + f.shareToken);
                    } else if (f.nodeId) {
                        var dir = f.filePath.substring(0, f.filePath.lastIndexOf('/')) || '/';
                        fileUrl = origin + OC.generateUrl('/apps/files/files/' + f.nodeId + '?dir=' + encodeURIComponent(dir) + '&openfile=true&fileid=' + f.nodeId);
                    } else {
                        fileUrl = '#';
                    }
                    html += '<a class="fr-shared-file fr-shared-file-link" href="' + esc(fileUrl) + '" target="_blank" rel="noopener" title="Click to open ' + esc(f.fileName) + '">';
                    html += '<span class="fr-shared-file-icon">' + (f.filePath.endsWith('/') ? '&#128193;' : '&#128196;') + '</span>';
                    html += '<span class="fr-shared-file-name">' + esc(f.fileName) + '</span>';
                    html += '<span class="fr-shared-file-badges">';
                    html += '<span class="fr-badge">' + permLabel(f.permissions) + '</span>';
                    if (f.locked) html += '<span class="fr-badge fr-badge-lock">Locked</span>';
                    if (f.passwordProtected) html += '<span class="fr-badge fr-badge-password">Password</span>';
                    if (f.shareExpiry) html += '<span class="fr-badge fr-badge-expiry">Exp: ' + fmtDateShort(f.shareExpiry) + '</span>';
                    if (f.shareToken) html += '<span class="fr-badge">Link</span>';
                    html += '</span></a>';
                });
                html += '</div></div>';
            }

            // Actions
            html += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">';
            if (canApprove && req.status === 'pending') {
                html += '<button class="fr-btn fr-btn-success" data-action="accept" data-id="' + req.id + '">Accept</button>';
                html += '<button class="fr-btn fr-btn-danger" data-action="reject" data-id="' + req.id + '">Reject</button>';
            }
            if (canApprove && req.status === 'accepted') {
                html += '<button class="fr-btn fr-btn-primary" data-action="accept" data-id="' + req.id + '">Share Files</button>';
                html += '<button class="fr-btn fr-btn-danger" data-action="reject" data-id="' + req.id + '">Reject</button>';
            }
            if (isRequester && (req.status === 'pending' || req.status === 'accepted')) {
                html += '<button class="fr-btn" data-action="cancel" data-id="' + req.id + '">Cancel Request</button>';
            }
            html += '</div>';

            // Activity timeline
            if (activity.length > 0) {
                html += '<div class="fr-detail-section"><h4>Activity History</h4>';
                html += '<div class="fr-timeline">';
                activity.forEach(function (a) {
                    html += '<div class="fr-timeline-item">';
                    html += '<div class="fr-timeline-dot ' + a.action + '"></div>';
                    html += '<span class="fr-timeline-time">' + fmtDate(a.createdAt) + '</span>';
                    html += '<span class="fr-timeline-msg"><strong>' + esc(a.userDisplayName || a.userId) + '</strong> — ' + esc(a.action);
                    if (a.message) html += ': ' + esc(a.message);
                    html += '</span></div>';
                });
                html += '</div></div>';
            }

            content.innerHTML = html;
        }).catch(function (err) {
            content.innerHTML = '<p class="fr-empty">Error: ' + esc(err.message) + '</p>';
        });
    }

    // ── Accept ───────────────────────────────────────────────────────────
    function acceptRequest(id) {
        openFulfillModal(id);
    }

    // ── Cancel modal ────────────────────────────────────────────────────
    function openCancelModal(id) {
        document.getElementById('fr-cancel-id').value = id;
        document.getElementById('fr-cancel-reason').value = '';
        document.getElementById('fr-cancel-overlay').style.display = 'flex';
    }

    function initCancelModal() {
        document.getElementById('fr-cancel-close').addEventListener('click', closeCancel);
        document.getElementById('fr-cancel-back').addEventListener('click', closeCancel);
        document.getElementById('fr-cancel-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var id = document.getElementById('fr-cancel-id').value;
            var reason = document.getElementById('fr-cancel-reason').value.trim();
            if (!reason) return;
            api('POST', '/api/requests/' + id + '/cancel', { reason: reason }).then(function () {
                closeCancel();
                toast('Request cancelled');
                refreshAll();
            }).catch(function (err) { toast(err.message, 'error'); });
        });
    }
    function closeCancel() { document.getElementById('fr-cancel-overlay').style.display = 'none'; }

    // ── Reject modal ─────────────────────────────────────────────────────
    function openRejectModal(id) {
        document.getElementById('fr-reject-id').value = id;
        document.getElementById('fr-reject-reason').value = '';
        document.getElementById('fr-reject-overlay').style.display = 'flex';
    }

    function initRejectModal() {
        document.getElementById('fr-reject-close').addEventListener('click', closeReject);
        document.getElementById('fr-reject-cancel').addEventListener('click', closeReject);
        document.getElementById('fr-reject-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var id = document.getElementById('fr-reject-id').value;
            var reason = document.getElementById('fr-reject-reason').value.trim();
            if (!reason) return;
            api('POST', '/api/requests/' + id + '/reject', { reason: reason }).then(function () {
                closeReject();
                toast('Request rejected');
                refreshAll();
            }).catch(function (err) { toast(err.message, 'error'); });
        });
    }
    function closeReject() { document.getElementById('fr-reject-overlay').style.display = 'none'; }

    // ── New Request modal ────────────────────────────────────────────────
    function initNewRequest() {
        document.getElementById('fr-new-request').addEventListener('click', function () {
            document.getElementById('fr-new-form').reset();
            document.getElementById('fr-new-overlay').style.display = 'flex';
        });
        document.getElementById('fr-new-close').addEventListener('click', closeNew);
        document.getElementById('fr-new-cancel').addEventListener('click', closeNew);

        // Submit
        document.getElementById('fr-new-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var description = document.getElementById('fr-new-desc').value.trim();
            var department = document.getElementById('fr-new-department').value.trim();
            var province = document.getElementById('fr-new-province').value.trim();
            var municipalityCity = document.getElementById('fr-new-municipality').value.trim();
            var project = document.getElementById('fr-new-project').value.trim();
            var permitDocumentName = document.getElementById('fr-new-permit').value.trim();
            var dateNeeded = document.getElementById('fr-new-date-needed').value;

            // Auto-generate title from permit/document name or department
            var title = permitDocumentName || department || 'File Request';

            api('POST', '/api/requests', {
                title: title,
                description: description || null,
                department: department || null,
                province: province || null,
                municipalityCity: municipalityCity || null,
                project: project || null,
                permitDocumentName: permitDocumentName || null,
                dateNeeded: dateNeeded || null
            }).then(function () {
                closeNew();
                toast('Request submitted!');
                refreshAll();
            }).catch(function (err) { toast(err.message, 'error'); });
        });
    }
    function closeNew() {
        document.getElementById('fr-new-overlay').style.display = 'none';
    }

    // ── Fulfill modal ────────────────────────────────────────────────────
    function openFulfillModal(id) {
        selectedFiles = [];
        document.getElementById('fr-fulfill-id').value = id;
        document.getElementById('fr-fulfill-submit').disabled = true;
        renderFulfillFiles();

        api('GET', '/api/requests/' + id).then(function (data) {
            var req = data.request;
            var html = '<div style="margin-bottom:12px;padding:10px 14px;background:var(--color-background-dark,#f5f5f5);border-radius:8px;font-size:13px">';
            html += '<strong>' + esc(req.permitDocumentName || req.department || 'Request #' + req.id) + '</strong>';
            if (req.description) html += '<p style="margin:6px 0 0;color:var(--color-text-maxcontrast,#767676)">' + esc(req.description) + '</p>';
            html += '<p style="margin:6px 0 0;font-size:12px">Requested by: <strong>' + esc(req.requesterDisplayName || req.requesterId) + '</strong></p>';
            html += '</div>';
            document.getElementById('fr-fulfill-request-info').innerHTML = html;
        });

        document.getElementById('fr-fulfill-overlay').style.display = 'flex';
    }

    function renderFulfillFiles() {
        var container = document.getElementById('fr-fulfill-files');
        if (selectedFiles.length === 0) {
            container.innerHTML = '<p class="fr-empty">No files or folders selected yet. Click below to add.</p>';
            document.getElementById('fr-fulfill-submit').disabled = true;
            return;
        }
        document.getElementById('fr-fulfill-submit').disabled = false;

        var html = '';
        selectedFiles.forEach(function (f, i) {
            var fileName = f.path.split('/').pop() || f.path;
            var isFolder = f.path.endsWith('/') || !fileName.includes('.');
            var icon = isFolder ? '&#128193;' : '&#128196;';
            html += '<div class="fr-file-entry" data-index="' + i + '">';
            html += '<div class="fr-file-entry-header">';
            html += '<span class="fr-file-entry-name" title="' + esc(f.path) + '">' + icon + ' ' + esc(fileName) + '</span>';
            html += '<button type="button" class="fr-file-entry-remove" data-index="' + i + '">&times;</button>';
            html += '</div>';
            html += '</div>';
        });
        container.innerHTML = html;

        container.querySelectorAll('.fr-file-entry-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedFiles.splice(parseInt(btn.dataset.index), 1);
                renderFulfillFiles();
            });
        });
    }

    function initFulfillModal() {
        document.getElementById('fr-fulfill-close').addEventListener('click', closeFulfill);
        document.getElementById('fr-fulfill-cancel').addEventListener('click', closeFulfill);

        // Expiry toggle
        var expiryToggle = document.getElementById('fr-expiry-toggle');
        var expiryInput = document.getElementById('fr-fulfill-expiry');
        expiryToggle.addEventListener('change', function () {
            expiryInput.disabled = !expiryToggle.checked;
            if (expiryToggle.checked) {
                expiryInput.focus();
            } else {
                expiryInput.value = '';
            }
        });

        document.getElementById('fr-add-files').addEventListener('click', function () {
            if (OC && OC.dialogs && OC.dialogs.filepicker) {
                OC.dialogs.filepicker(
                    'Select files or folders to share',
                    function (paths) {
                        if (!Array.isArray(paths)) paths = [paths];
                        paths.forEach(function (p) {
                            var exists = selectedFiles.some(function (f) { return f.path === p; });
                            if (!exists) {
                                selectedFiles.push({ path: p });
                            }
                        });
                        renderFulfillFiles();
                    },
                    true,      // multiselect
                    undefined, // mimetype filter
                    true,      // modal
                    undefined, // type - allow files and folders
                    undefined, // path
                    { allowDirectoryChooser: true }
                );
            } else {
                var path = prompt('Enter file or folder path (e.g., /Documents/report.pdf):');
                if (path) {
                    selectedFiles.push({ path: path });
                    renderFulfillFiles();
                }
            }
        });

        document.getElementById('fr-fulfill-submit').addEventListener('click', function () {
            if (selectedFiles.length === 0) return;
            var id = document.getElementById('fr-fulfill-id').value;
            var btn = document.getElementById('fr-fulfill-submit');
            btn.disabled = true;
            btn.textContent = 'Sharing...';

            var expiryDate = null;
            if (expiryToggle.checked && expiryInput.value) {
                expiryDate = expiryInput.value;
            }

            api('POST', '/api/requests/' + id + '/fulfill', {
                files: selectedFiles,
                expiryDate: expiryDate
            }).then(function () {
                closeFulfill();
                toast('Request accepted and files shared!');
                refreshAll();
            }).catch(function (err) {
                toast(err.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Share and Complete';
            });
        });
    }
    function closeFulfill() {
        document.getElementById('fr-fulfill-overlay').style.display = 'none';
        selectedFiles = [];
        document.getElementById('fr-expiry-toggle').checked = false;
        document.getElementById('fr-fulfill-expiry').disabled = true;
        document.getElementById('fr-fulfill-expiry').value = '';
    }


    // ── Sortable headers ────────────────────────────────────────────────
    function initSortHeaders() {
        var sentTable = document.getElementById('fr-sent-table');
        if (sentTable) {
            sentTable.querySelectorAll('th.fr-sortable').forEach(function (th) {
                th.addEventListener('click', function () {
                    var key = th.dataset.sortKey;
                    if (sentSortKey === key) {
                        sentSortDir = sentSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sentSortKey = key;
                        sentSortDir = 'asc';
                    }
                    updateSortHeaders('fr-sent-table', sentSortKey, sentSortDir);
                    renderSentTable();
                });
            });
        }

        var adminTable = document.getElementById('fr-admin-table');
        if (adminTable) {
            adminTable.querySelectorAll('th.fr-sortable').forEach(function (th) {
                th.addEventListener('click', function () {
                    var key = th.dataset.sortKey;
                    if (adminSortKey === key) {
                        adminSortDir = adminSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        adminSortKey = key;
                        adminSortDir = 'asc';
                    }
                    updateSortHeaders('fr-admin-table', adminSortKey, adminSortDir);
                    renderAdminTable();
                });
            });
        }
    }

    // ── Filter bindings ──────────────────────────────────────────────────
    function initFilters() {
        var fs = document.getElementById('filter-sent-status');
        if (fs) fs.addEventListener('change', loadSent);
        var fr = document.getElementById('filter-received-status');
        if (fr) fr.addEventListener('change', loadReceived);
        var fa = document.getElementById('filter-admin-status');
        if (fa) fa.addEventListener('change', loadAdmin);
    }

    // ── Close overlays on background click or close button ──────────────
    function closeDetailOverlay() {
        document.getElementById('fr-detail-overlay').style.display = 'none';
        history.replaceState(null, '', window.location.pathname);
    }

    function initOverlays() {
        document.querySelectorAll('.fr-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                // Close when clicking the dark backdrop
                if (e.target === overlay) {
                    if (overlay.id === 'fr-detail-overlay') {
                        closeDetailOverlay();
                    } else {
                        overlay.style.display = 'none';
                    }
                    return;
                }
                // Close when clicking any close button (or its child)
                var closeBtn = e.target.closest('.fr-modal-close, .fr-detail-close-btn');
                if (closeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (overlay.id === 'fr-detail-overlay') {
                        closeDetailOverlay();
                    } else {
                        overlay.style.display = 'none';
                    }
                }
            });
        });

        // Event delegation for detail overlay action buttons
        var detailOverlay = document.getElementById('fr-detail-overlay');
        if (detailOverlay) {
            detailOverlay.addEventListener('click', function (e) {
                var actionBtn = e.target.closest('[data-action]');
                if (actionBtn) {
                    closeDetailOverlay();
                    var action = actionBtn.dataset.action;
                    var rid = parseInt(actionBtn.dataset.id);
                    if (action === 'accept') acceptRequest(rid);
                    else if (action === 'reject') openRejectModal(rid);
                    else if (action === 'cancel') openCancelModal(rid);
                }
            });
        }
    }

    // ── Refresh all ──────────────────────────────────────────────────────
    function refreshAll() {
        var activeTab = document.querySelector('.fr-tab.active');
        if (activeTab) loadTab(activeTab.dataset.tab);
    }

    // ── Init ─────────────────────────────────────────────────────────────
    function init() {
        initTabs();
        initNewRequest();
        initRejectModal();
        initCancelModal();
        initFulfillModal();
        initFilters();
        initSentTable();
        initAdminTable();
        initSortHeaders();
        initOverlays();

        // Load the active tab on startup
        var activeTab = document.querySelector('.fr-tab.active');
        if (activeTab) loadTab(activeTab.dataset.tab);

        // Open detail modal if navigated via notification (?open=ID)
        var openId = parseInt(appEl && appEl.dataset.openRequest);
        if (openId > 0) {
            openDetail(openId);
        }

        // Close modal on browser back button
        window.addEventListener('popstate', function () {
            var overlay = document.getElementById('fr-detail-overlay');
            if (overlay && overlay.style.display !== 'none') {
                overlay.style.display = 'none';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
