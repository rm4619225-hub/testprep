<?php
// Google ID-token की सर्वर-साइड जाँच (Gmail नाम/ईमेल/फ़ोटो)
require __DIR__ . '/lib.php';
$cred = (string)(body()['credential'] ?? ''); $cid = jload('settings', [])['google_client_id'] ?? '';
if ($cred === '' || $cid === '') out(['ok' => false, 'error' => 'not configured'], 400);
$ctx = stream_context_create(['http' => ['timeout' => 8]]);
$r = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred), false, $ctx);
$d = $r ? json_decode($r, true) : null;
if (!is_array($d) || ($d['aud'] ?? '') !== $cid || ($d['email_verified'] ?? '') !== 'true') out(['ok' => false, 'error' => 'invalid token'], 401);
out(['ok' => true, 'email' => $d['email'], 'name' => $d['name'] ?? '', 'picture' => $d['picture'] ?? '']);
