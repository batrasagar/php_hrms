<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDocTemplate LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]);
    $companiesDd = $s->fetchAll();
}
$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]);
    if (!$chk->fetch()) $fCompany = 0;
}

$docTypes = [
    'offer_letter'       => 'Offer Letter',
    'joining_letter'     => 'Joining Letter',
    'appointment_letter' => 'Appointment Letter',
    'experience_letter'  => 'Experience Letter',
    'warning_letter'     => 'Warning Letter',
    'termination_letter' => 'Termination Letter',
    'custom'             => 'Custom',
];

$msg = ''; $msgType = 'success';
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $type    = isset($docTypes[$_POST['doc_type'] ?? '']) ? $_POST['doc_type'] : 'custom';
        $content = $_POST['content'] ?? '';
        if (!$name) { $msg = 'Name is required.'; $msgType = 'danger'; if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$msg]]); exit; } }
        else {
            if ($id) {
                $db->prepare("UPDATE tblDocTemplate SET Name=?, DocType=?, Content=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
                   ->execute([$name, $type, $content, $id, $fCompany]);
                $editId = $id; $msg = 'Template saved.';
            } else {
                $db->prepare("INSERT INTO tblDocTemplate (CompanyId,Name,DocType,Content) VALUES (?,?,?,?)")
                   ->execute([$fCompany, $name, $type, $content]);
                $editId = (int)$db->lastInsertId(); $msg = 'Template created.';
            }
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>"templates.php?company=$fCompany&edit=$editId"]); exit; }
            header("Location: templates.php?company=$fCompany&edit=$editId&msg=" . urlencode($msg)); exit;
        }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE tblDocTemplate SET IsActive=1-IsActive WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"templates.php?company=$fCompany&edit=$editId"]); exit; }
        header("Location: templates.php?company=$fCompany&edit=$editId"); exit;
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM tblDocTemplate WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Template deleted.','redirect'=>"templates.php?company=$fCompany"]); exit; }
        header("Location: templates.php?company=$fCompany"); exit;
    }
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$templates = [];
if ($fCompany) {
    $s = $db->prepare("SELECT id, Name, DocType, IsActive FROM tblDocTemplate WHERE CompanyId=? ORDER BY DocType, Name");
    $s->execute([$fCompany]); $templates = $s->fetchAll();
}
$editRow = null;
if ($editId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblDocTemplate WHERE id=? AND CompanyId=?");
    $s->execute([$editId, $fCompany]); $editRow = $s->fetch();
}

$availableVars = [
    'Personal'  => ['employee_name','employee_code','father_name','dob','gender','phone','present_address'],
    'Job'       => ['designation','department','contractor','join_date','dol','basic_salary','gross_salary'],
    'Statutory' => ['uan','pf_no','esi_no','pan_no'],
    'Bank'      => ['bank_name','bank_branch','bank_account','bank_ifsc'],
    'Other'     => ['company_name','today_date','issue_date'],
];

$pageTitle  = 'Document Templates';
$activePage = 'doc_templates';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-3" style="min-height:600px" id="tplLayout">

<!-- Template list -->
<div class="col-lg-3" id="tplSidebar">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
      <form method="GET" class="d-inline">
        <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:120px">
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="templates.php?company=<?= $fCompany ?>" class="btn btn-primary btn-sm ms-1"><i class="bi bi-plus-lg"></i></a>
    </div>
    <div class="list-group list-group-flush" style="overflow-y:auto;max-height:580px">
      <?php if (empty($templates)): ?>
      <div class="p-3 text-muted small text-center">No templates yet. Click + to create one.</div>
      <?php endif; ?>
      <?php foreach ($templates as $t): ?>
      <a href="templates.php?company=<?= $fCompany ?>&edit=<?= $t['id'] ?>"
         class="list-group-item list-group-item-action py-2 <?= $t['id']==$editId?'active':'' ?> <?= !$t['IsActive']?'text-muted':'' ?>">
        <div class="d-flex justify-content-between">
          <span class="fw-semibold small"><?= htmlspecialchars($t['Name']) ?></span>
          <?php if (!$t['IsActive']): ?><span class="badge bg-secondary">Off</span><?php endif; ?>
        </div>
        <div class="<?= $t['id']==$editId?'opacity-75':'text-muted' ?> small"><?= $docTypes[$t['DocType']] ?? $t['DocType'] ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Editor -->
