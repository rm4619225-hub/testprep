<?php
// कोर्स एक्सेस: छात्र अपना एक्सेस देखता है (mine); एडमिन तुरंत लॉक/अनलॉक, वैधता बदलना, मुफ़्त एक्सेस देना।
require __DIR__ . '/lib.php';
$b = body(); $act = $b['action'] ?? ''; $acc = jload('access', []);
function ckey($e, $c) { return strtolower($e) . '|' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$c); }
if ($act === 'mine') {
  $em = strtolower((string)($b['email'] ?? '')); if (!valid_email($em)) out(['ok' => true, 'items' => []]);
  $items = []; foreach ($acc as $x) if (($x['email'] ?? '') === $em) $items[] = ['courseId' => $x['courseId'], 'locked' => !empty($x['locked']), 'exp' => $x['exp'] ?? null, 'at' => $x['at'] ?? 0, 'name' => $x['name'] ?? ''];
  out(['ok' => true, 'items' => $items]);
}
need_admin();
if ($act === 'list') out(['ok' => true, 'items' => array_values($acc)]);
$em = strtolower((string)($b['email'] ?? '')); $k = ckey($em, $b['courseId'] ?? '');
if (!valid_email($em) || !preg_match('/^[A-Za-z0-9_-]+$/', (string)($b['courseId'] ?? ''))) out(['ok' => false, 'error' => 'invalid'], 400);
if ($act === 'set') {
  $x = $acc[$k] ?? ['email' => $em, 'courseId' => $b['courseId'], 'at' => time() * 1000, 'name' => ''];
  $x['locked'] = !empty($b['locked']); $x['exp'] = isset($b['exp']) && $b['exp'] ? (float)$b['exp'] : null;
  if ($x['locked']) $x['lockedAt'] = time() * 1000; $acc[$k] = $x; jsave('access', $acc); out(['ok' => true]);
}
if ($act === 'grant') {
  $d = max(0, min(3650, (int)($b['days'] ?? 0)));
  $acc[$k] = ['email' => $em, 'name' => '', 'courseId' => $b['courseId'], 'at' => time() * 1000, 'exp' => $d ? (time() + $d * 86400) * 1000 : null, 'locked' => false]; jsave('access', $acc); out(['ok' => true]);
}
out(['ok' => false, 'error' => 'unknown action'], 400);
