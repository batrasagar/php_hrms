  </main><!-- /app-content -->
</div><!-- /app-main -->

<!-- ── Global Toast Container ──────────────────────────────────────────── -->
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:11000"></div>
<style>
  #toast-container .toast { min-width: 280px; max-width: 400px; font-size: 13.5px; border-radius: 10px !important; box-shadow: 0 8px 24px rgba(0,0,0,.14) !important; }
  #toast-container .toast-body { padding: 10px 14px; display: flex; align-items: flex-start; gap: 10px; }
  #toast-container .t-icon { font-size: 18px; line-height: 1.3; flex-shrink: 0; }
  #toast-container .t-msg  { flex: 1; }
  #toast-container .toast.t-success { border-left: 4px solid #1a7a3a !important; }
  #toast-container .toast.t-danger  { border-left: 4px solid #b82a20 !important; }
  #toast-container .toast.t-warning { border-left: 4px solid #7a5500 !important; }
  #toast-container .toast.t-info    { border-left: 4px solid #004a99 !important; }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
/* ── Global Toast ───────────────────────────────────────────────────── */
window.showToast = function(msg, type, duration) {
  type     = type     || 'success';
  duration = duration || 4000;
  var icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
  var el = document.createElement('div');
  el.className = 'toast t-' + type;
  el.setAttribute('role', 'alert');
  el.setAttribute('aria-live', 'assertive');
  el.innerHTML =
    '<div class="toast-body">' +
      '<span class="t-icon">' + (icons[type] || 'ℹ️') + '</span>' +
      '<span class="t-msg">' + msg + '</span>' +
      '<button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="toast" aria-label="Close"></button>' +
    '</div>';
  document.getElementById('toast-container').appendChild(el);
  var t = new bootstrap.Toast(el, { delay: duration });
  t.show();
  el.addEventListener('hidden.bs.toast', function() { el.remove(); });
};

/* Auto-convert .alert-success/danger/warning divs to toasts
   Skip elements that have data-no-toast attribute (inline/structural alerts) */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.alert-success, .alert-danger, .alert-warning').forEach(function(el) {
    if ('noToast' in el.dataset) return;
    var type = el.classList.contains('alert-success') ? 'success'
             : el.classList.contains('alert-danger')  ? 'danger'
             : 'warning';
    // Get text content, strip close-button text
    var clone = el.cloneNode(true);
    clone.querySelectorAll('.btn-close, button').forEach(function(b) { b.remove(); });
    var msg = clone.innerHTML.trim();
    el.style.display = 'none';
    showToast(msg, type);
  });
});

/* ── CSRF: inject token header into every $.ajax call ──────────────── */
(function () {
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  if (token) $.ajaxSetup({ headers: { 'X-CSRF-Token': token } });
})();

/* ── Global AJAX form handler (opt-in via data-ajax attribute) ──────── */
$(document).on('submit', 'form[data-ajax]', function (e) {
  e.preventDefault();
  var $form    = $(this);
  var $btn     = $form.find('[type=submit]').first();
  var origHtml = $btn.html();

  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…');
  $form.find('.ajax-err').remove();

  $.ajax({
    url         : $form.attr('action') || window.location.href,
    type        : 'POST',
    data        : new FormData($form[0]),
    processData : false,
    contentType : false,
    success: function (resp) {
      if (resp.success) {
        if (resp.message) showToast(resp.message, 'success');
        if (resp.redirect) {
          window.location.href = resp.redirect;
        } else {
          $btn.prop('disabled', false).html(origHtml);
        }
      } else {
        (resp.errors || ['An error occurred.']).forEach(function (msg) {
          $form.before('<div class="alert alert-danger py-2 ajax-err" data-no-toast>' +
            $('<div>').text(msg).html() + '</div>');
        });
        window.scrollTo(0, 0);
        $btn.prop('disabled', false).html(origHtml);
      }
    },
    error: function (xhr) {
      $form.before('<div class="alert alert-danger py-2 ajax-err" data-no-toast>Server error (' +
        xhr.status + '). Please try again.</div>');
      window.scrollTo(0, 0);
      $btn.prop('disabled', false).html(origHtml);
    }
  });
});

