<?php
// कोड रजिस्टर / जाँच (validate) / लेजर (कौन किस कोड से आया)
require __DIR__ . '/lib.php';
$b = body(); $act = $b['action'] ?? ($_GET['action'] ?? '');
$code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($b['code'] ?? '')));
if ($act === 'register') {
  $em = (string)($b['email'] ?? ''); if (!valid_email($em) || $code === '') out(['ok' => false], 400);
  $c = jload('codes', []); if (!isset($c[$code])) { $c[$code] = $em; jsave('codes', $c); }
  out(['ok' => true]);
}
if ($act === 'validate') {
  $st = jload('settings', []); $em = strtolower((string)($b['email'] ?? ''));
  foreach (($st['coupons'] ?? []) as $cp) if (strtoupper($cp['code'] ?? '') === $code && !empty($cp['active']) && (empty($cp['expiry']) || $cp['expiry'] >= date('Y-m-d')))
    out(['ok' => true, 'kind' => 'coupon', 'type' => $cp['type'] ?? 'percent', 'value' => $cp['value'] ?? 0]);
  $r = $st['refer'] ?? ['on' => true, 'discount' => 5]; $c = jload('codes', []);
  if (!empty($r['on']) && isset($c[$code]) && strtolower($c[$code]) !== $em) out(['ok' => true, 'kind' => 'referral', 'type' => 'percent', 'value' => $r['discount']]);
  out(['ok' => false]);
}
if ($act === 'ledger') { need_admin(); out(['ok' => true, 'ledger' => jload('ledger', []), 'rewards' => jload('rewards', [])]); }
out(['ok' => false, 'error' => 'unknown action'], 400);
