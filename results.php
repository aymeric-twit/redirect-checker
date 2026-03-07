<?php

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

$jobId = $_GET['job'] ?? '';

if (!GestionnaireJobs::validerJobId($jobId)) {
    if (defined('PLATFORM_EMBEDDED')) {
        echo '<div class="alert alert-danger m-4">Job ID invalide.</div>';
        return;
    }
    header('Location: index.php');
    exit;
}

$gestionnaire = new GestionnaireJobs();

if (!is_dir($gestionnaire->cheminJob($jobId))) {
    if (defined('PLATFORM_EMBEDDED')) {
        echo '<div class="alert alert-danger m-4">Job introuvable ou expire.</div>';
        return;
    }
    header('Location: index.php');
    exit;
}

$analyse = $gestionnaire->lireAnalyse($jobId);
$config = json_decode(file_get_contents($gestionnaire->cheminJob($jobId) . '/config.json'), true) ?? [];
$progression = $gestionnaire->lireProgression($jobId);
$resultatsHttp = $gestionnaire->lireResultats($jobId);

$estTermine = ($progression['status'] ?? '') === 'done';
$verifier404 = !empty($config['verifier_404']);

// Offset actuel du fichier de logs (pour reprendre le streaming apres un refresh)
$cheminLogs = $gestionnaire->cheminJob($jobId) . '/logs.jsonl';
$logOffsetInitial = file_exists($cheminLogs) ? filesize($cheminLogs) : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultats - Redirects Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>

<nav class="navbar mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            <i class="bi bi-signpost-split"></i> Redirects Checker
            <span class="d-block d-sm-inline ms-sm-2 text-light-muted">Resultats de l'analyse</span>
        </span>
    </div>
</nav>

<div class="container py-3">

    <!-- Lien retour -->
    <a href="index.php" class="btn btn-outline-primary btn-sm mb-3" id="lienRetour">
        <i class="bi bi-arrow-left me-1"></i>Nouvelle analyse
    </a>

    <!-- Section progression (visible si verification 404 en cours) -->
    <div id="progressSection" class="card shadow-sm mb-4 <?= ($verifier404 && !$estTermine) ? '' : 'd-none' ?>">
        <div class="card-body">
<?php
    $pctInitial = $progression['progress'] ?? 0;
    $verifiees = $progression['verifiees'] ?? 0;
    $totalUrls = $progression['total'] ?? 0;
    $texteProgression = $verifiees > 0
        ? "$verifiees / $totalUrls URLs verifiees"
        : 'Demarrage...';
