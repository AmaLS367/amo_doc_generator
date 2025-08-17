<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
$out = ['template'=>'order','discount'=>0,'products'=>[], 'saved_at'=>0];

if ($leadId > 0) {
    $file = __DIR__ . '/../data/cache/' . $leadId . '.json';
    if (is_file($file)) {
        $j = json_decode(@file_get_contents($file), true);
        if (is_array($j)) $out = $j;
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
