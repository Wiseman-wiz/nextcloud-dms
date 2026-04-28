/**
 * download_control – files-hook.js
 * Nextcloud 33 compatible
 *
 * Two interception strategies:
 *  VIEW  → capture-phase click on button.files-list__row-name-link
 *  DOWNLOAD → MutationObserver watching document.body for <a download> nodes
 *             that NC 33 creates programmatically when a download is triggered.
 *
 * Modals are built with plain DOM (OC.dialogs renders raw HTML as text in NC 33).
 */
(function () {
	'use strict';

	const APP_ID = 'download_control';

	/* ─────────────────────────────────────────
	   Session cache – skip re-showing disclaimer
	   for the same file within one browser tab.
	   ───────────────────────────────────────── */
	const _viewed = new Set();

	/* ─────────────────────────────────────────
	   Backend helpers
	   ───────────────────────────────────────── */
	function postLog(endpoint, body) {
		return fetch(OC.generateUrl('/apps/' + APP_ID + endpoint), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'requesttoken': OC.requestToken,
			},
			body: new URLSearchParams(body),
		}).catch(function () { /* non-fatal */ });
	}

	/* ─────────────────────────────────────────
	   Custom Modal using native <dialog>
	   Uses .showModal() which renders in the
	   browser's top-layer, natively overriding
	   any JavaScript focus-traps (e.g. the
	   Nextcloud viewer's focus trap).
	   ───────────────────────────────────────── */
	let _activeModal = null;

	function _createModal(opts) {
		/*
		 * opts = {
		 *   title   : string,
		 *   body    : HTMLElement | string (HTML),
		 *   buttons : [{ label, primary, onClick }]
		 * }
		 * Returns a Promise that resolves with the button index that was clicked.
		 */
		return new Promise(function (resolve) {
			if (_activeModal) _closeModal(_activeModal);

			/* inject styles once */
			if (!document.getElementById('dc-modal-styles')) {
				const style = document.createElement('style');
				style.id = 'dc-modal-styles';
				style.textContent = [
					'dialog.dc-dialog::backdrop{background:rgba(0,0,0,0.55);animation:dc-fade-in 0.18s ease}',
					'dialog.dc-dialog{padding:0;border:none;border-radius:12px;background:#ffffff;',
					'position:fixed;inset:0;margin:auto;height:fit-content;',
					'box-shadow:0 8px 40px rgba(0,0,0,0.28);max-width:520px;width:90%;',
					'font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;',
					'animation:dc-slide-in 0.2s ease;overflow:hidden}',
					'@keyframes dc-fade-in{from{opacity:0}to{opacity:1}}',
					'@keyframes dc-slide-in{from{transform:translateY(-16px);opacity:0}to{transform:none;opacity:1}}',
				].join('');
				document.head.appendChild(style);
			}

			/* <dialog> element */
			const dialog = document.createElement('dialog');
			dialog.classList.add('dc-dialog');

			/* title bar */
			const titleBar = document.createElement('div');
			titleBar.style.cssText = [
				'padding:18px 22px 14px', 'border-bottom:1px solid #eee',
				'display:flex', 'align-items:center', 'justify-content:space-between',
			].join(';');
			const titleEl = document.createElement('h2');
			titleEl.id = 'dc-modal-title';
			titleEl.textContent = opts.title;
			titleEl.style.cssText = 'margin:0;font-size:15px;font-weight:600;color:#222;';
			const closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.textContent = '✕';
			closeBtn.setAttribute('aria-label', 'Close');
			closeBtn.style.cssText = [
				'background:none', 'border:none', 'cursor:pointer',
				'font-size:16px', 'color:#888', 'line-height:1', 'padding:2px 6px',
				'border-radius:4px',
			].join(';');
			closeBtn.addEventListener('mouseover', function () { this.style.color = '#333'; });
			closeBtn.addEventListener('mouseout', function () { this.style.color = '#888'; });
			titleBar.appendChild(titleEl);
			titleBar.appendChild(closeBtn);

			/* body */
			const bodyEl = document.createElement('div');
			bodyEl.style.cssText = 'padding:20px 22px;';
			if (typeof opts.body === 'string') {
				bodyEl.innerHTML = opts.body;
			} else {
				bodyEl.appendChild(opts.body);
			}

			/* button row */
			const btnRow = document.createElement('div');
			btnRow.style.cssText = [
				'padding:14px 22px 18px', 'border-top:1px solid #eee',
				'display:flex', 'justify-content:flex-end', 'gap:10px',
			].join(';');

			opts.buttons.forEach(function (btn, idx) {
				const b = document.createElement('button');
				b.type = 'button';
				b.textContent = btn.label;
				b.style.cssText = [
					'padding:8px 18px', 'border-radius:6px', 'cursor:pointer',
					'font-size:13px', 'font-weight:500', 'border:1px solid',
					btn.primary
						? 'background:#0082c9;color:#fff;border-color:#0082c9;'
						: 'background:#fff;color:#333;border-color:#ccc;',
					'transition:opacity 0.15s',
				].join(';');
				b.addEventListener('mouseover', function () { this.style.opacity = '0.85'; });
				b.addEventListener('mouseout', function () { this.style.opacity = '1'; });
				b.addEventListener('click', function () {
					_closeModal(modal);
					if (btn.onClick) btn.onClick();
					resolve(idx);
				});
				btnRow.appendChild(b);
			});

			dialog.appendChild(titleBar);
			dialog.appendChild(bodyEl);
			dialog.appendChild(btnRow);

			const modal = { dialog, resolve };
			_activeModal = modal;

			/* close via ✕ button */
			closeBtn.addEventListener('click', function () {
				_closeModal(modal);
				resolve(-1);
			});

			/* close on backdrop click (click on <dialog> itself, outside inner content) */
			dialog.addEventListener('click', function (e) {
				if (e.target === dialog) { _closeModal(modal); resolve(-1); }
			});

			/* close on Escape — native <dialog> fires a 'cancel' event */
			dialog.addEventListener('cancel', function (e) {
				e.preventDefault();
				_closeModal(modal);
				resolve(-1);
			});

			/* mount and open as modal (top-layer) */
			document.body.appendChild(dialog);
			dialog.showModal();

			/* focus first primary button */
			const firstPrimary = btnRow.querySelector('button');
			if (firstPrimary) firstPrimary.focus();
		});
	}

	function _closeModal(modal) {
		if (!modal) return;
		try { modal.dialog.close(); } catch (_) {}
		if (modal.dialog && modal.dialog.parentNode) {
			modal.dialog.parentNode.removeChild(modal.dialog);
		}
		if (_activeModal === modal) _activeModal = null;
	}

	/* ─────────────────────────────────────────
	   Specific modals
	   ───────────────────────────────────────── */
	function showDisclaimerModal(fileName) {
		return new Promise(function (resolve, reject) {
			const safe = _esc(fileName);
			const body = `
				<div style="background:#fff8e1;border-left:4px solid #f9a825;padding:14px 16px;border-radius:6px;margin-bottom:16px;">
					<h4 style="margin:0 0 8px;color:#5d4037;font-size:14px;">⚠️ File Access Disclaimer</h4>
					<p style="margin:0 0 10px;font-size:13px;color:#5d4037;line-height:1.55;">
						You are about to access <strong>${safe}</strong>.
						This file may contain confidential information. By proceeding you agree to:
					</p>
					<ul style="font-size:13px;color:#5d4037;margin:0;padding-left:18px;line-height:1.8;">
						<li>Not share this file with unauthorised persons.</li>
						<li>Use the information only for authorised business purposes.</li>
						<li>Report any security incidents immediately to IT.</li>
					</ul>
				</div>
				<p style="font-weight:600;font-size:13px;margin:0;color:#333;">
					Do you acknowledge and agree to these terms?
				</p>`;

			_createModal({
				title: '🔒 Confidentiality Agreement',
				body: body,
				buttons: [
					{ label: 'Decline', primary: false, onClick: function () { reject(new Error('declined')); } },
					{ label: 'I Agree – Open File', primary: true, onClick: function () { resolve(); } },
				],
			}).then(function (idx) { if (idx <= 0) reject(new Error('dismissed')); });
		});
	}

	function showPurposeModal(fileName) {
		return new Promise(function (resolve, reject) {
			const safe = _esc(fileName);
			const uid = 'dc-purpose-' + Date.now();
			const body = `
				<p style="margin:0 0 14px;font-size:13px;color:#444;line-height:1.5;">
					Please state the <strong>purpose</strong> for downloading
					<code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;font-size:12px;">${safe}</code>.
				</p>
				<label for="${uid}" style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;color:#333;">
					Purpose of Download <span style="color:#e53935;">*</span>
				</label>
				<textarea id="${uid}" rows="4"
					placeholder="e.g. Audit review, Customer support, Compliance check…"
					style="width:100%;box-sizing:border-box;border:1px solid #ccc;border-radius:6px;
					       padding:9px 11px;font-size:13px;resize:vertical;font-family:inherit;
					       background-color:#f9f9f9;color:#000;transition:border-color 0.15s;"
				></textarea>
				<p id="${uid}-err" style="color:#e53935;font-size:12px;margin:4px 0 0;display:none;">
					This field is required.
				</p>`;

			_createModal({
				title: '📋 Download Purpose Required',
				body: body,
				buttons: [
					{
						label: 'Cancel', primary: false,
						onClick: function () { reject(new Error('cancelled')); },
					},
					{
						label: 'Confirm Download', primary: true,
						onClick: function () {
							/* no-op — we replace this button's handler below */
						},
					},
				],
			});

			/* The <dialog> is already in the DOM after _createModal.
			   Replace the Confirm button handler with validation logic. */
			setTimeout(function () {
				const dlg = _activeModal && _activeModal.dialog;
				if (!dlg) { reject(new Error('no modal')); return; }

				const buttons = dlg.querySelectorAll('button');
				const confirmBtn = buttons[buttons.length - 1]; /* last = primary */
				if (!confirmBtn) { reject(new Error('no button')); return; }

				/* Clone to strip the generic close handler */
				const newBtn = confirmBtn.cloneNode(true);
				confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

				newBtn.addEventListener('click', function () {
					const ta = dlg.querySelector('#' + uid);
					const err = dlg.querySelector('#' + uid + '-err');
					const val = ta ? ta.value.trim() : '';
					if (!val) {
						if (ta) ta.style.borderColor = '#e53935';
						if (err) err.style.display = 'block';
						return;
					}
					_closeModal(_activeModal);
					resolve(val);
				});

				/* Focus textarea */
				const ta = dlg.querySelector('#' + uid);
				if (ta) ta.focus();
			}, 20);
		});
	}

	function _esc(str) {
		return String(str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;')
			.replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	/* ─────────────────────────────────────────
	   Open a file programmatically.
	   Tries OCA.Viewer first (handles images, PDFs,
	   videos, markdown, text, etc.), then falls back
	   to URL-based navigation using the file ID.
	   ───────────────────────────────────────── */
	function _openFile(row, dir, fileName) {
		var filePath = (dir || '') + '/' + fileName;
		var fileId = row.getAttribute('data-cy-files-list-row-fileid');

		/* Strategy 1: NC Viewer API (previewable files) */
		if (window.OCA && OCA.Viewer && typeof OCA.Viewer.open === 'function') {
			try {
				OCA.Viewer.open({ path: filePath });
				return;
			} catch (_) { /* fall through */ }
		}

		/* Strategy 2: Navigate via NC internal URL (non-previewable) */
		if (fileId) {
			var params = new URLSearchParams(window.location.search);
			params.set('dir', dir || '/');
			params.set('openfile', fileId);
			var base = window.location.pathname;
			window.history.pushState(null, '', base + '?' + params.toString());
			window.dispatchEvent(new PopStateEvent('popstate'));
			return;
		}

		/* Strategy 3: last resort — direct download link */
		var dlUrl = OC.generateUrl('/remote.php/dav/files/' +
			OC.getCurrentUser().uid + filePath);
		window.open(dlUrl, '_blank');
	}

	/* ─────────────────────────────────────────
	   VIEW interception
	   NC 33: the "open file" button is
	     button.files-list__row-name-link[aria-label="View"]
	   inside tr[data-cy-files-list-row].
	   We listen in capture phase so we run first.
	   ───────────────────────────────────────── */
	function _hookViewClicks() {
		document.addEventListener('click', function (event) {
			const btn = event.target.closest('button.files-list__row-name-link');
			if (!btn) return;

			const row = event.target.closest('tr[data-cy-files-list-row]');
			if (!row) return;

			/* ── Skip folders ─────────────────────────────────
			   NC 33 renders a .folder-icon inside the row icon
			   for directories. This is the reliable signal since
			   data-cy-files-list-row-type/mime are not set.
			   ──────────────────────────────────────────────── */
			const icon = row.querySelector('.files-list__row-icon');
			if (icon && icon.querySelector('.folder-icon')) {
				return;  /* folder → let NC handle navigation normally */
			}

			const fileName = row.getAttribute('data-cy-files-list-row-name') || 'this file';
			const key = fileName; /* per-tab cache */

			if (_viewed.has(key)) return; /* already ack'd this session */

			event.preventDefault();
			event.stopImmediatePropagation();

			showDisclaimerModal(fileName)
				.then(function () {
					_viewed.add(key);
					const dir = _getDir();
					postLog('/api/ack-disclaimer', {
						fileName: fileName,
						filePath: dir + '/' + fileName,
					});
					/* Open the file programmatically instead of
					   re-dispatching a click (which bubbles to the
					   parent <a> and causes unwanted navigation). */
					_openFile(row, dir, fileName);
				})
				.catch(function () { /* declined – stay on list */ });
		}, true /* capture */);
	}

	/* ─────────────────────────────────────────
	   DOWNLOAD interception
	   NC 33 triggers downloads by creating a
	   temporary <a href="…" download="…">,
	   appending it, calling .click(), then removing
	   it — all synchronously in one tick.
	   MutationObserver fires too late to intercept.

	   Fix: monkey-patch HTMLAnchorElement.prototype.click
	   so ANY programmatic .click() on an anchor with
	   a download attribute is intercepted before the
	   browser navigates.
	   ───────────────────────────────────────── */
	function _hookDownloads() {
		const _origClick = HTMLAnchorElement.prototype.click;

		HTMLAnchorElement.prototype.click = function () {
			/* Only intercept anchors that have a download attribute */
			if (!this.hasAttribute('download')) {
				return _origClick.call(this);
			}

			/* Skip if we're re-firing after the user confirmed */
			if (this.__dc_approved) {
				delete this.__dc_approved;
				return _origClick.call(this);
			}

			const anchor = this;
			const href = anchor.getAttribute('href') || '';
			const fileName = anchor.getAttribute('download') || _nameFromUrl(href);

			/* Prevent immediate removal by NC —
			   keep a reference so we can re-click later */
			const parent = anchor.parentNode;

			showPurposeModal(fileName)
				.then(function (purpose) {
					const dir = _getDir();
					return postLog('/api/log-purpose', {
						fileName: fileName,
						filePath: dir + '/' + fileName,
						fileType: 'file',
						purpose: purpose,
					});
				})
				.then(function () {
					/* Re-create the anchor (original may have been removed) */
					const a = document.createElement('a');
					a.href = href;
					a.download = fileName;
					a.style.display = 'none';
					a.__dc_approved = true;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
				})
				.catch(function () { /* cancelled – don't download */ });

			/* Do NOT call _origClick — block the original download */
		};
	}

	function _nameFromUrl(url) {
		try {
			const parts = new URL(url, window.location.origin).pathname.split('/');
			return decodeURIComponent(parts[parts.length - 1]) || 'file';
		} catch (_) {
			return 'file';
		}
	}

	function _getDir() {
		try {
			const params = new URLSearchParams(window.location.search);
			if (params.has('dir')) return params.get('dir');
			const hash = window.location.hash;
			const m = hash.match(/[?&]dir=([^&]+)/);
			if (m) return decodeURIComponent(m[1]);
		} catch (_) {}
		return '';
	}

	/* ─────────────────────────────────────────
	   Boot – wait for OC globals to be ready
	   ───────────────────────────────────────── */
	function boot() {
		if (typeof OC === 'undefined' || !OC.generateUrl) {
			setTimeout(boot, 150);
			return;
		}
		_hookViewClicks();
		_hookDownloads();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
