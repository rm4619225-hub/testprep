<?php
// एडमिन के लिए वैकल्पिक OTP लॉगिन: OTP एडमिन के मोबाइल नंबर पर SMS से जाता है
// (10 मिनट मान्य, अधिकतम 5 कोशिश, 60 सेकंड में 1 बार). नंबर config.php की ADMIN_MOBILES सूची में होना ज़रूरी है।
require __DIR__ . '/lib.php';
if (ADMIN_MOBILES === '' || ADMIN_TOKEN === '' || ADMIN_TOKEN === 'CHANGE-THIS-LONG-SECRET') out(['ok' => false, 'error' => 'OTP लॉगिन बंद है — config.php में ADMIN_MOBILES, SMS कुंजी व ADMIN_TOKEN सेट करें'], 400);
$b = body(); $act = $b['action'] ?? ''; $o = jload('admin_otp', []);
$mob = substr(preg_replace('/\D/', '', (string)($b['mobile'] ?? '')), -10);
if ($act === 'send') {
  if (!preg_match('/^[6-9]\d{9}$/', $mob)) out(['ok' => false, 'error' => 'सही मोबाइल नंबर डालें'], 400);
  if (($o['sent'] ?? 0) > time() - 60) out(['ok' => false, 'error' => 'कृपया 1 मिनट बाद फिर कोशिश करें'], 429);
  if (!in_array($mob, admin_mobiles(), true)) { jsave('admin_otp', ['sent' => time()]); out(['ok' => true]); }   // अनधिकृत नंबर: कुछ नहीं भेजा, पर वही जवाब
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $ok = sms_send($mob, SITE_NAME . ' admin OTP: ' . $code . ' (valid 10 min)', $code);
  if (ADMIN_EMAIL !== '' && smtp_send(ADMIN_EMAIL, SITE_NAME . ' Admin OTP', '<h2 style="letter-spacing:4px">' . $code . '</h2><p>10 मिनट में समाप्त।</p>')) $ok = true;
  if (!$ok) out(['ok' => false, 'error' => 'OTP भेजा नहीं जा सका — SMS (Fast2SMS/Twilio) सेटअप जाँचें'], 500);
  jsave('admin_otp', ['hash' => hash_hmac('sha256', $code . '|' . $mob, ADMIN_TOKEN), 'exp' => time() + 600, 'tries' => 0, 'sent' => time()]);
  out(['ok' => true]);
}
if ($act === 'verify') {
  $code = (string)($b['otp'] ?? '');
  if (empty($o['hash']) || ($o['exp'] ?? 0) < time() || ($o['tries'] ?? 0) >= 5) out(['ok' => false, 'error' => 'OTP समाप्त — नया OTP मँगाएँ'], 401);
  $o['tries']++; jsave('admin_otp', $o);
  if (!preg_match('/^\d{6}$/', $code) || !hash_equals($o['hash'], hash_hmac('sha256', $code . '|' . $mob, ADMIN_TOKEN))) out(['ok' => false, 'error' => 'OTP गलत है'], 401);
  jsave('admin_otp', []); out(['ok' => true, 'token' => make_session()]);
}
out(['ok' => false, 'error' => 'unknown action'], 400);
