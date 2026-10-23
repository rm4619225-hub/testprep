<?php
// बैंक खाता, GST, कोर्स-प्राइस, Google Client ID, कूपन, रेफ़र ऑफ़र, नोटिस
require __DIR__ . '/lib.php';
if (isset($_GET['ping'])) out(['ok' => true, 'engine' => 'php', 'mail' => mail_configured(), 'gateway' => RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_SECRET !== '', 'otp' => ADMIN_MOBILES !== '', 'mysql' => DB_NAME !== '']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  need_admin(); $b = body(); $s = [];
  $bank = is_array($b['bank'] ?? null) ? $b['bank'] : [];
  foreach (['beneficiaryName', 'bankName', 'bankAcc', 'ifsc', 'upiVpa', 'gstPan'] as $k) $s['bank'][$k] = mb_substr((string)($bank[$k] ?? ''), 0, 120);
  $s['gstRate'] = max(0, min(40, (float)($b['gstRate'] ?? 18))); $s['defaultPrice'] = max(0, (float)($b['defaultPrice'] ?? 49));
  $s['aiAutoEmail'] = !empty($b['aiAutoEmail']);
  $s['coursePrices'] = []; foreach ((array)($b['coursePrices'] ?? []) as $p) if (isset($p['id'])) $s['coursePrices'][] = ['id' => (string)$p['id'], 'price' => max(0, (float)($p['price'] ?? 0))];
  $s['google_client_id'] = preg_match('/^[A-Za-z0-9._-]{10,200}$/', (string)($b['google_client_id'] ?? '')) ? $b['google_client_id'] : '';
  $r = (array)($b['refer'] ?? []); $s['refer'] = ['on' => !empty($r['on']), 'discount' => max(0, min(50, (float)($r['discount'] ?? 5))), 'months' => (int)($r['months'] ?? 1), 'extra' => (int)($r['extra'] ?? 1)];
  $q = (string)($b['qrImage'] ?? ''); $s['qrImage'] = (preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $q) && strlen($q) < 1300000) ? $q : '';
  $s['accessDays'] = max(0, min(3650, (int)($b['accessDays'] ?? 0)));
  $s['coupons'] = array_slice(is_array($b['coupons'] ?? null) ? $b['coupons'] : [], 0, 200);
  $s['notices'] = array_slice(is_array($b['notices'] ?? null) ? $b['notices'] : [], 0, 100);
  if (!jsave('settings', $s)) out(['ok' => false, 'error' => 'data folder not writable'], 500);
  out(['ok' => true]);
}
$s = jload('settings', []);
if (!is_admin() && isset($s['bank']['bankAcc'])) unset($s['bank']['bankAcc'], $s['bank']['gstPan']);  // खाता संख्या सार्वजनिक नहीं
if (!is_admin()) { $s['coupons'] = []; }                                                              // कूपन सूची सार्वजनिक नहीं (validate से जाँचें)
out(['ok' => true, 'settings' => $s ?: null]);
