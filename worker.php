<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

const TAILLE_BATCH = 5000;

$options = getopt('', ['job:']);
$jobId = $options['job'] ?? null;

if ($jobId === null || !GestionnaireJobs::validerJobId($jobId)) {
    fwrite(STDERR, "Job ID invalide.\n");
    exit(1);
}

$gestionnaire = new GestionnaireJobs();
$cheminJob = $gestionnaire->cheminJob($jobId);

if (!is_dir($cheminJob)) {
    fwrite(STDERR, "Repertoire du job introuvable.\n");
    exit(1);
}

// Gestion des erreurs fatales
register_shutdown_function(function () use ($gestionnaire, $jobId): void {
    $erreur = error_get_last();
    if ($erreur !== null && in_array($erreur['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $gestionnaire->ecrireProgression($jobId, [
            'status' => 'error',
            'message' => 'Erreur fatale : ' . $erreur['message'],
            'message_fr' => 'Erreur fatale : ' . $erreur['message'],
            'message_en' => 'Fatal error: ' . $erreur['message'],
            'progress' => 0,
        ]);
    }
});

$analyse = $gestionnaire->lireAnalyse($jobId);
if ($analyse === null) {
    $gestionnaire->ecrireProgression($jobId, [
        'status' => 'error',
        'message' => 'Impossible de charger l\'analyse.',
        'message_fr' => 'Impossible de charger l\'analyse.',
        'message_en' => 'Unable to load analysis.',
        'progress' => 0,
    ]);
    exit(1);
}

// Lire la configuration du job
$config = json_decode(file_get_contents($cheminJob . '/config.json'), true) ?? [];
$domaine = $config['domaine'] ?? '';
$concurrence = $config['concurrence'] ?? 10;
$delaiMs = $config['delai_ms'] ?? 0;
$timeout = $config['timeout'] ?? 5;
$maxRedirections = $config['max_redirections'] ?? 10;
$userAgent = $config['user_agent'] ?? 'SalomonBotSEO/1.0';
$customHeaderNom = $config['custom_header_nom'] ?? '';
$customHeaderValeur = $config['custom_header_valeur'] ?? '';
$headersSupplementaires = [];
if ($customHeaderNom !== '') {
    $headersSupplementaires[$customHeaderNom] = $customHeaderValeur;
}

// Prefixer le domaine sur les URLs relatives si un domaine est fourni
$pairesOrigine = $analyse['paires'];
$paires = $pairesOrigine;
if ($domaine !== '') {
    $analyseur = new AnalyseurRedirections();
    $paires = $analyseur->prefixerDomaine($pairesOrigine, $domaine);
}

// Extraire les URLs source uniques a crawler (prefixees)
$urlsSourceUniques = [];
$mappingSourcePrefixee = []; // URL prefixee -> URL originale
foreach ($paires as $i => $paire) {
    $src = $paire['source'];
    if ((str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) && !isset($urlsSourceUniques[$src])) {
        $urlsSourceUniques[$src] = true;
        $mappingSourcePrefixee[$src] = $pairesOrigine[$i]['source'];
    }
}

// Mapping destinations : URL originale -> URL prefixee (pour comparaison JS)
$mappingDestinations = [];
foreach ($pairesOrigine as $i => $paireOrigine) {
    $destOrigine = $paireOrigine['destination'];
    $destPrefixee = $paires[$i]['destination'];
    if ($destOrigine !== $destPrefixee) {
        $mappingDestinations[$destOrigine] = $destPrefixee;
    }
}

// Liberer la memoire des paires (plus besoin)
unset($analyse['paires'], $pairesOrigine, $paires);

$urlsAVerifier = array_keys($urlsSourceUniques);
unset($urlsSourceUniques);
$total = count($urlsAVerifier);

if ($total === 0) {
    $gestionnaire->ecrireProgression($jobId, [
        'status' => 'done',
        'phase' => 'termine',
        'progress' => 100,
        'verifiees' => 0,
        'total' => 0,
        'message' => 'Aucune URL absolue a verifier.',
        'message_fr' => 'Aucune URL absolue a verifier.',
        'message_en' => 'No absolute URL to check.',
    ]);
    $gestionnaire->sauvegarderResultats($jobId, ['verificationsHttp' => []]);
    exit(0);
}

$gestionnaire->ecrireProgression($jobId, [
    'status' => 'running',
    'phase' => 'verification_http',
    'progress' => 0,
    'verifiees' => 0,
    'total' => $total,
    'pid' => getmypid(),
]);

