<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

$jobId = $_GET['job'] ?? '';
$format = $_GET['format'] ?? 'csv';
$type = $_GET['type'] ?? 'corrige';

if (!GestionnaireJobs::validerJobId($jobId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'erreur' => 'Job ID invalide.',
        'erreur_fr' => 'Job ID invalide.',
        'erreur_en' => 'Invalid job ID.',
    ]);
    exit;
}

$gestionnaire = new GestionnaireJobs();

if (!is_dir($gestionnaire->cheminJob($jobId))) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'erreur' => 'Job introuvable.',
        'erreur_fr' => 'Job introuvable.',
        'erreur_en' => 'Job not found.',
    ]);
    exit;
}

$analyse = $gestionnaire->lireAnalyse($jobId);
$resultats = $gestionnaire->lireResultats($jobId) ?? [];

if ($analyse === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'erreur' => 'Analyse introuvable.',
        'erreur_fr' => 'Analyse introuvable.',
        'erreur_en' => 'Analysis not found.',
    ]);
    exit;
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'analyse' => $analyse,
        'verificationsHttp' => $resultats['verificationsHttp'] ?? [],
        'mappingDestinations' => $resultats['mappingDestinations'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Export CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="redirects-' . $type . '-' . $jobId . '.csv"');
header('X-Content-Type-Options: nosniff');

$sortie = fopen('php://output', 'w');
fprintf($sortie, "\xEF\xBB\xBF"); // BOM UTF-8

if ($type === 'problemes') {
    fputcsv($sortie, ['Type', 'Source', 'Destination actuelle', 'Detail', 'Correction suggeree'], ';');

    foreach ($analyse['chaines'] as $chaine) {
        fputcsv($sortie, [
            'Chaine',
            $chaine['correction']['source'],
            $chaine['chaine'][1] ?? '',
            implode(' -> ', $chaine['chaine']),
            $chaine['correction']['destination'],
        ], ';');
    }

    foreach ($analyse['boucles'] as $boucle) {
        fputcsv($sortie, [
            'Boucle',
            $boucle['boucle'][0],
            $boucle['boucle'][1] ?? '',
            implode(' -> ', $boucle['boucle']),
            '',
        ], ';');
    }

    foreach ($analyse['autoRedirections'] as $url) {
        fputcsv($sortie, ['Auto-redirection', $url, $url, 'Source = Destination', ''], ';');
    }

    $verificationsHttp = $resultats['verificationsHttp'] ?? [];
    foreach ($verificationsHttp as $url => $verif) {
        if (($verif['statut'] ?? 0) === 404) {
            $sourcePaire = '';
            foreach ($analyse['paires'] as $paire) {
                if ($paire['destination'] === $url) {
                    $sourcePaire = $paire['source'];
                    break;
                }
            }
            fputcsv($sortie, ['404', $sourcePaire, $url, 'HTTP 404', ''], ';');
        }
    }
} else {
    // Export corrige : liste aplatie
    fputcsv($sortie, ['Source', 'Destination corrigee', 'Statut'], ';');

    $aplati = $analyse['aplati'] ?? [];
    $verificationsHttp = $resultats['verificationsHttp'] ?? [];

    foreach ($analyse['paires'] as $paire) {
        $source = $paire['source'];
        $destinationCorrigee = $aplati[$source]['destination'] ?? $paire['destination'];
        $estBoucle = $aplati[$source]['estBoucle'] ?? false;

        $statut = 'OK';
        if ($estBoucle) {
            $statut = 'Boucle';
        } elseif ($source === $paire['destination']) {
            $statut = 'Auto-redirection';
        } elseif ($destinationCorrigee !== $paire['destination']) {
            $statut = 'Chaine aplatie';
        }

        $statutHttp = $verificationsHttp[$destinationCorrigee]['statut'] ?? null;
        if ($statutHttp === 404) {
            $statut = '404';
        }

        fputcsv($sortie, [$source, $destinationCorrigee, $statut], ';');
    }
}

fclose($sortie);
