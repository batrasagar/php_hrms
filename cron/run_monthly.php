<?php
/**
 * Wrapper called by Task Scheduler on 1st of each month.
 * Automatically targets the previous month's YYMM.
 * Usage: php cron/run_monthly.php [step]
 *   step: monthly (default) | payroll | all
 */
$step = $argv[1] ?? 'monthly';

$ts   = mktime(0, 0, 0, (int)date('n') - 1, 1, (int)date('Y')); // 1st of last month
$ym   = date('ym', $ts); // e.g. 2606

passthru(PHP_BINARY . ' ' . __DIR__ . '/process_pipeline.php ' . escapeshellarg($step) . ' 0 ' . $ym);
