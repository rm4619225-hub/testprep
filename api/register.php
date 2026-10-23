<?php
// छात्र रजिस्ट्रेशन — tp_users टेबल में सुरक्षित सेव (BCRYPT पासवर्ड, prepared statements, सर्वर-साइड कैप्चा)
// JSON या form-data दोनों चलते हैं.   action = captcha | register
require __DIR__ . '/lib.php';

function req() { $b = body(); return $b ? $b : $_POST; }
function fail($msg, $code = 400, $field = '') { out(['success' => false, 'ok' => false, 'message' => $msg, 'field' => $field], $code); }
function cap_secret() { return hash('sha256', 'captcha|' . ADMIN_TOKEN . '|' . DB_NAME . '|' . DB_USER); }
function cap_new() {
  $a = random_int(1, 9); $b = random_int(1, 9); $exp = time() + 600;
  return ['success' => true, 'ok' => true, 'question' => "$a + $b = ?", 'token' => $exp . '.' . hash_hmac('sha256', $exp . '|' . ($a + $b), cap_secret())];
}
function cap_ok($token, $ans) {                       // हस्ताक्षरित + 10 मिनट वैध + एक बार ही उपयोग
  $p = explode('.', (string)$token); $ans = trim((string)$ans);
  if (count($p) !== 2 || !ctype_digit($p[0]) || (int)$p[0] < time() || !preg_match('/^\d{1,2}$/', $ans)) return false;
  if (!hash_equals(hash_hmac('sha256', $p[0] . '|' . (int)$ans, cap_secret()), $p[1])) return false;
  $used = jload('cap_used', []); $now = time();
  foreach ($used as $k => $e) if ($e < $now) unset($used[$k]);
  $id = hash('sha256', $token); if (isset($used[$id])) return false;
  $used[$id] = (int)$p[0] + 60; jsave('cap_used', $used); return true;
}
function rate_ok($bucket, $key, $max, $window) {      // IP के हिसाब से सीमा
  $r = jload($bucket, []); $now = time(); $k = hash('sha256', $key);
  foreach ($r as $kk => $v) if (($v[1] ?? 0) < $now) unset($r[$kk]);
  $c = $r[$k][0] ?? 0; if ($c >= $max) { jsave($bucket, $r); return false; }
  $r[$k] = [$c + 1, $r[$k][1] ?? $now + $window]; jsave($bucket, $r); return true;
}

$r = req(); $act = $r['action'] ?? ($_GET['action'] ?? 'register');
if ($act === 'captcha') out(cap_new());
if ($act !== 'register') fail('unknown action');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('POST अनुरोध चाहिए', 405);

$pdo = db();
if (!$pdo) fail('डेटाबेस जुड़ा नहीं है — api/config.php में DB_NAME, DB_USER, DB_PASS भरें', 500);
if (!rate_ok('reg_rate', $_SERVER['REMOTE_ADDR'] ?? 'x', 10, 3600)) fail('बहुत अधिक कोशिशें — कुछ देर बाद फिर करें', 429);

$name   = trim((string)($r['student_name'] ?? $r['name'] ?? ''));
$father = trim((string)($r['father_name'] ?? $r['father'] ?? ''));
$email  = strtolower(trim((string)($r['email'] ?? '')));
$mobile = preg_replace('/\D/', '', (string)($r['mobile'] ?? $r['phone'] ?? ''));
if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') $mobile = substr($mobile, 2);
$pass   = (string)($r['password'] ?? '');

if ($name === '' || $father === '' || $email === '' || $mobile === '' || $pass === '') fail('सभी फ़ील्ड भरना ज़रूरी है');
$nameRx = '/^[\p{L}\p{M}][\p{L}\p{M} .\'-]{1,99}$/u';
if (!preg_match($nameRx, $name))   fail('विद्यार्थी का नाम सही लिखें (केवल अक्षर, 2 से 100)', 400, 'student_name');
if (!preg_match($nameRx, $father)) fail('पिता का नाम सही लिखें (केवल अक्षर, 2 से 100)', 400, 'father_name');
if (strlen($email) > 190 || !valid_email($email)) fail('सही ईमेल (Gmail) लिखें', 400, 'email');
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) fail('मोबाइल नंबर 10 अंकों का (6-9 से शुरू) होना चाहिए', 400, 'mobile');
if (strlen($pass) < 6 || strlen($pass) > 72) fail('पासवर्ड 6 से 72 अक्षरों का रखें', 400, 'password');
if (!cap_ok($r['captcha_token'] ?? '', $r['captcha_answer'] ?? '')) fail('कैप्चा का उत्तर गलत या पुराना है — नया कैप्चा लें', 400, 'captcha');

try {
  $q = $pdo->prepare('SELECT email, mobile FROM tp_users WHERE email = ? OR mobile = ? LIMIT 2');
  $q->execute([$email, $mobile]);
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['email'] === $email)  fail('यह ईमेल पहले से रजिस्टर्ड है — लॉगिन करें', 409, 'email');
    if ($row['mobile'] === $mobile) fail('यह मोबाइल नंबर पहले से रजिस्टर्ड है — लॉगिन करें', 409, 'mobile');
  }
  $ins = $pdo->prepare('INSERT INTO tp_users (student_name, father_name, email, mobile, password_hash) VALUES (?, ?, ?, ?, ?)');
  $ins->execute([$name, $father, $email, $mobile, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])]);
} catch (PDOException $e) {
  if ($e->getCode() === '23000') fail('यह ईमेल या मोबाइल पहले से रजिस्टर्ड है', 409);
  if ($e->getCode() === '42S02') fail('tp_users टेबल नहीं मिली — database/schema.sql Import करें', 500);
  fail('डेटाबेस त्रुटि — बाद में कोशिश करें', 500);
}
out(['success' => true, 'ok' => true, 'message' => 'रजिस्ट्रेशन सफल! अब लॉगिन करें।', 'user' => ['name' => $name, 'father' => $father, 'email' => $email, 'mobile' => $mobile]], 201);
