<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$gestionnaire = new GestionnaireJobs();
$jobs = $gestionnaire->listerJobs();

echo json_encode(['jobs' => $jobs], JSON_UNESCAPED_UNICODE);