/* ── AJAX Filter handler (opt-in via data-filter attribute) ─────────── */
$(document).on('submit', 'form[data-filter]', function(e) {
  e.preventDefault();
  var $form    = $(this);
  var $btn     = $form.find('[type=submit]').first();
  var origHtml = $btn.length ? $btn.html() : '';
  var url      = window.location.pathname + '?' + $form.serialize();

  history.pushState(null, '', url);

  if ($btn.length) {
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Filtering…');
  }

  var $results = $('#filter-results');
  $results.css('opacity', 0.5);

  $.get(url, function(html) {
    var $new = $('<div>').html(html).find('#filter-results');
    if ($new.length) {
      $results.find('table').each(function() {
        if ($.fn.DataTable.isDataTable(this)) $(this).DataTable().destroy();
      });
      $results.html($new.html()).css('opacity', 1).trigger('filter:done');
      if (window.initTomSelects)  window.initTomSelects($results[0]);
      if (window.initDatepickers) window.initDatepickers($results[0]);
    } else {
      $results.css('opacity', 1).trigger('filter:done');
    }
    if ($btn.length) $btn.prop('disabled', false).html(origHtml);
  }).fail(function() {
    $results.css('opacity', 1).trigger('filter:done');
    if ($btn.length) $btn.prop('disabled', false).html(origHtml);
    showToast('Filter failed. Please try again.', 'danger');
  });
});

