<?php
// एडमिन: पंजीकृत छात्रों की सूची (पासवर्ड hash कभी नहीं भेजा जाता)
require __DIR__ . '/lib.php';
need_admin();
$pdo = db(); if (!$pdo) out(['ok' => false, 'error' => 'MySQL जुड़ा नहीं'], 500);
try {
  $rows = $pdo->query('SELECT id, student_name, father_name, email, mobile, created_at FROM tp_users ORDER BY id DESC LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { out(['ok' => false, 'error' => 'tp_users टेबल नहीं मिली — schema.sql Import करें'], 500); }
out(['ok' => true, 'count' => count($rows), 'items' => $rows]);
