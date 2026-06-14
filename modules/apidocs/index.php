<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
$pageTitle  = 'API Documentation';
$activePage = 'apidocs';
require_once __DIR__ . '/../../includes/header.php';

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
?>
<style>
  .doc-section { margin-bottom: 2.5rem; }
  .doc-section h5 { font-weight: 700; border-bottom: 2px solid #e3e6ef; padding-bottom: .4rem; margin-bottom: 1rem; }
  pre.code-block {
    background: #1e2a3a; color: #e8edf3; border-radius: 8px;
    padding: 1rem 1.2rem; font-size: .85rem; overflow-x: auto; margin: 0;
  }
  .method-badge { font-size: .75rem; padding: .3em .7em; border-radius: 4px; font-weight: 700; vertical-align: middle; }
  .badge-get { background: #198754; color: #fff; }
  .param-required { color: #dc3545; font-size: .75rem; font-weight: 600; }
  .param-optional { color: #6c757d; font-size: .75rem; }
  .endpoint-url {
    background: #f0f4ff; border: 1px solid #d0d9f0; border-radius: 6px;
    padding: .5rem 1rem; font-family: monospace; font-size: .9rem; word-break: break-all;
  }
</style>

<div style="max-width:860px">

  <!-- Overview -->
  <div class="doc-section">
    <h5><i class="bi bi-info-circle me-2"></i>Overview</h5>
    <p class="text-muted mb-2">The AttnLog REST API allows authorized clients to fetch punch log records from registered devices. All requests must be authenticated using an API key issued per company.</p>
    <div class="endpoint-url"><?= htmlspecialchars($baseUrl) ?>/api/</div>
  </div>

  <!-- Authentication -->
  <div class="doc-section">
    <h5><i class="bi bi-shield-lock me-2"></i>Authentication</h5>
    <p class="text-muted">Pass your API key in the <code>X-Api-Key</code> request header. Each key is scoped to a single company — it cannot access devices belonging to other companies.</p>
    <pre class="code-block">X-Api-Key: your-api-key-here</pre>
    <div class="mt-2 text-muted small">API keys are managed under <a href="<?= BASE_URL ?>/modules/apikeys/list.php">API Keys</a>.</div>
  </div>

  <!-- Endpoint -->
  <div class="doc-section">
    <h5><i class="bi bi-braces me-2"></i>Endpoints</h5>

    <!-- GET /api/punchlog.php -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="method-badge badge-get">GET</span>
          <span class="endpoint-url flex-grow-1"><?= htmlspecialchars($baseUrl) ?>/api/punchlog.php</span>
        </div>
        <p class="text-muted mb-3">Fetch punch log records for a specific device. Use <code>LastSerial</code> for incremental polling — pass the highest <code>id</code> you already have and only newer records are returned.</p>

        <h6 class="fw-semibold mb-2">Parameters</h6>
        <table class="table table-sm table-bordered mb-4">
          <thead class="table-light">
            <tr><th>Parameter</th><th>In</th><th>Type</th><th>Description</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>SerialNumber</code> <span class="param-required">required</span></td>
              <td>query</td>
              <td>string</td>
              <td>Serial number of the device to query. Must belong to the company bound to the API key.</td>
            </tr>
            <tr>
              <td><code>LastSerial</code> <span class="param-optional">optional</span></td>
              <td>query</td>
              <td>integer</td>
              <td>Last punch log <code>id</code> the client already has. Returns only records with <code>id &gt; LastSerial</code>. Omit to fetch all records.</td>
            </tr>
          </tbody>
        </table>

        <h6 class="fw-semibold mb-2">Example Request</h6>
        <ul class="nav nav-tabs mb-0" id="reqTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-curl">cURL</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-php">PHP</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-js">JavaScript</button></li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom mb-4">
          <div class="tab-pane fade show active p-0" id="tab-curl">
            <pre class="code-block rounded-0 rounded-bottom"><span style="color:#7ec8e3"># All records for a device</span>
curl -X GET \
  "<?= htmlspecialchars($baseUrl) ?>/api/punchlog.php?SerialNumber=ABC123" \
  -H "X-Api-Key: your-api-key-here"

<span style="color:#7ec8e3"># Only records after id 500 (incremental poll)</span>
curl -X GET \
  "<?= htmlspecialchars($baseUrl) ?>/api/punchlog.php?SerialNumber=ABC123&amp;LastSerial=500" \
  -H "X-Api-Key: your-api-key-here"</pre>
          </div>
          <div class="tab-pane fade p-0" id="tab-php">
            <pre class="code-block rounded-0 rounded-bottom">$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => '<?= htmlspecialchars($baseUrl) ?>/api/punchlog.php'
                            . '?SerialNumber=ABC123&amp;LastSerial=500',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Api-Key: your-api-key-here'],
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);</pre>
          </div>
          <div class="tab-pane fade p-0" id="tab-js">
            <pre class="code-block rounded-0 rounded-bottom">const res = await fetch(
  '<?= htmlspecialchars($baseUrl) ?>/api/punchlog.php?SerialNumber=ABC123&amp;LastSerial=500',
  { headers: { 'X-Api-Key': 'your-api-key-here' } }
);
const data = await res.json();</pre>
          </div>
        </div>

        <h6 class="fw-semibold mb-2">Success Response <span class="badge bg-success ms-1">200 OK</span></h6>
        <pre class="code-block mb-4">{
  "success": true,
  "count": 2,
  "data": [
    {
      "id": "501",
      "SerialNumber": "ABC123",
      "EnrollId": "2001",
      "PunchDateTime": "2025-06-08 09:30:00",
      "Mode": "IN",
      "CreatedAt": "2025-06-08 09:30:05"
    },
    {
      "id": "502",
      "SerialNumber": "ABC123",
      "EnrollId": "2001",
      "PunchDateTime": "2025-06-08 18:05:00",
      "Mode": "OUT",
      "CreatedAt": "2025-06-08 18:05:03"
    }
  ]
}</pre>

        <h6 class="fw-semibold mb-2">Error Responses</h6>
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr><th>HTTP Status</th><th>Condition</th><th>Error message</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge bg-warning text-dark">400</span></td><td>Missing <code>SerialNumber</code></td><td><code>Missing required parameter: SerialNumber</code></td></tr>
            <tr><td><span class="badge bg-warning text-dark">400</span></td><td>Invalid <code>LastSerial</code> value</td><td><code>LastSerial must be a non-negative integer</code></td></tr>
            <tr><td><span class="badge bg-danger">401</span></td><td>Missing <code>X-Api-Key</code> header</td><td><code>Missing X-Api-Key header</code></td></tr>
            <tr><td><span class="badge bg-danger">401</span></td><td>Key not found or inactive</td><td><code>Invalid or inactive API key</code></td></tr>
            <tr><td><span class="badge bg-danger">404</span></td><td>Serial number not in key's company</td><td><code>SerialNumber not found or not authorized for this API key</code></td></tr>
            <tr><td><span class="badge bg-secondary">500</span></td><td>Database error</td><td><code>Database error: ...</code></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Response Fields -->
  <div class="doc-section">
    <h5><i class="bi bi-table me-2"></i>Response Fields</h5>
    <table class="table table-sm table-bordered">
      <thead class="table-light">
        <tr><th>Field</th><th>Type</th><th>Description</th></tr>
      </thead>
      <tbody>
        <tr><td><code>id</code></td><td>integer</td><td>Unique punch record ID. Use as <code>LastSerial</code> in the next poll.</td></tr>
        <tr><td><code>SerialNumber</code></td><td>string</td><td>Device serial number.</td></tr>
        <tr><td><code>EnrollId</code></td><td>string</td><td>Employee/user ID enrolled on the device.</td></tr>
        <tr><td><code>PunchDateTime</code></td><td>datetime</td><td>Attendance timestamp (<code>YYYY-MM-DD HH:MM:SS</code>, IST).</td></tr>
        <tr><td><code>Mode</code></td><td>string</td><td>Punch mode as reported by device (e.g. <code>IN</code>, <code>OUT</code>).</td></tr>
        <tr><td><code>CreatedAt</code></td><td>datetime</td><td>When the record was inserted into the database.</td></tr>
      </tbody>
    </table>
  </div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