/* ── Tom-Select auto-init ───────────────────────────────────────────── */
(function () {
  function initTomSelects(root) {
    (root || document).querySelectorAll('select.form-select, select.form-select-sm').forEach(function (el) {
      if (el.tomselect) return;                          // already initialised
      if (el.classList.contains('bg-transparent')) return; // inline table cells
      if ('noTs' in el.dataset) return;                 // opt-out via data-no-ts

      new TomSelect(el, {
        create: false,
        allowEmptyOption: true,
        selectOnTab: true,
        maxOptions: null,
        plugins: el.multiple ? ['remove_button'] : [],
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () { initTomSelects(); });

  /* expose globally so pages can call initTomSelects(container) after
     dynamically adding selects (e.g. after AJAX row inserts) */
  window.initTomSelects = initTomSelects;
})();

/* ── Flatpickr auto-init: dd-MMM-yyyy display everywhere ─────────────── */
/* Turns every <input type="date"> into a datepicker that DISPLAYS
   dd-MMM-yyyy (e.g. 13-Jul-2026) while the real submitted value stays
   Y-m-d, so PHP/MySQL and existing JS reading .val() are unaffected.
   Opt out on a field with data-no-fp. */
(function () {
  function initDatepickers(root) {
    if (!window.flatpickr) return;
    (root || document).querySelectorAll('input[type="date"]').forEach(function (el) {
      if (el._flatpickr) return;          // already initialised
      if ('noFp' in el.dataset) return;   // opt-out via data-no-fp
      var opts = {
        altInput  : true,
        altFormat : 'd-M-Y',              // 13-Jul-2026
        dateFormat: 'Y-m-d',              // value kept for the server
        allowInput: true,
      };
      if (el.getAttribute('min')) opts.minDate = el.getAttribute('min');
      if (el.getAttribute('max')) opts.maxDate = el.getAttribute('max');
      flatpickr(el, opts);
    });
  }
  document.addEventListener('DOMContentLoaded', function () { initDatepickers(); });
  window.initDatepickers = initDatepickers;
})();

/* ── Excel export (flat table → .xls via HTML, no library needed) ───── */
(function () {
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  /* rows: array of arrays. The first `headerRows` (default 1) render as <th>.
     Produces a .xls the browser hands to Excel — mirrors the swipe report's
     long-standing export, generalised for reuse. */
  window.excelFromRows = function (rows, filename, title, headerRows) {
    headerRows = headerRows == null ? 1 : headerRows;
    if (!rows || rows.length <= headerRows) {
      if (window.showToast) showToast('Nothing to export — load the report first.', 'warning');
      return;
    }
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 1);
    var s = '<table border="1">';
    if (title) s += '<tr><td colspan="' + cols + '"><b>' + esc(title) + '</b></td></tr>';
    rows.forEach(function (r, i) {
      var tag = i < headerRows ? 'th' : 'td';
      s += '<tr>';
      for (var c = 0; c < cols; c++) {
        // \n inside a value → a line break WITHIN the same Excel cell.
        var cell = esc(r[c] == null ? '' : r[c]).replace(/\n/g, '<br style="mso-data-placement:same-cell">');
        s += '<' + tag + '>' + cell + '</' + tag + '>';
      }
      s += '</tr>';
    });
    s += '</table>';
    var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>' + s + '</body></html>';
    var blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' });
    var name = (filename || 'export').replace(/[^\w.-]+/g, '_');
    if (!/\.xls$/i.test(name)) name += '.xls';
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  };

  /* Flatten a rendered table to a .xls. DataTables-aware: includes EVERY row
     across all pages, honouring the active search/sort. skipCols drops columns
     by their header index (e.g. a serial or action column). */
  window.excelFromDataTable = function (selector, filename, title, skipCols) {
    skipCols = skipCols || [];
    var $ = window.jQuery; if (!$) return;
    var $t = $(selector); if (!$t.length) return;
    var keep  = function (i) { return skipCols.indexOf(i) === -1; };
    var strip = function (h) { return $('<div>').html(h).text().replace(/\s+/g, ' ').trim(); };
    var header = [];
    $t.find('thead tr').first().find('th').each(function (i) { if (keep(i)) header.push($(this).text().trim()); });
    var rows = [header];
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
      $t.DataTable().rows({ search: 'applied', order: 'applied' }).every(function () {
        var d = this.data(), row = [];
        for (var i = 0; i < d.length; i++) if (keep(i)) row.push(strip(d[i]));
        rows.push(row);
      });
    } else {
      $t.find('tbody tr').each(function () {
        var row = [];
        $(this).find('td').each(function (i) { if (keep(i)) row.push(strip($(this).html())); });
        rows.push(row);
      });
    }
    window.excelFromRows(rows, filename, title, 1);
  };

  /* Turn an ajax/attendance_data.php JSON payload into a flat, one-row-per-
     employee-per-day table. Shared by the attendance, monthly and swipe reports
     so their "Excel" buttons all yield the same tidy, pivot-friendly sheet. */
  window.attFlatRows = function (data) {
    var STATUS = { P: 'Present', HP: 'Half Present', A: 'Absent', L: 'Leave', HL: 'Half Leave',
                   CO: 'Comp Off', WO: 'Week Off', HOL: 'Holiday', SUN: 'Week Off (Sun)' };
    var rows = [['Emp Code', 'Name', 'Father', 'Designation', 'Department', 'Contractor', 'Shift',
                 'Date', 'Day', 'Status', 'In', 'Out', 'Hours', 'OT']];
    (data.employees || []).forEach(function (emp) {
      (data.dates || []).forEach(function (d) {
        var c = (emp.days && emp.days[d.date]) || { type: '' };
        if (!c.type || c.type === 'FUT') return;            // skip blank & future days
        var pin  = c.in  || (c.punches && c.punches.length ? c.punches[0] : '') || '';
        var pout = c.out || (c.punches && c.punches.length > 1 ? c.punches[c.punches.length - 1] : '') || '';
        rows.push([
          emp.code || '', emp.name || '', emp.fatherName || '', emp.designation || '',
          emp.department || '', emp.contractor || '', emp.shiftName || '',
          d.date, d.dayName || '', STATUS[c.type] || c.type,
          pin, pout, c.tot || '', c.ot || ''
        ]);
      });
    });
    return rows;
  };

  /* Turn the same payload into a PIVOT / crosstab: one row per employee, one
     column per date (matching the on-screen attendance grid), plus summary
     columns. Two header rows — date number, then weekday. Feed to excelFromRows
     with headerRows = 2. */
  window.attPivotRows = function (data) {
    var dates = data.dates || [], emps = data.employees || [];
    function otHm(m) { m = m || 0; if (m <= 0) return ''; var h = Math.floor(m / 60), mn = m % 60; return (h ? h + 'h' : '') + (mn ? mn + 'm' : (h ? '' : '0m')); }
    function cellText(c) {
      if (!c || !c.type) return '';
      switch (c.type) {
        case 'P': case 'HP':
          // In, Out and total each on their own line inside the one cell.
          var inT, outT;
          if (c.punches && c.punches.length) { inT = c.punches[0]; outT = c.punches.length > 1 ? c.punches[c.punches.length - 1] : ''; }
          else { inT = c.in || ''; outT = c.out || ''; }
          var parts = [];
          if (inT)  parts.push(inT);
          if (outT) parts.push(outT);
          if (c.tot) parts.push('(' + c.tot + (c.ot ? ' +' + c.ot : '') + ')');
          return parts.length ? parts.join('\n') : c.type;
        case 'A':   return 'A';
        case 'L':   return 'L';
        case 'HL':  return 'HL' + (c.lvSub ? ' ' + c.lvSub : '');
        case 'CO':  return 'CO';
        case 'WO':  return c.woCut ? 'WO*' : 'WO';
        case 'HOL': return 'H';
        case 'SUN': return c.woCut ? 'S*' : 'S';
        default:    return '';
      }
    }
    var SUM = ['P', 'HP', 'A', 'L', 'CO', 'HL', 'H+S', 'Days', 'OT'];
    var head1 = ['Emp Code', 'Name', 'Department'];
    var head2 = ['', '', ''];
    dates.forEach(function (d) { head1.push(parseInt(d.dayNum, 10)); head2.push(d.dayName || ''); });
    SUM.forEach(function (h) { head1.push(h); head2.push(''); });
    var rows = [head1, head2];
    emps.forEach(function (emp) {
      var r = [emp.code || '', emp.name || '', emp.department || ''];
      var cP = 0, cHP = 0, cA = 0, cL = 0, cCO = 0, cHL = 0, cHS = 0, ot = 0;
      dates.forEach(function (d) {
        var c = (emp.days && emp.days[d.date]) || { type: '' };
        switch (c.type) {
          case 'P':  cP++;  break;
          case 'HP': cHP++; break;
          case 'A':  cA++;  break;
          case 'L':  cL++;  break;
          case 'CO': cCO++; break;
          case 'HL': cHL++; break;
          case 'SUN': case 'HOL': case 'WO': if (!c.woCut) cHS++; break;
        }
        ot += (c.otMins || 0);
        r.push(cellText(c));
      });
      var days = cP + cHP * 0.5 + cL + cHL * 0.5 + cCO + cHS;
      r.push(cP || '', cHP || '', cA || '', cL || '', cCO || '', cHL || '', cHS || '',
             (Number.isInteger(days) ? days : days.toFixed(1)), otHm(ot));
      rows.push(r);
    });
    return rows;
  };
})();
</script>
<?php if (!empty($extraJs)): ?>
<?= $extraJs ?>
<?php endif; ?>
<script>
/* ── Sidebar logic ──────────────────────────────────────────────────── */
(function () {
  const body     = document.body;
  const backdrop = document.getElementById('sbBackdrop');
  const desktopBtn = document.getElementById('desktopToggle');
  const mobileBtn  = document.getElementById('mobileToggle');

  // Restore desktop collapsed state
  if (window.innerWidth >= 992 && localStorage.getItem('sb-state') === 'collapsed') {
    body.classList.add('sb-collapsed');
  }

  // Desktop toggle
  desktopBtn?.addEventListener('click', () => {
    body.classList.toggle('sb-collapsed');
    localStorage.setItem('sb-state', body.classList.contains('sb-collapsed') ? 'collapsed' : 'expanded');
    // Close open sub-menus when collapsing
    if (body.classList.contains('sb-collapsed')) {
      document.querySelectorAll('#sbNav .collapse.show').forEach(el => {
        bootstrap.Collapse.getOrCreateInstance(el).hide();
      });
    }
  });

  // Mobile toggle
  function openMobile()  { body.classList.add('sb-open'); document.documentElement.style.overflow = 'hidden'; }
  function closeMobile() { body.classList.remove('sb-open'); document.documentElement.style.overflow = ''; }

  mobileBtn?.addEventListener('click', () => {
    body.classList.contains('sb-open') ? closeMobile() : openMobile();
  });
  backdrop?.addEventListener('click', closeMobile);

  // Close mobile sidebar on route-link click
  document.querySelectorAll('.sb-item:not([data-bs-toggle]), .sb-sub-item').forEach(el => {
    el.addEventListener('click', () => { if (window.innerWidth < 992) closeMobile(); });
  });

  // Handle resize
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) closeMobile();
  });
})();
</script>