<div class="col-lg-9" id="tplEditor">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
      <span><?= $editRow ? 'Edit Template: ' . htmlspecialchars($editRow['Name']) : 'New Template' ?></span>
      <button type="button" id="btnToggleSidebar" class="btn btn-sm btn-outline-secondary"
              title="Toggle template list" onclick="toggleTplSidebar()">
        <i class="bi bi-layout-sidebar" id="sidebarToggleIcon"></i>
      </button>
    </div>
    <div class="card-body">
      <form method="POST" action="templates.php?company=<?= $fCompany ?>" data-ajax>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editRow ? $editRow['id'] : 0 ?>">
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label">Template Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= htmlspecialchars($editRow['Name'] ?? '') ?>" placeholder="e.g. Standard Offer Letter">
          </div>
          <div class="col-sm-4">
            <label class="form-label">Type</label>
            <select name="doc_type" class="form-select">
              <?php foreach ($docTypes as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($editRow['DocType'] ?? 'custom')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($editRow): ?>
          <div class="col-sm-2 d-flex align-items-end gap-1">
            <form method="POST" class="d-inline" action="templates.php?company=<?= $fCompany ?>&edit=<?= $editRow['id'] ?>" data-ajax>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $editRow['IsActive']?'btn-outline-warning':'btn-outline-success' ?>">
                <?= $editRow['IsActive']?'Deactivate':'Activate' ?>
              </button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete template?')" action="templates.php?company=<?= $fCompany ?>" data-ajax>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </div>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-lg-8" id="editorCol">
            <label class="form-label mb-1">Content</label>
            <textarea id="contentArea" name="content" rows="22"
                      placeholder="Write your template here. Use {{variable_name}} for dynamic fields."><?= htmlspecialchars($editRow['Content'] ?? '') ?></textarea>
          </div>
          <div class="col-lg-4" id="varPanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0">Insert Variable <span class="text-muted small">(click to insert)</span></label>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleVarPanel()" title="Collapse variable panel">
                <i class="bi bi-layout-sidebar-reverse" id="varPanelIcon"></i>
              </button>
            </div>
            <?php foreach ($availableVars as $group => $vars): ?>
            <div class="mb-2">
              <div class="text-muted small fw-semibold mb-1"><?= $group ?></div>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($vars as $v): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1"
                        style="font-size:11px" onclick="insertVar('<?= $v ?>')">
                  {{<?= $v ?>}}
                </button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <div class="alert alert-info py-2 mt-3" data-no-toast style="font-size:12px">
              <strong>Tip:</strong> Click a variable to insert at cursor. Use the Table menu in the toolbar — right-click a cell for row/column options.
            </div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-success"><i class="bi bi-floppy me-1"></i><?= $editRow?'Save Changes':'Create Template' ?></button>
          <?php if ($editRow): ?>
          <a href="issue.php?company=<?= $fCompany ?>&tpl=<?= $editRow['id'] ?>" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-text me-1"></i>Issue This Template →
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#contentArea',
    plugins: 'table lists link code wordcount',
    toolbar: [
        'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough',
        'forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | indent outdent | table | link | code | removeformat'
    ].join(' | '),
    table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol | tablecellprops | tablemergecells tablesplitcells',
    content_style: [
        "body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.8; margin: 20px; color: #000; }",
        "table { border-collapse: collapse; width: 100%; margin: 8px 0; }",
        "table td, table th { border: 1px solid #999; padding: 6px 10px; }",
        "table th { background: #f2f2f2; font-weight: bold; }"
    ].join(' '),
    height: 520,
    menubar: false,
    branding: false,
    promotion: false,
    statusbar: true,
    resize: true,
    setup: function(editor) {
        // Keep textarea in sync on every change so FormData picks up latest content
        editor.on('change input undo redo', function() { editor.save(); });
    }
});

// Ensure TinyMCE is synced before the global data-ajax handler collects FormData
document.addEventListener('submit', function(e) {
    if (e.target.matches('[data-ajax]') && typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
}, true); // capture phase fires before jQuery delegated handler

function insertVar(v) {
    const txt = '{{' + v + '}}';
    if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
        tinymce.activeEditor.insertContent(txt);
        tinymce.activeEditor.focus();
    }
}
</script>
<script>
(function () {
  var KEY       = 'tpl_sidebar_collapsed';
  var sidebar   = document.getElementById('tplSidebar');
  var editor    = document.getElementById('tplEditor');
  var icon      = document.getElementById('sidebarToggleIcon');
  var collapsed = false;

  function applyState(isCollapsed) {
    collapsed = isCollapsed;
    if (isCollapsed) {
      sidebar.classList.add('d-none');
      editor.classList.replace('col-lg-9', 'col-lg-12');
      icon.className = 'bi bi-layout-sidebar-reverse';
    } else {
      sidebar.classList.remove('d-none');
      editor.classList.replace('col-lg-12', 'col-lg-9');
      icon.className = 'bi bi-layout-sidebar';
    }
  }

  window.toggleTplSidebar = function () {
    var next = !collapsed;
    localStorage.setItem(KEY, next ? '1' : '0');
    applyState(next);
  };

  applyState(localStorage.getItem(KEY) === '1');
})();

// ── Variable panel collapse ───────────────────────────────────────────────
(function () {
  var VP_KEY     = 'tpl_varpanel_collapsed';
  var varPanel   = document.getElementById('varPanel');
  var editorCol  = document.getElementById('editorCol');
  var vpIcon     = document.getElementById('varPanelIcon');
  var vpCollapsed = false;

  function applyVp(isCollapsed) {
    vpCollapsed = isCollapsed;
    if (isCollapsed) {
      varPanel.classList.add('d-none');
      editorCol.classList.replace('col-lg-8', 'col-lg-12');
      vpIcon.className = 'bi bi-layout-sidebar';
    } else {
      varPanel.classList.remove('d-none');
      editorCol.classList.replace('col-lg-12', 'col-lg-8');
      vpIcon.className = 'bi bi-layout-sidebar-reverse';
    }
    // Notify TinyMCE of potential layout change
    if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
      tinymce.activeEditor.getWin().dispatchEvent(new Event('resize'));
    }
  }

  window.toggleVarPanel = function () {
    var next = !vpCollapsed;
    localStorage.setItem(VP_KEY, next ? '1' : '0');
    applyVp(next);
  };

  applyVp(localStorage.getItem(VP_KEY) === '1');
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
