<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Repertoire de stockage des configs
$dossierConfigs = __DIR__ . '/data/configs';
if (!is_dir($dossierConfigs)) {
    mkdir($dossierConfigs, 0755, true);
}

// En mode plateforme, isoler par utilisateur
$prefixe = 'default';
if (defined('PLATFORM_EMBEDDED') && !empty($_SESSION['user_id'])) {
    $prefixe = 'user_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_SESSION['user_id']);
}

$fichierConfigs = $dossierConfigs . '/' . $prefixe . '.json';

function lireConfigs(string $fichier): array
{
    if (!file_exists($fichier)) {
        return [];
    }
    $contenu = file_get_contents($fichier);
    if ($contenu === false) {
        return [];
    }
    $configs = json_decode($contenu, true);
    return is_array($configs) ? $configs : [];
}

function ecrireConfigs(string $fichier, array $configs): void
{
    file_put_contents($fichier, json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

$methode = $_SERVER['REQUEST_METHOD'];

// GET : lister les configs
if ($methode === 'GET') {
    echo json_encode(lireConfigs($fichierConfigs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST : sauvegarder une config
if ($methode === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nom = trim($input['nom'] ?? '');
    $valeurs = $input['valeurs'] ?? [];

    if ($nom === '' || strlen($nom) > 100) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Nom de config invalide.']);
        exit;
    }

    if (!is_array($valeurs)) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Valeurs invalides.']);
        exit;
    }

    $configs = lireConfigs($fichierConfigs);

    // Remplacer si meme nom, sinon ajouter (max 50 configs)
    $configs = array_values(array_filter($configs, fn(array $c): bool => ($c['nom'] ?? '') !== $nom));

    if (count($configs) >= 50) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Maximum 50 configs sauvegardees.']);
        exit;
    }

    $configs[] = ['nom' => $nom, 'valeurs' => $valeurs];
    ecrireConfigs($fichierConfigs, $configs);

    echo json_encode(['ok' => true]);
    exit;
}

// DELETE : supprimer une config
if ($methode === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nom = trim($input['nom'] ?? '');

    if ($nom === '') {
        http_response_code(400);
        echo json_encode(['erreur' => 'Nom requis.']);
        exit;
    }

    $configs = lireConfigs($fichierConfigs);
    $configs = array_values(array_filter($configs, fn(array $c): bool => ($c['nom'] ?? '') !== $nom));
    ecrireConfigs($fichierConfigs, $configs);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['erreur' => 'Methode non autorisee.']);
