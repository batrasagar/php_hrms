<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/card_entry.php';
requireAdmin();
blockCompliance();

$db   = getDb();
$user = currentUser();

// Table may not exist until M031 runs
try { $db->query("SELECT 1 FROM tblCardTemplate LIMIT 1"); }
catch (Throwable $ex) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── AJAX save ─────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    csrf_verify();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id     = (int)($in['id'] ?? 0);
    $name   = trim($in['name'] ?? '');
    $wMm    = max(20, min(300, (float)($in['width_mm']  ?? 86)));
    $hMm    = max(20, min(300, (float)($in['height_mm'] ?? 54)));
    $layout = json_encode($in['layout'] ?? new stdClass(), JSON_UNESCAPED_UNICODE);
    if (!$fCompany)          { echo json_encode(['success'=>false,'errors'=>['No active company.']]); exit; }
    if ($name === '')        { echo json_encode(['success'=>false,'errors'=>['Template name is required.']]); exit; }
    if (strlen($layout) > 8_000_000) { echo json_encode(['success'=>false,'errors'=>['Layout too large — shrink embedded images.']]); exit; }
    if ($id) {
        $chk = $db->prepare("SELECT id FROM tblCardTemplate WHERE id=? AND CompanyId=?");
        $chk->execute([$id, $fCompany]);
        if (!$chk->fetch()) { echo json_encode(['success'=>false,'errors'=>['Template not found for this company.']]); exit; }
        $db->prepare("UPDATE tblCardTemplate SET Name=?, WidthMm=?, HeightMm=?, Layout=? WHERE id=?")
           ->execute([$name, $wMm, $hMm, $layout, $id]);
    } else {
        $db->prepare("INSERT INTO tblCardTemplate (CompanyId, Name, WidthMm, HeightMm, Layout) VALUES (?,?,?,?,?)")
           ->execute([$fCompany, $name, $wMm, $hMm, $layout]);
        $id = (int)$db->lastInsertId();
    }
    echo json_encode(['success'=>true, 'id'=>$id]);
    exit;
}

// ── Load template (edit) or defaults (new) ────────────────────────────────────
$tplId  = (int)($_GET['id'] ?? 0);
$tplRow = null;
if ($tplId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblCardTemplate WHERE id=? AND CompanyId=?");
    $s->execute([$tplId, $fCompany]);
    $tplRow = $s->fetch();
    if (!$tplRow) { header('Location: card_templates.php'); exit; }
}

// ── Sample employee for the live preview ─────────────────────────────────────
$sampleEntry = null;
if ($fCompany) {
    $s = $db->prepare(
        "SELECT e.*, c.Name AS CompanyName, c.Address AS CompanyAddress,
                c.SignImage, c.SignName, c.SignDesignation
         FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
         WHERE e.CompanyId=? AND e.Status='active'
         ORDER BY (e.Photo IS NULL OR e.Photo=''), e.id LIMIT 1"
    );
    $s->execute([$fCompany]);
    if ($row = $s->fetch()) $sampleEntry = cardEntryFromRow($row, BASE_URL);
}
if (!$sampleEntry) {
    $sampleEntry = cardEntryFromRow([
        'CompanyName'=>'Demo Company Pvt Ltd', 'CompanyAddress'=>'Industrial Area, City',
        'EmployeeCode'=>'EMP001', 'Name'=>'Sample Employee', 'FatherName'=>'Sample Father',
        'Department'=>'Production', 'Designation'=>'Operator', 'Phone'=>'9876543210',
        'AdhaarID'=>'123456789012', 'BloodGroup'=>'B+', 'DOB'=>'1995-06-15', 'JoinDate'=>'2024-01-01',
        'PermanentAdd'=>'123, Sample Street, Sample City',
    ], BASE_URL);
}