// Fichier de logs temps reel (JSON lines)
$cheminLogs = $cheminJob . '/logs.jsonl';
file_put_contents($cheminLogs, '');

// Fichier de resultats incrementaux (JSON lines, un resultat par ligne)
$cheminResultats = $cheminJob . '/resultats_incremental.jsonl';
file_put_contents($cheminResultats, '');

// Traitement par batch pour limiter la memoire
$batches = array_chunk($urlsAVerifier, TAILLE_BATCH);
unset($urlsAVerifier);

$verifieesGlobal = 0;

foreach ($batches as $numeroBatch => $batchUrls) {
    $verificateur = new VerificateurHttp($concurrence, $timeout, $userAgent, $maxRedirections, $delaiMs, $headersSupplementaires);

    // Callback logs temps reel + ecriture incrementale des resultats
    $verificateur->setCallbackResultat(function (string $urlCrawlee, array $resultat) use ($cheminLogs, $cheminResultats, $mappingSourcePrefixee): void {
        $sourceOriginale = $mappingSourcePrefixee[$urlCrawlee] ?? $urlCrawlee;
        $logEntry = [
            'source' => $sourceOriginale,
            'urlCrawlee' => $urlCrawlee,
            'statut' => $resultat['statut'],
            'urlFinale' => $resultat['urlFinale'],
            'erreur' => $resultat['erreur'],
            'chaineRedirections' => $resultat['chaineRedirections'] ?? [],
        ];
        $ligne = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($cheminLogs, $ligne, FILE_APPEND | LOCK_EX);

        // Ecrire le resultat complet pour reconstruction finale
        $resultatLigne = [
            'source' => $sourceOriginale,
            'resultat' => $resultat,
        ];
        file_put_contents($cheminResultats, json_encode($resultatLigne, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    });

    $verificateur->setCallbackProgression(function (int $verifieesBatch, int $totalBatch) use ($gestionnaire, $jobId, &$verifieesGlobal, $total, $numeroBatch): void {
        static $derniereMaj = 0;
        $maintenant = time();
        if ($maintenant - $derniereMaj < 2 && ($verifieesGlobal + $verifieesBatch) < $total) {
            return;
        }
        $derniereMaj = $maintenant;

        $verifieesTotal = $verifieesGlobal + $verifieesBatch;
        $progression = $total > 0 ? (int) round(($verifieesTotal / $total) * 100) : 0;
        $gestionnaire->ecrireProgression($jobId, [
            'status' => 'running',
            'phase' => 'verification_http',
            'progress' => min($progression, 99),
            'verifiees' => $verifieesTotal,
            'total' => $total,
            'pid' => getmypid(),
        ]);
    });

    // Crawler le batch
    $resultatsBatch = $verificateur->verifier($batchUrls);
    $verifieesGlobal += count($batchUrls);

    // Liberer la memoire du batch
    unset($resultatsBatch, $verificateur, $batchUrls);
    gc_collect_cycles();

    fwrite(STDOUT, "Batch " . ($numeroBatch + 1) . "/" . count($batches) . " termine ($verifieesGlobal/$total URLs).\n");
}

// Reconstruction du fichier resultats.json depuis le fichier incremental (ecriture streaming)
$cheminResultatsFinal = $cheminJob . '/resultats.json';
$handleOut = fopen($cheminResultatsFinal, 'w');
fwrite($handleOut, '{"verificationsHttp":{');

$handleIn = fopen($cheminResultats, 'r');
$premier = true;
if ($handleIn !== false) {
    while (($ligne = fgets($handleIn)) !== false) {
        $entry = json_decode($ligne, true);
        if ($entry !== null && isset($entry['source'], $entry['resultat'])) {
            if (!$premier) {
                fwrite($handleOut, ',');
            }
            $premier = false;
            fwrite($handleOut, json_encode($entry['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fwrite($handleOut, ':');
            fwrite($handleOut, json_encode($entry['resultat'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
    fclose($handleIn);
}

fwrite($handleOut, '},"mappingDestinations":');
fwrite($handleOut, json_encode($mappingDestinations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
fwrite($handleOut, '}');
fclose($handleOut);

// Nettoyer le fichier incremental
unlink($cheminResultats);

$gestionnaire->ecrireProgression($jobId, [
    'status' => 'done',
    'phase' => 'termine',
    'progress' => 100,
    'verifiees' => $total,
    'total' => $total,
    'pid' => getmypid(),
]);

fwrite(STDOUT, "Verification terminee : $total URLs verifiees.\n");
