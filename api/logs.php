<?php
require __DIR__ . '/lib.php'; need_admin();
$l = jload('logs', []); $n = count($l); $ok = 0; foreach ($l as $x) if (($x['status'] ?? '') === 'sent') $ok++;
out(['ok' => true, 'logs' => array_slice($l, 0, 100), 'rate' => $n ? round($ok * 100 / $n) : 100, 'pending' => count(jload('queue', []))]);
