<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);

function body() { static $b = null; if ($b === null) { $b = json_decode(file_get_contents('php://input'), true); if (!is_array($b)) $b = []; } return $b; }
function out($d, $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function is_admin() { $t = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''; if (ADMIN_TOKEN === '' || ADMIN_TOKEN === 'CHANGE-THIS-LONG-SECRET') return false; return hash_equals(ADMIN_TOKEN, $t) || valid_session($t); }
function need_admin() { if (!is_admin()) out(['ok' => false, 'error' => 'invalid admin token'], 401); }
function db() {
  static $p = null;
  if (DB_NAME === '') return null;
  if ($p === null) { try { $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    catch (Exception $e) { out(['ok' => false, 'error' => 'MySQL connection failed — config.php जाँचें'], 500); } }
  return $p;
}
function jload($n, $d = []) {
  if ($pdo = db()) { $s = $pdo->prepare('SELECT v FROM tp_kv WHERE k = ?'); $s->execute([$n]); $r = $s->fetchColumn(); $v = $r ? json_decode($r, true) : null; return is_array($v) ? $v : $d; }
  $f = DATA_DIR . '/' . $n . '.json'; if (!is_file($f)) return $d; $v = json_decode(file_get_contents($f), true); return is_array($v) ? $v : $d;
}
function jsave($n, $v) {
  $json = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($pdo = db()) { $pdo->prepare('INSERT INTO tp_kv (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$n, $json]); return true; }
  $f = DATA_DIR . '/' . $n . '.json'; $fp = fopen($f, 'c'); if (!$fp) return false;
  flock($fp, LOCK_EX); ftruncate($fp, 0); fwrite($fp, $json); fflush($fp); flock($fp, LOCK_UN); fclose($fp); return true;
}
// एडमिन सत्र टोकन (OTP लॉगिन के बाद) — ADMIN_TOKEN से हस्ताक्षरित, 8 घंटे मान्य
function make_session() { $e = time() + 8 * 3600; return $e . '.' . hash_hmac('sha256', (string)$e, ADMIN_TOKEN); }
function valid_session($t) { $p = explode('.', (string)$t); return count($p) === 2 && ctype_digit($p[0]) && (int)$p[0] > time() && hash_equals(hash_hmac('sha256', $p[0], ADMIN_TOKEN), $p[1]); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function valid_email($e) { return is_string($e) && strlen($e) < 255 && filter_var($e, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $e); }
function domain_ok($e) { $d = substr(strrchr($e, '@'), 1); return $d !== '' && (checkdnsrr($d, 'MX') || checkdnsrr($d, 'A')); }

function mail_configured() { return SMTP_HOST !== '' || (MAIL_FROM !== 'billing@yoursite.com'); }

function smtp_send($to, $subject, $html) {
  if (!valid_email($to)) return false;
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  if (SMTP_HOST === '') {
    $hd = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    return @mail($to, $subj, $html, $hd);
  }
  $fp = @stream_socket_client((SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT, $en, $es, 20);
  if (!$fp) return false;
  stream_set_timeout($fp, 20);
  $rd = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (substr($l, 3, 1) === ' ') break; } return $r; };
  $wr = function ($c) use ($fp, $rd) { fwrite($fp, $c . "\r\n"); return $rd(); };
  $rd(); $wr('EHLO localhost');
  if (SMTP_SECURE === 'tls') { $wr('STARTTLS'); if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; } $wr('EHLO localhost'); }
  if (SMTP_USER !== '') { $wr('AUTH LOGIN'); $wr(base64_encode(SMTP_USER)); $r = $wr(base64_encode(SMTP_PASS)); if (substr($r, 0, 3) !== '235') { fclose($fp); return false; } }
  $wr('MAIL FROM:<' . MAIL_FROM . '>'); $r = $wr('RCPT TO:<' . $to . '>');
  if ($r === '' || $r[0] !== '2') { fclose($fp); return false; }
  $wr('DATA');
  $msg = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\nTo: <$to>\r\nSubject: $subj\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
  fwrite($fp, $msg . "\r\n.\r\n"); $r = $rd(); $wr('QUIT'); fclose($fp);
  return substr($r, 0, 3) === '250';
}

function invoice_html($inv, $st) {
  $gst = (float)($st['gstRate'] ?? 18); $amt = (float)($inv['amount'] ?? 0);
  $sub = $gst > 0 ? $amt / (1 + $gst / 100) : $amt; $gv = $amt - $sub; $b = $st['bank'] ?? [];
  $disc = !empty($inv['code']) ? '<tr><td>Code ' . h($inv['code']) . '</td><td align="right">-₹' . number_format((float)($inv['discount'] ?? 0), 2) . '</td></tr>' : '';
  return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;padding:20px">'
   . '<h2 style="margin:0;color:#1d4ed8">' . h(SITE_NAME) . ' — Tax Invoice</h2><p style="color:#64748b;margin:4px 0 14px">Invoice #' . h($inv['invoiceNo'] ?? '') . ' • ' . h($inv['date'] ?? '') . '</p>'
   . '<p><b>' . h($inv['name'] ?? '') . '</b><br>' . h($inv['email'] ?? '') . '</p>'
   . '<table width="100%" cellpadding="6" style="border-collapse:collapse;border-top:1px solid #e2e8f0"><tr><td>' . h($inv['courseTitle'] ?? '') . '</td><td align="right">₹' . number_format($sub, 2) . '</td></tr>'
   . '<tr><td>GST (' . $gst . '%)</td><td align="right">₹' . number_format($gv, 2) . '</td></tr>' . $disc
   . '<tr><td><b>Total Paid</b></td><td align="right"><b>₹' . number_format($amt, 2) . '</b></td></tr></table>'
   . '<p style="font-size:12px;color:#64748b">Order: ' . h($inv['orderId'] ?? '') . ' • Beneficiary: ' . h($b['beneficiaryName'] ?? '') . ' • UPI: ' . h($b['upiVpa'] ?? '') . '</p>'
   . '<p style="font-size:12px">आपका टेस्ट पैकेज अनलॉक हो गया है — पोर्टल में लॉगिन करके "Start Exam" दबाएँ। / Your package is unlocked — log in and press "Start Exam".</p></div>';
}

// एक जॉब भेजने की कोशिश (retry के साथ) / try one job with retries
function run_job(&$job, $st) {
  $inv = $job['inv'];
  if (!valid_email($inv['email'] ?? '')) { $job['status'] = 'bad_email'; return; }
  if (!domain_ok($inv['email'])) { $job['status'] = 'bad_domain'; return; }          // bounce/spam guard
  while ($job['attempts'] < MAX_ATTEMPTS) {
    $job['attempts']++;
    if (smtp_send($inv['email'], SITE_NAME . ' Invoice #' . ($inv['invoiceNo'] ?? ''), invoice_html($inv, $st))) { $job['status'] = 'sent'; $job['sentAt'] = date('c'); return; }
    $job['status'] = 'failed'; $job['next'] = time() + 300 * $job['attempts'];
    if ($job['attempts'] < MAX_ATTEMPTS) sleep(1);
  }
}
function log_job($job) {
  $log = jload('logs', []); array_unshift($log, ['invoiceNo' => $job['inv']['invoiceNo'] ?? '', 'email' => $job['inv']['email'] ?? '', 'status' => $job['status'], 'attempts' => $job['attempts'], 'time' => date('Y-m-d H:i:s')]);
  jsave('logs', array_slice($log, 0, 500));
}

// ---- SMS (OTP के लिए) ----
function sms_send($mobile10, $text, $otp = '') {
  if (!preg_match('/^[6-9]\d{9}$/', $mobile10)) return false;
  if (SMS_PROVIDER === 'twilio' && TWILIO_SID !== '') {
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_USERPWD => TWILIO_SID . ':' . TWILIO_TOKEN, CURLOPT_TIMEOUT => 15,
      CURLOPT_POSTFIELDS => http_build_query(['To' => '+91' . $mobile10, 'From' => TWILIO_FROM, 'Body' => $text])]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return $code >= 200 && $code < 300;
  }
  if (FAST2SMS_KEY !== '') {
    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2?' . http_build_query(['authorization' => FAST2SMS_KEY, 'route' => 'otp', 'variables_values' => $otp, 'numbers' => $mobile10]));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $r = json_decode((string)curl_exec($ch), true); curl_close($ch); return !empty($r['return']);
  }
  return false;
}
function admin_mobiles() { return array_values(array_filter(array_map(function ($m) { return substr(preg_replace('/\D/', '', $m), -10); }, explode(',', ADMIN_MOBILES)))); }
