<?php
// एडमिन: बिल सूची देखना / छात्र का गलत नाम सुधारना (वैकल्पिक: सुधरा बिल दोबारा ईमेल)
require __DIR__ . '/lib.php'; need_admin();
$b = body(); $iv = jload('invoices', []);
if (($b['action'] ?? '') === 'rename') {
  $no = (string)($b['invoiceNo'] ?? ''); $name = trim(strip_tags((string)($b['name'] ?? '')));
  if ($no === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) out(['ok' => false, 'error' => 'invalid name'], 400);
  if (!isset($iv[$no])) { $iv[$no] = ['invoiceNo' => $no]; }
  $iv[$no]['name'] = $name; jsave('invoices', $iv);
  $sent = false;
  if (!empty($b['resend']) && valid_email($iv[$no]['email'] ?? '')) { $job = ['inv' => $iv[$no], 'attempts' => 0, 'status' => 'pending', 'next' => 0]; run_job($job, jload('settings', [])); log_job($job); $sent = $job['status'] === 'sent'; }
  out(['ok' => true, 'resent' => $sent]);
}
out(['ok' => true, 'invoices' => array_values($iv)]);