<!-- ── Command palette / menu search (Ctrl+M) ──────────────────────────── -->
<style>
.cmdk-trigger{display:inline-flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:6px 10px;color:var(--text-2);cursor:pointer;font-size:13px;font-family:inherit;transition:background .15s;}
.cmdk-trigger:hover{background:rgba(0,0,0,.06);color:var(--text);}
.cmdk-trigger kbd{background:var(--surface);border:1px solid var(--border);border-radius:5px;padding:1px 6px;font-size:11px;color:var(--text-2);font-family:inherit;}
@media (max-width:575.98px){.cmdk-trigger-text{display:none;}.cmdk-trigger kbd{display:none;}}
.cmdk-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);z-index:11050;display:none;align-items:flex-start;justify-content:center;padding-top:70px;animation:cmdkFade .15s ease;}
.cmdk-overlay.open{display:flex;}
@keyframes cmdkFade{from{opacity:0}to{opacity:1}}
.cmdk-modal{background:var(--surface);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.06);width:600px;max-width:calc(100vw - 32px);max-height:calc(100vh - 120px);display:flex;flex-direction:column;overflow:hidden;}
.cmdk-inrow{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.cmdk-inrow>i{color:var(--text-3);font-size:16px;}
.cmdk-input{flex:1;border:none!important;outline:none!important;box-shadow:none!important;font-size:15px;background:transparent!important;color:var(--text)!important;padding:0!important;font-family:inherit;}
.cmdk-input::placeholder{color:var(--text-3);}
.cmdk-esc{font-size:11px;background:var(--bg);color:var(--text-2);border:1px solid var(--border);border-radius:5px;padding:2px 7px;cursor:pointer;flex-shrink:0;font-family:inherit;}
.cmdk-body{overflow-y:auto;flex:1;padding:6px 0 4px;}
.cmdk-sec{display:flex;align-items:center;gap:7px;padding:10px 18px 4px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);}
.cmdk-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.cmdk-item{display:flex;align-items:center;justify-content:space-between;width:100%;padding:8px 18px;border:none;background:transparent;text-align:left;cursor:pointer;gap:10px;font-family:inherit;transition:background .1s;}
.cmdk-item.hi{background:var(--blue-lt);}
.cmdk-label{font-size:14px;color:var(--text);font-weight:500;flex:1;}
.cmdk-badge{font-size:11px;border-radius:20px;padding:2px 9px;font-weight:600;flex-shrink:0;}
.cmdk-arrow{color:var(--text-3);opacity:0;}
.cmdk-item.hi .cmdk-arrow{opacity:1;}
.cmdk-empty{padding:32px 18px;text-align:center;font-size:14px;color:var(--text-3);}
.cmdk-footer{display:flex;gap:16px;padding:8px 18px;border-top:1px solid var(--border);font-size:11px;color:var(--text-3);flex-shrink:0;}
.cmdk-footer kbd{background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:1px 5px;font-size:11px;font-family:inherit;color:var(--text-2);margin-right:2px;}
</style>
<script>
(function () {
  var idx = null, overlay = null, input = null, body = null, results = [], hi = 0, query = '';

  function hue(s){var h=0;for(var i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))>>>0;return h%360;}
  function color(s){return 'hsl(' + hue(s) + ',60%,52%)';}
  function esc(s){return (s+'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

  // Build the menu index by scraping the rendered sidebar (respects role visibility).
  function buildIndex(){
    var out = [], nav = document.getElementById('sbNav');
    if (!nav) return out;
    var group = 'Quick Access', seen = {};
    nav.querySelectorAll('.sb-section-label, a.sb-item, a.sb-sub-item').forEach(function(el){
      if (el.classList.contains('sb-section-label')) { group = el.textContent.trim() || group; return; }
      if (el.hasAttribute('data-bs-toggle')) return;            // collapse toggles aren't destinations
      var href = el.getAttribute('href');
      if (!href || href === '#') return;
      var lbl = el.querySelector('.sb-item-label');
      var label = (lbl ? lbl.textContent : el.textContent).replace(/\s+/g,' ').trim();
      if (!label) return;
      var key = href + '|' + label;
      if (seen[key]) return; seen[key] = 1;
      out.push({ label: label, href: href, group: group, target: el.getAttribute('target') || '' });
    });
    return out;
  }

  function ensure(){
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.className = 'cmdk-overlay';
    overlay.innerHTML =
      '<div class="cmdk-modal" role="dialog" aria-label="Menu search">' +
        '<div class="cmdk-inrow"><i class="bi bi-search"></i>' +
        '<input class="cmdk-input" type="text" placeholder="Search menu…" autocomplete="off" spellcheck="false">' +
        '<kbd class="cmdk-esc">esc</kbd></div>' +
        '<div class="cmdk-body"></div>' +
        '<div class="cmdk-footer"><span><kbd>↑</kbd><kbd>↓</kbd>navigate</span><span><kbd>↵</kbd>open</span><span><kbd>esc</kbd>close</span></div>' +
      '</div>';
    document.body.appendChild(overlay);
    input = overlay.querySelector('.cmdk-input');
    body  = overlay.querySelector('.cmdk-body');
    overlay.addEventListener('mousedown', function(e){ if (e.target === overlay) close(); });
    overlay.querySelector('.cmdk-esc').addEventListener('click', close);
    input.addEventListener('input', function(){ query = input.value; hi = 0; render(); });
    input.addEventListener('keydown', onKey);
  }

  function filtered(){
    var q = query.trim().toLowerCase();
    if (!q) return null;
    var terms = q.split(/\s+/).filter(Boolean);
    return idx.filter(function(r){
      var hay = (r.label + ' ' + r.group).toLowerCase();
      return terms.every(function(t){ return hay.indexOf(t) > -1; });
    });
  }

  function itemHtml(r, i, badge){
    return '<button class="cmdk-item" data-i="'+i+'"><span class="cmdk-label">'+esc(r.label)+'</span>' +
      (badge
        ? '<span class="cmdk-badge" style="background:'+color(r.group)+'22;color:'+color(r.group)+'">'+esc(r.group)+'</span>'
        : '<i class="bi bi-arrow-right cmdk-arrow"></i>') +
      '</button>';
  }

  function render(){
    var res = filtered(), html = '';
    if (res === null) {
      var groups = {}, order = [];
      idx.forEach(function(r){ if (!groups[r.group]) { groups[r.group] = []; order.push(r.group); } groups[r.group].push(r); });
      var flat = [], i = 0;
      order.forEach(function(g){
        html += '<div class="cmdk-sec"><span class="cmdk-dot" style="background:'+color(g)+'"></span>'+esc(g)+'</div>';
        groups[g].forEach(function(r){ html += itemHtml(r, i, false); flat.push(r); i++; });
      });
      results = flat;
    } else if (res.length) {
      results = res;
      res.forEach(function(r, i){ html += itemHtml(r, i, true); });
    } else {
      results = [];
      html = '<div class="cmdk-empty">No menu items match “'+esc(query.trim())+'”.</div>';
    }
    body.innerHTML = html;
    bindItems();
    highlight();
  }

  function bindItems(){
    body.querySelectorAll('.cmdk-item').forEach(function(btn){
      var i = +btn.getAttribute('data-i');
      btn.addEventListener('mousemove', function(){ if (hi !== i) { hi = i; highlight(); } });
      btn.addEventListener('mousedown', function(e){ e.preventDefault(); go(results[i]); });
    });
  }
  function highlight(){
    body.querySelectorAll('.cmdk-item').forEach(function(btn){
      var i = +btn.getAttribute('data-i'), on = (i === hi);
      btn.classList.toggle('hi', on);
      if (on) btn.scrollIntoView({ block: 'nearest' });
    });
  }
  function onKey(e){
    if (e.key === 'Escape') { close(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); hi = Math.min(hi + 1, results.length - 1); highlight(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); hi = Math.max(hi - 1, 0); highlight(); }
    else if (e.key === 'Enter') { e.preventDefault(); if (results[hi]) go(results[hi]); }
  }
  function go(r){ if (!r) return; if (r.target === '_blank') window.open(r.href, '_blank'); else window.location.href = r.href; close(); }
  function open(){ ensure(); if (!idx) idx = buildIndex(); query = ''; hi = 0; input.value = ''; render(); overlay.classList.add('open'); setTimeout(function(){ input.focus(); }, 20); }
  function close(){ if (overlay) overlay.classList.remove('open'); }

  window.addEventListener('keydown', function(e){
    if ((e.ctrlKey || e.metaKey) && (e.key === 'm' || e.key === 'M')) {
      e.preventDefault();
      (overlay && overlay.classList.contains('open')) ? close() : open();
    }
  });
  window.openMenuPalette = open;
})();
</script>
</body>
</html>