$pageTitle  = $tplRow ? ('Card Designer — ' . $tplRow['Name']) : 'Card Designer — New Template';
$activePage = 'card_templates';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.cdz-wrap    { display:flex; gap:12px; align-items:flex-start; }
.cdz-main    { flex:1; min-width:0; }
.cdz-side    { width:250px; flex-shrink:0; }
.cdz-canvas-outer { background:#e9ecef; border-radius:10px; padding:24px; overflow:auto; min-height:420px;
                    display:flex; align-items:flex-start; justify-content:center; }
.cdz-canvas-holder { position:relative; }
.cdz-canvas .cr-card { box-shadow:0 4px 18px rgba(0,0,0,.18);
  background-image:linear-gradient(rgba(0,0,0,.045) 1px, transparent 1px),
                   linear-gradient(90deg, rgba(0,0,0,.045) 1px, transparent 1px);
  background-size:10px 10px; }
.cdz-canvas [data-idx] { cursor:move; }
.cdz-selbox  { position:absolute; border:1.5px dashed #0071e3; pointer-events:none; z-index:50; }
.cdz-handle  { position:absolute; right:-6px; bottom:-6px; width:12px; height:12px; background:#0071e3;
               border:2px solid #fff; border-radius:3px; cursor:se-resize; pointer-events:auto; }
.cdz-layers  { max-height:300px; overflow-y:auto; }
.cdz-layer   { display:flex; align-items:center; gap:6px; padding:4px 8px; border-radius:6px;
               font-size:12px; cursor:pointer; }
.cdz-layer:hover   { background:#f1f3f5; }
.cdz-layer.active  { background:var(--blue-lt); color:var(--blue); font-weight:600; }
.cdz-layer .btns   { margin-left:auto; display:none; gap:2px; }
.cdz-layer:hover .btns, .cdz-layer.active .btns { display:flex; }
.cdz-layer .btns button { border:none; background:none; padding:0 3px; font-size:11px; color:#888; cursor:pointer; }
.cdz-layer .btns button:hover { color:#000; }
.cdz-props .form-control-sm, .cdz-props .form-select-sm { font-size:12px; }
.cdz-props label { font-size:10.5px; color:#6c757d; margin-bottom:1px; display:block; }
.cdz-props .col-p { width:74px; }
.cdz-props .col-p2 { width:110px; }
.cdz-toggle.active { background:var(--blue); color:#fff; border-color:var(--blue); }
</style>

<div class="card border-0 shadow-sm mb-2">
  <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label small mb-1">Template Name</label>
      <input type="text" id="tName" class="form-control form-control-sm" style="width:220px" value="<?= htmlspecialchars($tplRow['Name'] ?? 'New Card Template') ?>">
    </div>
    <div><label class="form-label small mb-1">W (mm)</label>
      <input type="number" id="tW" class="form-control form-control-sm" style="width:80px" step="0.5" min="20" max="300" value="<?= $tplRow ? (float)$tplRow['WidthMm'] : 86 ?>">
    </div>
    <div><label class="form-label small mb-1">H (mm)</label>
      <input type="number" id="tH" class="form-control form-control-sm" style="width:80px" step="0.5" min="20" max="300" value="<?= $tplRow ? (float)$tplRow['HeightMm'] : 54 ?>">
    </div>
    <div class="btn-group btn-group-sm" role="group">
      <button type="button" class="btn btn-outline-primary active" id="tabFront">Front</button>
      <button type="button" class="btn btn-outline-primary" id="tabBack">Back</button>
    </div>
    <div class="form-check align-self-center mt-3">
      <input type="checkbox" class="form-check-input" id="hasBack">
      <label class="form-check-label small" for="hasBack">Back side</label>
    </div>
    <div class="btn-group btn-group-sm ms-auto" role="group">
      <button type="button" class="btn btn-outline-secondary" id="zOut"><i class="bi bi-zoom-out"></i></button>
      <button type="button" class="btn btn-outline-secondary" disabled id="zLbl">8×</button>
      <button type="button" class="btn btn-outline-secondary" id="zIn"><i class="bi bi-zoom-in"></i></button>
    </div>
    <button type="button" class="btn btn-success btn-sm" id="btnSave"><i class="bi bi-check-lg me-1"></i>Save</button>
    <button type="button" class="btn btn-outline-success btn-sm" id="btnTest"><i class="bi bi-printer me-1"></i>Test Print</button>
    <a href="card_templates.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list me-1"></i>Templates</a>
  </div>
</div>

<!-- Contextual properties bar -->
<div class="card border-0 shadow-sm mb-2 cdz-props" id="propsBar" style="display:none">
  <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
    <div class="col-p"><label>X (mm)</label><input type="number" step="0.5" class="form-control form-control-sm" data-p="x"></div>
    <div class="col-p"><label>Y (mm)</label><input type="number" step="0.5" class="form-control form-control-sm" data-p="y"></div>
    <div class="col-p" data-g="wh"><label>W (mm)</label><input type="number" step="0.5" min="0" class="form-control form-control-sm" data-p="w"></div>
    <div class="col-p" data-g="wh"><label>H (mm)</label><input type="number" step="0.5" min="0" class="form-control form-control-sm" data-p="h"></div>
    <div class="col-p" data-g="line"><label>Length</label><input type="number" step="0.5" class="form-control form-control-sm" data-p="len"></div>
    <div class="col-p" data-g="line"><label>Thick</label><input type="number" step="0.1" class="form-control form-control-sm" data-p="thickness"></div>
    <div class="col-p2" data-g="txt"><label>Font</label>
      <select class="form-select form-select-sm" data-p="fontFamily">
        <option value="Arial, sans-serif">Arial</option>
        <option value="Helvetica, Arial, sans-serif">Helvetica</option>
        <option value="'Times New Roman', serif">Times</option>
        <option value="Georgia, serif">Georgia</option>
        <option value="Verdana, sans-serif">Verdana</option>
        <option value="Tahoma, sans-serif">Tahoma</option>
        <option value="'Courier New', monospace">Courier</option>
        <option value="'Trebuchet MS', sans-serif">Trebuchet</option>
      </select>
    </div>
    <div class="col-p" data-g="txt"><label>Size (pt)</label><input type="number" step="0.5" min="4" max="48" class="form-control form-control-sm" data-p="fontSize"></div>
    <div data-g="txt"><label>&nbsp;</label>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary cdz-toggle" data-t="bold"><b>B</b></button>
        <button type="button" class="btn btn-outline-secondary cdz-toggle" data-t="italic"><i>I</i></button>
      </div>
    </div>
    <div data-g="txt"><label>Align</label>
      <select class="form-select form-select-sm" data-p="align" style="width:80px">
        <option value="left">Left</option><option value="center">Center</option><option value="right">Right</option>
      </select>
    </div>
    <div class="col-p" data-g="color"><label>Color</label><input type="color" class="form-control form-control-sm form-control-color" data-p="color"></div>
    <div class="col-p2" data-g="prefix"><label>Prefix</label><input type="text" class="form-control form-control-sm" data-p="prefix" placeholder="e.g. Name: "></div>
    <div style="min-width:220px;flex:1" data-g="content"><label>Text ({tokens} allowed)</label><input type="text" class="form-control form-control-sm" data-p="content"></div>
    <div data-g="imgsrc"><label>Source</label>
      <select class="form-select form-select-sm" data-p="source" style="width:130px">
        <option value="photo">Photo</option><option value="signature">Emp. Signature</option><option value="issuer_sign">Issuer Sign</option>
      </select>
    </div>
    <div data-g="codesrc"><label>Source</label>
      <select class="form-select form-select-sm" data-p="source" style="width:130px">
        <option value="empcode">Employee Code</option><option value="aadhaar">Aadhaar</option><option value="custom">Custom…</option>
      </select>
    </div>
    <div class="col-p" data-g="fit"><label>Fit</label>
      <select class="form-select form-select-sm" data-p="fit"><option value="cover">Cover</option><option value="contain">Contain</option></select>
    </div>
    <div class="col-p" data-g="rect"><label>Fill</label><input type="color" class="form-control form-control-sm form-control-color" data-p="fill"></div>
    <div class="col-p" data-g="box"><label>Border</label><input type="number" step="0.1" min="0" class="form-control form-control-sm" data-p="borderW"></div>
    <div class="col-p" data-g="box"><label>B.Color</label><input type="color" class="form-control form-control-sm form-control-color" data-p="borderColor"></div>
    <div class="col-p" data-g="box"><label>Radius</label><input type="number" step="0.5" min="0" class="form-control form-control-sm" data-p="radius"></div>
    <div class="col-p"><label>Rotate°</label><input type="number" step="5" min="0" max="359" class="form-control form-control-sm" data-p="rotation"></div>
    <div><label>&nbsp;</label>
      <button type="button" class="btn btn-outline-danger btn-sm" id="btnDelEl"><i class="bi bi-trash"></i></button>
    </div>
  </div>
</div>

<div class="cdz-wrap">
  <div class="cdz-main">
    <div class="cdz-canvas-outer">
      <div class="cdz-canvas-holder">
        <div class="cdz-canvas" id="canvas"></div>
        <div class="cdz-selbox" id="selBox" style="display:none"><span class="cdz-handle" id="selHandle"></span></div>
      </div>
    </div>
  </div>

  <div class="cdz-side">
    <div class="card border-0 shadow-sm mb-2">
      <div class="card-header bg-white py-2 fw-semibold" style="font-size:12.5px">Add Element</div>
      <div class="card-body p-2 d-flex flex-wrap gap-1">
        <select id="addField" class="form-select form-select-sm mb-1">
          <option value="">+ Employee field…</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" data-add="text"><i class="bi bi-fonts"></i> Text</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="lineh">— H Line</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="linev">| V Line</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="rect"><i class="bi bi-square"></i> Box</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="photo"><i class="bi bi-person-square"></i> Photo</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="barcode"><i class="bi bi-upc"></i> Barcode</button>
        <button class="btn btn-outline-secondary btn-sm" data-add="qr"><i class="bi bi-qr-code"></i> QR</button>
        <label class="btn btn-outline-secondary btn-sm mb-0"><i class="bi bi-image"></i> Image
          <input type="file" id="addImage" accept="image/*" hidden>
        </label>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-2 fw-semibold d-flex justify-content-between" style="font-size:12.5px">
        <span>Layers</span><span class="text-muted" id="layerCount"></span>
      </div>
      <div class="card-body p-1 cdz-layers" id="layers"></div>
    </div>
  </div>
</div>

<?php
$tplJson    = $tplRow ? ($tplRow['Layout'] ?: 'null') : 'null';
$sampleJson = json_encode($sampleEntry, JSON_UNESCAPED_UNICODE);
$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
<script src="card_render.js?v=1"></script>
<script>
(function(){
"use strict";
var CSRF   = document.querySelector("meta[name=csrf-token]").content;
var SAMPLE = ' . $sampleJson . ';
var savedLayout = ' . $tplJson . ';

var FIELDS = [
  ["name","Name"],["code","Emp Code"],["father_name","Father Name"],["department","Department"],
  ["designation","Designation"],["dob","DOB"],["doj","DOJ"],["phone","Phone"],["email","Email"],
  ["blood_group","Blood Group"],["aadhaar","Aadhaar"],["gender","Gender"],["grade","Grade"],
  ["contractor","Contractor"],["present_add","Present Address"],["permanent_add","Permanent Address"],
  ["company_name","Company Name"],["company_address","Company Address"],
  ["issuer_name","Issuer Name"],["issuer_designation","Issuer Designation"],
  ["uan","UAN"],["pf_no","PF No"],["esi_no","ESI No"],["pan_no","PAN"],["enroll_id","Enroll ID"]
];
FIELDS.forEach(function(f){ addField.add(new Option(f[1], f[0])); });

function defaultLayout(){
  return { front: { elements: [
    { type:"rect",  x:0, y:0, w:86, h:11, fill:"#0d3b66", borderW:0, borderColor:"#000", radius:0, rotation:0 },
    { type:"text",  content:"{company_name}", x:2, y:2, w:82, h:0, fontSize:11, fontFamily:"Arial, sans-serif", bold:true, italic:false, align:"center", color:"#ffffff", rotation:0 },
    { type:"text",  content:"{company_address}", x:2, y:7, w:82, h:0, fontSize:6, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"center", color:"#dce6f2", rotation:0 },
    { type:"photo", source:"photo", x:2.5, y:13.5, w:20, h:24, fit:"cover", borderW:0.4, borderColor:"#0d3b66", radius:1, rotation:0 },
    { type:"field", key:"name", prefix:"", x:25, y:14, w:59, h:0, fontSize:11, fontFamily:"Arial, sans-serif", bold:true, italic:false, align:"left", color:"#111111", rotation:0 },
    { type:"field", key:"code", prefix:"Code: ", x:25, y:20, w:40, h:0, fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#333333", rotation:0 },
    { type:"field", key:"department", prefix:"Dept: ", x:25, y:24.5, w:59, h:0, fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#333333", rotation:0 },
    { type:"field", key:"designation", prefix:"Desig: ", x:25, y:29, w:59, h:0, fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#333333", rotation:0 },
    { type:"field", key:"blood_group", prefix:"Blood: ", x:25, y:33.5, w:30, h:0, fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#b00020", rotation:0 },
    { type:"field", key:"phone", prefix:"Ph: ", x:25, y:38, w:40, h:0, fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#333333", rotation:0 },
    { type:"barcode", source:"empcode", x:2.5, y:40, w:20, h:8, rotation:0 },
    { type:"qr", source:"aadhaar", x:70, y:38, w:13, h:13, rotation:0 },
    { type:"line", orient:"h", x:2, y:49.5, len:82, thickness:0.3, color:"#0d3b66", rotation:0 }
  ]}, back: null };
}

var tpl = {
  id: ' . ($tplRow ? (int)$tplRow['id'] : 0) . ',
  name: ' . json_encode($tplRow['Name'] ?? 'New Card Template') . ',
  width_mm: ' . ($tplRow ? (float)$tplRow['WidthMm'] : 86) . ',
  height_mm: ' . ($tplRow ? (float)$tplRow['HeightMm'] : 54) . ',
  layout: (savedLayout && savedLayout.front) ? savedLayout : defaultLayout()
};
var side = "front", sel = -1, zoom = 8;

var canvas = document.getElementById("canvas"), selBox = document.getElementById("selBox"),
    layers = document.getElementById("layers"), props = document.getElementById("propsBar");

hasBack.checked = !!tpl.layout.back;
tabBack.disabled = !tpl.layout.back;

function els(){ return (tpl.layout[side] || {elements:[]}).elements; }
function selEl(){ return sel >= 0 ? els()[sel] : null; }
function round2(v){ return Math.round(v*2)/2; }

function elName(e){
  if (e.type === "field") { var f = FIELDS.find(function(x){return x[0]===e.key;}); return "Field: " + (f?f[1]:e.key); }
  if (e.type === "text")  return "Text: " + (e.content||"").slice(0,18);
  if (e.type === "line")  return (e.orient==="v"?"V":"H") + " Line";
  return { rect:"Box", photo:"Photo/Sign", image:"Image", barcode:"Barcode", qr:"QR Code" }[e.type] || e.type;
}

function render(){
  tpl.name      = tName.value;
  tpl.width_mm  = +tW.value || 86;
  tpl.height_mm = +tH.value || 54;
  canvas.innerHTML = CardRender.cardHtml(tpl, side, SAMPLE, {unit:"px", zoom:zoom, designer:true},
    function(i){ return \'data-idx="\'+i+\'"\'; });
  CardRender.renderCodes(canvas);
  zLbl.textContent = zoom + "×";
  renderLayers(); positionSelBox(); syncProps();
}

function renderLayers(){
  var h = "";
  els().forEach(function(e,i){
    h += \'<div class="cdz-layer\'+(i===sel?" active":"")+\'" data-l="\'+i+\'">\'
       + \'<i class="bi bi-grip-vertical text-muted"></i><span>\'+elName(e)+\'</span>\'
       + \'<span class="btns">\'
       + \'<button data-a="up" title="Raise">▲</button><button data-a="dn" title="Lower">▼</button>\'
       + \'<button data-a="dup" title="Duplicate">⧉</button><button data-a="del" title="Delete">✕</button>\'
       + \'</span></div>\';
  });
  layers.innerHTML = h || \'<div class="text-muted small p-2">No elements</div>\';
  layerCount.textContent = els().length;
}

function positionSelBox(){
  var node = canvas.querySelector(\'[data-idx="\'+sel+\'"]\');
  if (sel < 0 || !node){ selBox.style.display = "none"; return; }
  selBox.style.display = "block";
  selBox.style.left   = (node.offsetLeft-2) + "px";
  selBox.style.top    = (node.offsetTop-2)  + "px";
  selBox.style.width  = (node.offsetWidth+4)  + "px";
  selBox.style.height = (node.offsetHeight+4) + "px";
}

// ── Properties bar sync ───────────────────────────────────────────────────────
var GROUPS = {
  field:  ["wh","txt","color","prefix"],
  text:   ["wh","txt","color","content"],
  line:   ["line","color"],
  rect:   ["wh","rect","box"],
  photo:  ["wh","imgsrc","fit","box"],
  image:  ["wh","box"],
  barcode:["wh","codesrc"],
  qr:     ["wh","codesrc"]
};
function syncProps(){
  var e = selEl();
  if (!e){ props.style.display = "none"; return; }
  props.style.display = "";
  var show = GROUPS[e.type] || [];
  props.querySelectorAll("[data-g]").forEach(function(n){
    n.style.display = show.indexOf(n.dataset.g) >= 0 ? "" : "none";
  });
  // custom content field also for code sources set to custom
  if ((e.type==="barcode"||e.type==="qr") && e.source==="custom")
    props.querySelector(\'[data-g="content"]\').style.display = "";
  props.querySelectorAll("[data-p]").forEach(function(inp){
    var k = inp.dataset.p;
    if (e[k] === undefined) return;
    if (inp.type === "color") inp.value = e[k] || "#000000";
    else inp.value = e[k];
  });
  props.querySelectorAll(".cdz-toggle").forEach(function(b){
    b.classList.toggle("active", !!e[b.dataset.t]);
  });
}
props.addEventListener("input", function(ev){
  var e = selEl(), k = ev.target.dataset.p;
  if (!e || !k) return;
  var v = ev.target.value;
  if (ev.target.type === "number") v = parseFloat(v) || 0;
  e[k] = v;
  render();
});
props.addEventListener("click", function(ev){
  var b = ev.target.closest(".cdz-toggle"), e = selEl();
  if (!b || !e) return;
  e[b.dataset.t] = !e[b.dataset.t];
  render();
});
btnDelEl.addEventListener("click", function(){ removeSel(); });

// ── Selection / drag / resize ────────────────────────────────────────────────
var drag = null;
canvas.addEventListener("mousedown", function(ev){
  var n = ev.target.closest("[data-idx]");
  if (!n){ sel = -1; render(); return; }
  sel = +n.dataset.idx;
  var e = selEl();
  drag = { sx:ev.clientX, sy:ev.clientY, ox:+e.x||0, oy:+e.y||0, moved:false };
  render();
  // render() rebuilt the DOM — re-grab the node for cheap live moves
  drag.node = canvas.querySelector(\'[data-idx="\'+sel+\'"]\');
  ev.preventDefault();
});
selHandle.addEventListener("mousedown", function(ev){
  var e = selEl(); if (!e) return;
  drag = { resize:true, sx:ev.clientX, sy:ev.clientY,
           ow:+(e.type==="line" ? e.len : e.w)||0, oh:+(e.type==="line" ? e.thickness : e.h)||0 };
  ev.stopPropagation(); ev.preventDefault();
});
document.addEventListener("mousemove", function(ev){
  if (!drag) return;
  var e = selEl(); if (!e) return;
  var dx = (ev.clientX-drag.sx)/zoom, dy = (ev.clientY-drag.sy)/zoom;
  if (drag.resize){
    if (e.type === "line"){
      if (e.orient === "v"){ e.len = Math.max(1, round2(drag.ow+dy)); e.thickness = Math.max(0.1, +(drag.oh+dx).toFixed(1)); }
      else { e.len = Math.max(1, round2(drag.ow+dx)); e.thickness = Math.max(0.1, +(drag.oh+dy).toFixed(1)); }
    } else {
      e.w = Math.max(0, round2(drag.ow+dx));
      e.h = Math.max(0, round2(drag.oh+dy));
    }
    render();
  } else {
    drag.moved = true;
    e.x = round2(Math.min(Math.max(drag.ox+dx, -5), tpl.width_mm));
    e.y = round2(Math.min(Math.max(drag.oy+dy, -5), tpl.height_mm));
    // cheap live move: shift the node, full re-render on mouseup
    drag.node.style.left = (e.x*zoom)+"px";
    drag.node.style.top  = (e.y*zoom)+"px";
    positionSelBox(); syncProps();
  }
});
document.addEventListener("mouseup", function(){
  if (drag && drag.moved) render();
  drag = null;
});

// keyboard nudge / delete / bold / italic
document.addEventListener("keydown", function(ev){
  var e = selEl();
  if (!e || /INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) return;
  var step = ev.shiftKey ? 2 : 0.5, done = true;
  if      (ev.key === "ArrowLeft")  e.x = round2((+e.x||0)-step);
  else if (ev.key === "ArrowRight") e.x = round2((+e.x||0)+step);
  else if (ev.key === "ArrowUp")    e.y = round2((+e.y||0)-step);
  else if (ev.key === "ArrowDown")  e.y = round2((+e.y||0)+step);
  else if (ev.key === "Delete" || ev.key === "Backspace") { removeSel(); return; }
  else if ((ev.ctrlKey||ev.metaKey) && ev.key.toLowerCase() === "b") e.bold = !e.bold;
  else if ((ev.ctrlKey||ev.metaKey) && ev.key.toLowerCase() === "i") e.italic = !e.italic;
  else done = false;
  if (done){ ev.preventDefault(); render(); }
});
function removeSel(){
  if (sel < 0) return;
  els().splice(sel,1); sel = -1; render();
}

// ── Layers panel actions ─────────────────────────────────────────────────────
layers.addEventListener("click", function(ev){
  var row = ev.target.closest("[data-l]");
  if (!row) return;
  var i = +row.dataset.l, a = ev.target.dataset.a, arr = els();
  if (a === "del")      { arr.splice(i,1); sel = -1; }
  else if (a === "dup") { var c = JSON.parse(JSON.stringify(arr[i])); c.x = (+c.x||0)+3; c.y = (+c.y||0)+3; arr.splice(i+1,0,c); sel = i+1; }
  else if (a === "up" && i < arr.length-1) { arr.splice(i,2,arr[i+1],arr[i]); sel = i+1; }   // later = on top
  else if (a === "dn" && i > 0)            { arr.splice(i-1,2,arr[i],arr[i-1]); sel = i-1; }
  else sel = i;
  render();
});

// ── Add elements ─────────────────────────────────────────────────────────────
function addEl(e){ els().push(e); sel = els().length-1; render(); }
var TXT = { fontSize:8, fontFamily:"Arial, sans-serif", bold:false, italic:false, align:"left", color:"#000000", rotation:0 };
addField.addEventListener("change", function(){
  if (!this.value) return;
  addEl(Object.assign({ type:"field", key:this.value, prefix:"", x:5, y:5, w:0, h:0 }, TXT));
  this.value = "";
});
document.querySelectorAll("[data-add]").forEach(function(b){
  b.addEventListener("click", function(){
    var t = this.dataset.add;
    if (t === "text")   addEl(Object.assign({ type:"text", content:"Custom text", x:5, y:5, w:0, h:0 }, TXT));
    if (t === "lineh")  addEl({ type:"line", orient:"h", x:2, y:20, len:40, thickness:0.3, color:"#000000", rotation:0 });
    if (t === "linev")  addEl({ type:"line", orient:"v", x:20, y:2, len:20, thickness:0.3, color:"#000000", rotation:0 });
    if (t === "rect")   addEl({ type:"rect", x:5, y:5, w:30, h:15, fill:"#e8f0fe", borderW:0.3, borderColor:"#0d3b66", radius:1, rotation:0 });
    if (t === "photo")  addEl({ type:"photo", source:"photo", x:5, y:5, w:20, h:24, fit:"cover", borderW:0.3, borderColor:"#333333", radius:0.5, rotation:0 });
    if (t === "barcode")addEl({ type:"barcode", source:"empcode", x:5, y:40, w:25, h:8, rotation:0 });
    if (t === "qr")     addEl({ type:"qr", source:"empcode", x:60, y:35, w:14, h:14, rotation:0 });
  });
});
addImage.addEventListener("change", function(){
  var f = this.files[0]; this.value = "";
  if (!f) return;
  if (f.size > 400*1024) { alert("Image too large — keep under 400 KB (use a compressed PNG/JPG)."); return; }
  var r = new FileReader();
  r.onload = function(){ addEl({ type:"image", src:r.result, x:5, y:5, w:20, h:12, radius:0, rotation:0 }); };
  r.readAsDataURL(f);
});

// ── Sides ─────────────────────────────────────────────────────────────────────
function setSide(s){
  side = s; sel = -1;
  tabFront.classList.toggle("active", s==="front");
  tabBack.classList.toggle("active", s==="back");
  render();
}
tabFront.addEventListener("click", function(){ setSide("front"); });
tabBack.addEventListener("click", function(){ if (tpl.layout.back) setSide("back"); });
hasBack.addEventListener("change", function(){
  if (this.checked){ tpl.layout.back = tpl.layout.back || { elements: [] }; tabBack.disabled = false; setSide("back"); }
  else {
    if (tpl.layout.back && tpl.layout.back.elements.length && !confirm("Remove the back side and its elements?")) { this.checked = true; return; }
    tpl.layout.back = null; tabBack.disabled = true; setSide("front");
  }
});

// ── Zoom / size ───────────────────────────────────────────────────────────────
zIn.addEventListener("click", function(){ zoom = Math.min(14, zoom+1); render(); });
zOut.addEventListener("click", function(){ zoom = Math.max(3, zoom-1); render(); });
[tW, tH, tName].forEach(function(i){ i.addEventListener("change", render); });

// ── Save / test print ─────────────────────────────────────────────────────────
function save(cb){
  fetch("card_designer.php?action=save", {
    method: "POST",
    headers: { "Content-Type":"application/json", "X-CSRF-Token": CSRF },
    body: JSON.stringify(tpl)
  }).then(function(r){ return r.json(); }).then(function(d){
    if (d.success){
      if (!tpl.id){ tpl.id = d.id; history.replaceState(null, "", "card_designer.php?id="+d.id); }
      showToast("Template saved.");
      if (cb) cb();
    } else showToast((d.errors||["Save failed"])[0], "danger");
  }).catch(function(){ showToast("Save failed — network error.", "danger"); });
}
btnSave.addEventListener("click", function(){ save(); });
btnTest.addEventListener("click", function(){
  save(function(){ window.open("card_print.php?template_id="+tpl.id+"&test=1", "_blank"); });
});

render();
})();
</script>';
require_once __DIR__ . '/../../includes/footer.php';
?>
