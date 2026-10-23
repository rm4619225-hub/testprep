<?php
// Razorpay से कार्ड/UPI/नेटबैंकिंग — रकम सर्वर तय करता है (क्लाइंट की रकम पर भरोसा नहीं)
require __DIR__ . '/lib.php';
if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') out(['ok' => false, 'error' => 'gateway off (demo mode)'], 400);
$b = body(); $act = $b['action'] ?? '';
if ($act === 'create') {
  $st = jload('settings', []); $price = null;
  foreach (($st['coursePrices'] ?? []) as $p) if ((string)$p['id'] === (string)($b['courseId'] ?? '')) $price = (float)$p['price'];
  if ($price === null || $price <= 0) out(['ok' => false, 'error' => 'course price not synced — admin should connect PHP engine first'], 400);
  $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($b['code'] ?? ''))); $em = strtolower((string)($b['email'] ?? '')); $amt = $price;
  if ($code !== '') {
    $hit = false;
    foreach (($st['coupons'] ?? []) as $cp) if (strtoupper($cp['code'] ?? '') === $code && !empty($cp['active']) && (empty($cp['expiry']) || $cp['expiry'] >= date('Y-m-d'))) { $amt = ($cp['type'] ?? 'percent') === 'percent' ? $price * (1 - (float)$cp['value'] / 100) : $price - (float)$cp['value']; $hit = true; }
    $codes = jload('codes', []); $r = $st['refer'] ?? [];
    if (!$hit && !empty($r['on']) && isset($codes[$code]) && strtolower($codes[$code]) !== $em) $amt = $price * (1 - (float)($r['discount'] ?? 0) / 100);
  }
  $paise = (int)round(max(1, $amt) * 100);
  $ch = curl_init('https://api.razorpay.com/v1/orders');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15,
    CURLOPT_POSTFIELDS => json_encode(['amount' => $paise, 'currency' => 'INR', 'receipt' => 'tp_' . time()])]);
  $res = json_decode((string)curl_exec($ch), true); curl_close($ch);
  if (empty($res['id'])) out(['ok' => false, 'error' => 'gateway order failed'], 502);
  $o = jload('orders', []); $o[$res['id']] = ['amount' => $paise, 'status' => 'created', 'ts' => time()]; jsave('orders', array_slice($o, -2000, null, true));
  out(['ok' => true, 'order_id' => $res['id'], 'amount' => $paise, 'key' => RAZORPAY_KEY_ID]);
}
if ($act === 'verify') {
  $oid = (string)($b['order_id'] ?? ''); $pid = (string)($b['payment_id'] ?? ''); $sig = (string)($b['signature'] ?? ''); $o = jload('orders', []);
  if (!isset($o[$oid]) || !hash_equals(hash_hmac('sha256', $oid . '|' . $pid, RAZORPAY_KEY_SECRET), $sig)) out(['ok' => false, 'error' => 'signature mismatch'], 400);
  $o[$oid]['status'] = 'paid'; $o[$oid]['payment_id'] = $pid; jsave('orders', $o); out(['ok' => true]);
}
out(['ok' => false, 'error' => 'unknown action'], 400);
