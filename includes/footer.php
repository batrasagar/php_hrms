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
      if (window.initTomSelects) window.initTomSelects($results[0]);
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
</body>
</html>
