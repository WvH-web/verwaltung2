/**
 * WvH Abrechnungssystem – Custom JS
 */

'use strict';

// ----------------------------------------------------------------
// CSRF Token für AJAX-Requests
// ----------------------------------------------------------------
const WVH = {
  csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

  /** POST-Request mit CSRF */
  async post(url, data = {}) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(data),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  },

  /** Flash-Nachricht dynamisch anzeigen */
  flash(message, type = 'info') {
    const icons = {
      success: 'bi-check-circle-fill text-success',
      error:   'bi-x-circle-fill text-danger',
      warning: 'bi-exclamation-triangle-fill text-warning',
      info:    'bi-info-circle-fill text-info',
    };
    const bgs = {
      success: 'bg-success-subtle border-success',
      error:   'bg-danger-subtle border-danger',
      warning: 'bg-warning-subtle border-warning',
      info:    'bg-info-subtle border-info',
    };
    const div = document.createElement('div');
    div.className = `alert alert-dismissible border ${bgs[type] || bgs.info} d-flex align-items-center gap-2 mb-3`;
    div.setAttribute('role', 'alert');
    div.innerHTML = `
      <i class="bi ${icons[type] || icons.info} fs-5"></i>
      <span>${message}</span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    const container = document.querySelector('.wvh-main .container-fluid');
    if (container) container.insertAdjacentElement('afterbegin', div);
    setTimeout(() => div.remove(), 6000);
  },

  /** Bestätigungsdialog */
  confirm(message) {
    return new Promise(resolve => {
      const modal = document.getElementById('wvhConfirmModal');
      if (!modal) { resolve(window.confirm(message)); return; }
      modal.querySelector('#confirmMessage').textContent = message;
      const bsModal = new bootstrap.Modal(modal);
      const btn = modal.querySelector('#confirmOk');
      const handler = () => { resolve(true); bsModal.hide(); };
      btn.addEventListener('click', handler, { once: true });
      modal.addEventListener('hidden.bs.modal', () => resolve(false), { once: true });
      bsModal.show();
    });
  }
};

// ----------------------------------------------------------------
// Auto-Dismiss für Alerts nach 5 Sekunden
// ----------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bsAlert?.close();
    }, 6000);
  });

  // Tooltips initialisieren
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

  // Session-Timeout Warnung (5 Minuten vor Ablauf)
  const SESSION_LIFETIME = 7200 * 1000; // 2h in ms
  const WARN_BEFORE = 5 * 60 * 1000;   // 5 Minuten
  setTimeout(() => {
    WVH.flash('⚠️ Deine Sitzung läuft in 5 Minuten ab. Bitte speichere deine Arbeit.', 'warning');
  }, SESSION_LIFETIME - WARN_BEFORE);
});

// ----------------------------------------------------------------
// Formular-Schutz: Ungespeicherte Änderungen
// ----------------------------------------------------------------
let formDirty = false;
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-dirty-check]').forEach(form => {
    form.addEventListener('change', () => { formDirty = true; });
    form.addEventListener('submit', () => { formDirty = false; });
  });
  window.addEventListener('beforeunload', e => {
    if (formDirty) { e.preventDefault(); e.returnValue = ''; }
  });
});

// ----------------------------------------------------------------
// Tabellen: Live-Suche
// ----------------------------------------------------------------
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const rows  = document.querySelectorAll(`#${tableId} tbody tr`);
  if (!input || !rows.length) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    rows.forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
