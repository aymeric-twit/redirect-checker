<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

set_time_limit(120);
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Log des erreurs fatales en JSON
register_shutdown_function(function (): void {
    $erreur = error_get_last();
    if ($erreur !== null && in_array($erreur['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'erreur' => 'Erreur serveur : ' . $erreur['message'],
            'fichier' => basename($erreur['file']) . ':' . $erreur['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erreur' => 'Methode non autorisee.']);
    exit;
}

// Quota plateforme
if (class_exists('\\Platform\\Module\\Quota')) {
    $slug = 'redirects-checker';
    if (!\Platform\Module\Quota::trackerSiDisponible($slug)) {
        http_response_code(429);
        echo json_encode(['erreur' => 'Quota mensuel epuise.']);
        exit;
    }
}

$separateur = $_POST['separateur'] ?? 'auto';
$verifier404 = !empty($_POST['verifier_404']);
$domaine = rtrim(trim($_POST['domaine'] ?? ''), '/');
$concurrence = max(1, min(50, (int) ($_POST['concurrence'] ?? 2)));
$delaiMs = max(0, min(5000, (int) ($_POST['delai_ms'] ?? 100)));
$timeout = max(1, min(30, (int) ($_POST['timeout'] ?? 5)));
$maxRedirections = max(0, min(20, (int) ($_POST['max_redirections'] ?? 10)));
$userAgent = trim($_POST['user_agent'] ?? 'SalomonBotSEO/1.0');
$customHeaderNom = trim($_POST['custom_header_nom'] ?? '');
$customHeaderValeur = trim($_POST['custom_header_valeur'] ?? '');

// Valider le nom du header (caracteres autorises par RFC 7230)
if ($customHeaderNom !== '' && !preg_match('/^[A-Za-z0-9\-]+$/', $customHeaderNom)) {
    http_response_code(400);
    echo json_encode(['erreur' => 'Le nom du header ne doit contenir que des lettres, chiffres et tirets.']);
    exit;
}

// Valider le domaine s'il est fourni
if ($domaine !== '' && !preg_match('#^https?://.+#', $domaine)) {
    http_response_code(400);
    echo json_encode(['erreur' => 'Le domaine doit commencer par http:// ou https://']);
    exit;
}

$contenu = '';

// Source 1 : fichier uploade
if (!empty($_FILES['fichier_csv']) && $_FILES['fichier_csv']['error'] === UPLOAD_ERR_OK) {
    $fichier = $_FILES['fichier_csv'];
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $extensionsAutorisees = ['csv', 'tsv', 'txt'];

    if (!in_array($ext, $extensionsAutorisees, true)) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Format de fichier non supporte. Utilisez CSV, TSV ou TXT.']);
        exit;
    }

    $tailleMax = 2 * 1024 * 1024;
    if ($fichier['size'] > $tailleMax) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Le fichier ne doit pas depasser 2 Mo.']);
        exit;
    }

    $contenu = file_get_contents($fichier['tmp_name']);
    if ($contenu === false) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Impossible de lire le fichier.']);
        exit;
    }
}

// Source 2 : textarea
if ($contenu === '' && !empty($_POST['contenu'])) {
    $contenu = $_POST['contenu'];
}

if (trim($contenu) === '') {
    http_response_code(400);
    echo json_encode(['erreur' => 'Aucune donnee fournie. Collez du texte ou importez un fichier.']);
    exit;
}

// Parsing
$analyseur = new AnalyseurRedirections();
$resultatParsing = $analyseur->analyser($contenu, $separateur);

if (empty($resultatParsing['paires'])) {
    http_response_code(400);
    echo json_encode([
        'erreur' => 'Aucune redirection valide detectee.',
        'avertissements' => $resultatParsing['avertissements'],
    ]);
    exit;
}

// Verifier si des URLs relatives sont presentes sans domaine quand la verif HTTP est activee
if ($verifier404 && $domaine === '') {
    $aDesUrlsRelatives = false;
    foreach ($resultatParsing['paires'] as $paire) {
        if (!str_starts_with($paire['source'], 'http://') && !str_starts_with($paire['source'], 'https://')
            || !str_starts_with($paire['destination'], 'http://') && !str_starts_with($paire['destination'], 'https://')) {
            $aDesUrlsRelatives = true;
            break;
        }
    }
    if ($aDesUrlsRelatives) {
        http_response_code(400);
        echo json_encode([
            'erreur' => 'Le domaine est requis pour verifier les URLs relatives (ex: https://example.com). Renseignez le champ "Domaine" ou desactivez la verification HTTP.',
        ]);
        exit;
    }
}

// Analyse du graphe
$graphe = new GrapheRedirections($resultatParsing['paires']);
$chaines = $graphe->detecterChaines();
$boucles = $graphe->detecterBoucles();
$autoRedirections = $graphe->detecterAutoRedirections();
$aplati = $graphe->aplatirChaines();
$resume = $graphe->genererResume();

$analyse = [
    'paires' => $resultatParsing['paires'],
    'chaines' => $chaines,
    'boucles' => $boucles,
    'autoRedirections' => $autoRedirections,
    'aplati' => $aplati,
    'resume' => $resume,
    'avertissements' => $resultatParsing['avertissements'],
];

// Creer un job pour sauvegarder les resultats
$gestionnaire = new GestionnaireJobs();
$jobId = $gestionnaire->creerJob();

$gestionnaire->sauvegarderAnalyse($jobId, $analyse);
$gestionnaire->sauvegarderConfig($jobId, [
    'separateur' => $separateur,
    'verifier_404' => $verifier404,
    'domaine' => $domaine,
    'concurrence' => $concurrence,
    'delai_ms' => $delaiMs,
    'timeout' => $timeout,
    'max_redirections' => $maxRedirections,
    'user_agent' => $userAgent,
    'custom_header_nom' => $customHeaderNom,
    'custom_header_valeur' => $customHeaderValeur,
    'total_paires' => count($resultatParsing['paires']),
    'date_creation' => date('Y-m-d H:i:s'),
]);

// Lancer le worker si verification 404 demandee
if ($verifier404) {
    $gestionnaire->ecrireProgression($jobId, [
        'status' => 'starting',
        'phase' => 'verification_http',
        'progress' => 0,
        'verifiees' => 0,
        'total' => 0,
    ]);

    $cmd = sprintf(
        'php %s --job=%s >> %s 2>&1 &',
        escapeshellarg(__DIR__ . '/worker.php'),
        escapeshellarg($jobId),
        escapeshellarg(__DIR__ . '/data/jobs/' . $jobId . '/worker.log')
    );
    exec($cmd);
} else {
    $gestionnaire->ecrireProgression($jobId, [
        'status' => 'done',
        'phase' => 'termine',
        'progress' => 100,
        'verifiees' => 0,
        'total' => 0,
    ]);
    $gestionnaire->sauvegarderResultats($jobId, ['verificationsHttp' => []]);
}

echo json_encode([
    'jobId' => $jobId,
    'analyse' => $analyse,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
