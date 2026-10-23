<?php
// Failure Recovery: cron से हर 5 मिनट चलाएँ / run by cron every 5 minutes:
//   */5 * * * * php /path/to/api/worker.php
require __DIR__ . '/lib.php';
if (PHP_SAPI !== 'cli') need_admin();
$st = jload('settings', []); $q = jload('queue', []); $keep = []; $sent = 0;
foreach ($q as $job) {
  if (($job['next'] ?? 0) > time()) { $keep[] = $job; continue; }
  $job['attempts'] = min($job['attempts'], MAX_ATTEMPTS - 1);         // हर worker-run में 1 और कोशिश
  run_job($job, $st); log_job($job);
  if ($job['status'] === 'sent') $sent++; elseif ($job['status'] === 'failed' && ($job['tries'] = ($job['tries'] ?? 0) + 1) < 12) $keep[] = $job;
}
jsave('queue', $keep);
out(['ok' => true, 'sent' => $sent, 'pending' => count($keep)]);