?>
            <h6 class="fw-bold mb-3"><i class="bi bi-hourglass-split me-2"></i>Verification HTTP en cours...</h6>
            <div class="progress mb-2" style="height: 24px;">
                <div id="progressBar"
                     class="progress-bar progress-bar-teal progress-bar-striped progress-bar-animated"
                     role="progressbar"
                     style="width: <?= $pctInitial ?>%">
                    <span id="progressPct" class="fw-bold"><?= $pctInitial > 0 ? $pctInitial . '%' : '' ?></span>
                </div>
            </div>
            <small id="progressText" class="text-muted"><?= htmlspecialchars($texteProgression) ?></small>
            <textarea class="streaming-log form-control mt-3" id="logsPanel" rows="8" readonly></textarea>
        </div>
    </div>

    <!-- Resume KPI -->
    <div class="row g-3 mb-4" id="resumeSection">
        <div class="col-6 col-lg">
            <div class="kpi-card">
                <div class="kpi-label">Redirections 3xx</div>
                <div class="kpi-value" id="kpiTotal">-</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi-card" id="kpiChainesCard">
                <div class="kpi-label">Chaines</div>
                <div class="kpi-value" id="kpiChaines">-</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi-card" id="kpiBouclesCard">
                <div class="kpi-label">Boucles</div>
                <div class="kpi-value" id="kpiBoucles">-</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi-card" id="kpi404Card">
                <div class="kpi-label">Erreurs HTTP</div>
                <div class="kpi-value" id="kpi404">-</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi-card" id="kpiCorrectionsCard">
                <div class="kpi-label">Corrections</div>
                <div class="kpi-value" id="kpiCorrections">0</div>
            </div>
        </div>
    </div>

    <!-- Onglets resultats -->
    <ul class="nav nav-tabs mb-0" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-problemes" type="button" role="tab">
                <i class="bi bi-exclamation-diamond me-1"></i>Problemes <span class="badge bg-danger ms-1" id="badgeProblemes">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-liste" type="button" role="tab">
                <i class="bi bi-list-ul me-1"></i>Liste complete
            </button>
        </li>
        <li class="nav-item d-none" role="presentation" id="tabAvertissementsItem">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-avertissements" type="button" role="tab">
                <i class="bi bi-exclamation-triangle me-1"></i>Avertissements <span class="badge bg-warning text-dark ms-1" id="badgeAvertissements">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Tab Problemes -->
        <div class="tab-pane fade show active" id="tab-problemes" role="tabpanel">
            <div class="card shadow-sm border-top-0 rounded-top-0">
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center" id="filtresProblemes">
                        <button class="btn btn-sm btn-outline-secondary active" data-filtre="tous">Tous</button>
                        <button class="btn btn-sm btn-outline-warning" data-filtre="chaine"><i class="bi bi-link-45deg me-1"></i>Chaines</button>
                        <button class="btn btn-sm btn-outline-danger" data-filtre="boucle"><i class="bi bi-arrow-repeat me-1"></i>Boucles</button>
                        <button class="btn btn-sm btn-outline-danger" data-filtre="404"><i class="bi bi-x-circle me-1"></i>404</button>
                        <button class="btn btn-sm btn-outline-danger" data-filtre="http-erreur"><i class="bi bi-exclamation-octagon me-1"></i>Erreur HTTP</button>
                        <button class="btn btn-sm btn-outline-warning" data-filtre="auto"><i class="bi bi-arrow-return-right me-1"></i>Auto-redirect</button>
                        <button class="btn btn-sm btn-outline-info" data-filtre="redirect"><i class="bi bi-shuffle me-1"></i>Redirect inattendue</button>
                        <div class="ms-auto">
                            <input type="text" class="form-control form-control-sm" id="rechercheProblemes" placeholder="Rechercher..." style="width:200px">
                        </div>
                    </div>
                    <div class="mb-2">
                        <button class="btn btn-sm btn-outline-success d-none" id="btnAppliquerTout">
                            <i class="bi bi-check2-all me-1"></i>Appliquer toutes les corrections
                        </button>
                    </div>
                    <div class="bulk-page-info mb-1" id="page-info-tableProblemes"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tableProblemes">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="sortable-th" data-col="1" data-type="str">Source</th>
                                    <th>Destination actuelle</th>
                                    <th>Detail</th>
                                    <th>Destination corrigee</th>
                                </tr>
                            </thead>
                            <tbody id="bodyProblemes"></tbody>
                        </table>
                    </div>
                    <nav class="bulk-pagination" id="pagination-tableProblemes">
                        <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
                    </nav>
                    <p id="aucunProbleme" class="text-muted text-center py-3 d-none">
                        <i class="bi bi-check-circle text-success me-1"></i>Aucun probleme detecte.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tab Liste complete -->
        <div class="tab-pane fade" id="tab-liste" role="tabpanel">
            <div class="card shadow-sm border-top-0 rounded-top-0">
                <div class="card-body">
                    <div class="mb-3 d-flex align-items-center">
                        <input type="text" class="form-control form-control-sm ms-auto" id="rechercheListe" placeholder="Rechercher..." style="width:200px">
                    </div>
                    <div class="bulk-page-info mb-1" id="page-info-tableListe"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tableListe">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th class="sortable-th" data-col="1" data-type="str">Source</th>
                                    <th>Destination</th>
                                    <th>Statut</th>
                                    <th>HTTP</th>
                                </tr>
                            </thead>
                            <tbody id="bodyListe"></tbody>
                        </table>
                    </div>
                    <nav class="bulk-pagination" id="pagination-tableListe">
                        <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Tab Avertissements -->
        <div class="tab-pane fade" id="tab-avertissements" role="tabpanel">
            <div class="card shadow-sm border-top-0 rounded-top-0">
                <div class="card-body">
                    <div class="bulk-page-info mb-1" id="page-info-tableAvertissements"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tableAvertissements">
                            <thead>
                                <tr>
                                    <th style="width:50px">#</th>
                                    <th>Avertissement</th>
                                </tr>
                            </thead>
                            <tbody id="bodyAvertissements"></tbody>
                        </table>
                    </div>
                    <nav class="bulk-pagination" id="pagination-tableAvertissements">
                        <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons export -->
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" id="btnExportCorrige">
            <i class="bi bi-download me-1"></i>Exporter les corrections (CSV)
        </button>
        <button class="btn btn-outline-secondary" id="btnExportProblemes">
            <i class="bi bi-download me-1"></i>Exporter les problemes (CSV)
        </button>
    </div>
</div>

<div id="copyToast" class="copy-toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.rcJobId = <?= json_encode($jobId) ?>;
    window.rcAnalyse = <?= $analyse ? json_encode($analyse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null' ?>;
    window.rcEstTermine = <?= $estTermine ? 'true' : 'false' ?>;
    window.rcVerifier404 = <?= $verifier404 ? 'true' : 'false' ?>;
    window.rcResultatsHttp = <?= $resultatsHttp ? json_encode($resultatsHttp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null' ?>;
    window.rcLogOffset = <?= $logOffsetInitial ?>;
</script>
<script src="app.js?v=<?= time() ?>"></script>
</body>
</html>
