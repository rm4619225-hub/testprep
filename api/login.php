<?php
// छात्र लॉगिन: ईमेल या मोबाइल + पासवर्ड (tp_users से जाँच)
require __DIR__ . '/lib.php';
function lfail($m, $c = 401) { out(['success' => false, 'ok' => false, 'message' => $m], $c); }
$r = body(); if (!$r) $r = $_POST;
$pdo = db(); if (!$pdo) lfail('डेटाबेस जुड़ा नहीं है — config.php जाँचें', 500);
$id = strtolower(trim((string)($r['identifier'] ?? $r['email'] ?? ''))); $pass = (string)($r['password'] ?? '');
if ($id === '' || $pass === '' || strlen($pass) > 72) lfail('ईमेल/मोबाइल व पासवर्ड भरें', 400);
$digits = preg_replace('/\D/', '', $id); if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') $digits = substr($digits, 2);
// गलत कोशिशों की सीमा: IP + पहचान पर 10 / 15 मिनट
$rate = jload('login_rate', []); $now = time(); $k = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'x') . '|' . $id);
foreach ($rate as $kk => $v) if (($v[1] ?? 0) < $now) unset($rate[$kk]);
if (($rate[$k][0] ?? 0) >= 10) { jsave('login_rate', $rate); lfail('बहुत अधिक गलत कोशिशें — 15 मिनट बाद फिर करें', 429); }
try {
  $q = $pdo->prepare('SELECT id, student_name, father_name, email, mobile, password_hash FROM tp_users WHERE email = ? OR mobile = ? LIMIT 1');
  $q->execute([$id, preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : '-']);
  $u = $q->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { lfail($e->getCode() === '42S02' ? 'tp_users टेबल नहीं मिली — schema.sql Import करें' : 'डेटाबेस त्रुटि', 500); }
$hash = $u['password_hash'] ?? password_hash('dummy-password', PASSWORD_BCRYPT, ['cost' => 12]);  // समय-समान जाँच
if (!$u || !password_verify($pass, $hash)) {
  $rate[$k] = [($rate[$k][0] ?? 0) + 1, $rate[$k][1] ?? $now + 900]; jsave('login_rate', $rate);
  lfail('ईमेल/मोबाइल या पासवर्ड गलत है');
}
if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
  $pdo->prepare('UPDATE tp_users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $u['id']]);
}
out(['success' => true, 'ok' => true, 'message' => 'लॉगिन सफल', 'user' => ['name' => $u['student_name'], 'father' => $u['father_name'], 'email' => $u['email'], 'mobile' => $u['mobile']]]);
