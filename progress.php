<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$jobId = $_GET['job'] ?? '';

if (!GestionnaireJobs::validerJobId($jobId)) {
    http_response_code(400);
    echo json_encode(['erreur' => 'Job ID invalide.']);
    exit;
}

$gestionnaire = new GestionnaireJobs();
$progression = $gestionnaire->lireProgression($jobId);

if ($progression === null) {
    http_response_code(404);
    echo json_encode(['erreur' => 'Job non trouve.']);
    exit;
}

// Lire les logs depuis la position demandee (max 50 par requete)
$logOffset = max(0, (int) ($_GET['log_offset'] ?? 0));
$maxLogs = 50;
$logs = [];
$nouveauOffset = $logOffset;

$cheminLogs = $gestionnaire->cheminJob($jobId) . '/logs.jsonl';
if (file_exists($cheminLogs)) {
    $handle = fopen($cheminLogs, 'r');
    if ($handle !== false) {
        fseek($handle, $logOffset);
        $count = 0;
        while ($count < $maxLogs && ($ligne = fgets($handle)) !== false) {
            $entry = json_decode(trim($ligne), true);
            if ($entry !== null) {
                $logs[] = $entry;
                $count++;
            }
        }
        $nouveauOffset = ftell($handle);
        fclose($handle);
    }
}

$progression['logs'] = $logs;
$progression['logOffset'] = $nouveauOffset;

echo json_encode($progression, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
