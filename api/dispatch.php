<?php
// बिल ईमेल + Auto-Dispatcher + Failure Recovery + कूपन/रेफ़रल ट्रैकिंग
require __DIR__ . '/lib.php';
$b = body(); $inv = $b['invoice'] ?? null;
if (!is_array($inv)) out(['ok' => false, 'error' => 'invoice missing'], 400);
$inv = array_intersect_key($inv, array_flip(['invoiceNo', 'orderId', 'name', 'email', 'courseTitle', 'amount', 'date']));
$inv['code'] = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($b['code'] ?? '')); $inv['discount'] = (float)($b['discount'] ?? 0);
$inv['payMode'] = preg_replace('/[^a-z]/', '', (string)($b['payMode'] ?? '')); $inv['status'] = 'paid'; $inv['ts'] = time();
$st = jload('settings', []);
$ivs = jload('invoices', []); $ivs[$inv['invoiceNo'] ?? uniqid()] = $inv; jsave('invoices', array_slice($ivs, -5000, null, true));  // बिल रिकॉर्ड सुरक्षित

// 1) कौन किस कोड/कूपन से आया — ledger + रेफ़र इनाम
if ($inv['code'] !== '') {
  $kind = ($b['kind'] ?? '') === 'referral' ? 'referral' : 'coupon';
  $led = jload('ledger', []);
  foreach ($led as $x) if (($x['inv'] ?? '') === ($inv['invoiceNo'] ?? '#')) out(['ok' => true, 'sent' => false, 'status' => 'duplicate']);
  array_unshift($led, ['ts' => time(), 'email' => $inv['email'], 'course' => $inv['courseTitle'] ?? '', 'amount' => $inv['amount'] ?? 0, 'code' => strtoupper($inv['code']), 'kind' => $kind, 'discount' => $inv['discount'], 'inv' => $inv['invoiceNo'] ?? '']);
  jsave('ledger', array_slice($led, 0, 5000));
  if ($kind === 'referral') {
    $codes = jload('codes', []); $ref = $codes[strtoupper($inv['code'])] ?? null; $rc = $st['refer'] ?? [];
    if ($ref && strtolower($ref) !== strtolower($inv['email'])) {
      $rw = jload('rewards', []);
      $rw[] = ['referrer' => $ref, 'buyer' => $inv['email'], 'months' => (int)($rc['months'] ?? 1), 'extra' => (int)($rc['extra'] ?? 1), 'status' => 'pending', 'ts' => time()];
      jsave('rewards', $rw);
    }
  }
}
// 3) कोर्स एक्सेस रिकॉर्ड (समय पूरा होने पर अपने-आप लॉक; एडमिन तुरंत लॉक कर सकता है)
$days = (int)($st['accessDays'] ?? 0); $acc = jload('access', []);
foreach (array_slice((array)($b['courseIds'] ?? []), 0, 30) as $cid) {
  $cid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$cid); if ($cid === '') continue;
  $acc[strtolower($inv['email']) . '|' . $cid] = ['email' => strtolower($inv['email']), 'name' => $inv['name'] ?? '', 'courseId' => $cid, 'at' => time() * 1000, 'exp' => $days ? (time() + $days * 86400) * 1000 : null, 'locked' => false];
}
jsave('access', $acc);
if (!empty($b['noMail'])) out(['ok' => true, 'sent' => false, 'status' => 'skipped']);

// 2) बिल ईमेल — कतार में डालकर तुरंत भेजें
$job = ['id' => uniqid('j', true), 'inv' => $inv, 'attempts' => 0, 'status' => 'pending', 'next' => 0];
run_job($job, $st); log_job($job);
if ($job['status'] === 'failed') { $q = jload('queue', []); $q[] = $job; jsave('queue', $q); }
out(['ok' => true, 'sent' => $job['status'] === 'sent', 'status' => $job['status'], 'attempts' => $job['attempts']]);
