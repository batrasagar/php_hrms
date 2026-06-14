<?php /* Shared form fields for add/edit component modals. $row optional for edit. */ ?>
<div class="mb-3">
  <label class="form-label">Name <span class="text-danger">*</span></label>
  <input type="text" name="Name" class="form-control" value="<?= htmlspecialchars($row['Name'] ?? '') ?>" required>
</div>
<div class="row g-3 mb-3">
  <div class="col-6">
    <label class="form-label">Type</label>
    <select name="Type" class="form-select">
      <option value="earning"   <?= ($row['Type'] ?? 'earning') === 'earning'   ? 'selected' : '' ?>>Earning</option>
      <option value="deduction" <?= ($row['Type'] ?? '') === 'deduction' ? 'selected' : '' ?>>Deduction</option>
    </select>
  </div>
  <div class="col-6">
    <label class="form-label">Calculation</label>
    <select name="CalcType" class="form-select" id="calcType<?= $row['id'] ?? 'new' ?>">
      <option value="fixed"         <?= ($row['CalcType'] ?? 'fixed') === 'fixed'         ? 'selected' : '' ?>>Fixed ₹</option>
      <option value="percent_basic" <?= ($row['CalcType'] ?? '') === 'percent_basic' ? 'selected' : '' ?>>% of Basic</option>
      <option value="percent_gross" <?= ($row['CalcType'] ?? '') === 'percent_gross' ? 'selected' : '' ?>>% of Gross</option>
    </select>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-6">
    <label class="form-label">Default Value</label>
    <div class="input-group">
      <span class="input-group-text" id="prefix<?= $row['id'] ?? 'new' ?>">
        <?= in_array($row['CalcType'] ?? 'fixed', ['percent_basic','percent_gross']) ? '%' : '₹' ?>
      </span>
      <input type="number" name="DefaultValue" class="form-control"
             value="<?= $row['DefaultValue'] ?? 0 ?>" step="0.01" min="0">
    </div>
    <div class="form-text">Company-level default. Can be overridden per employee.</div>
  </div>
  <div class="col-6">
    <label class="form-label">Sort Order</label>
    <input type="number" name="SortOrder" class="form-control" value="<?= $row['SortOrder'] ?? 0 ?>" min="0">
  </div>
</div>
