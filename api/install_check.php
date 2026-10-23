<?php
// एक बार खोलकर जाँचें: http://yoursite.com/api/install_check.php  (काम होने पर इस फ़ाइल को हटा दें)
require __DIR__ . '/lib.php'; need_admin();
$r = ['php' => PHP_VERSION, 'data_writable' => is_writable(DATA_DIR), 'mail_configured' => mail_configured(), 'mysql' => DB_NAME !== '', 'otp' => ADMIN_MOBILES !== '', 'gateway' => RAZORPAY_KEY_ID !== '', 'curl' => function_exists('curl_init')];
if (DB_NAME !== '') { try { db()->query('SELECT 1 FROM tp_kv LIMIT 1'); $r['mysql_table'] = true; } catch (Exception $e) { $r['mysql_table'] = false; } }
out(['ok' => true, 'checks' => $r]);
