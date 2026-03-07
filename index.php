<?php

require_once __DIR__ . '/boot.php';

$erreur = '';

// Nettoyage des anciens jobs
$gestionnaire = new GestionnaireJobs();
$gestionnaire->nettoyerAnciensJobs();

// Protection du repertoire data
$dataHtaccess = __DIR__ . '/data/.htaccess';
if (!file_exists($dataHtaccess)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }
    if (!is_dir(__DIR__ . '/data/jobs')) {
        mkdir(__DIR__ . '/data/jobs', 0755, true);
    }
    file_put_contents($dataHtaccess, "Deny from all\n");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirects Checker</title>
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
            <span class="d-block d-sm-inline ms-sm-2 text-light-muted">Chaines, boucles & 404</span>
        </span>
    </div>
</nav>

<div class="container py-3">
    <?php if ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-0" id="tabsAccueil" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-nouvelle" data-bs-toggle="tab" data-bs-target="#pane-nouvelle" type="button" role="tab">
                <i class="bi bi-plus-circle me-1"></i>Nouvelle analyse
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-historique" data-bs-toggle="tab" data-bs-target="#pane-historique" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i>Historique
            </button>
        </li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="pane-nouvelle" role="tabpanel">
    <div class="card shadow-sm mb-4 border-top-0 rounded-top-0">
        <div class="card-header">
            <h5 class="mb-0 fw-bold"><i class="bi bi-upload me-2"></i>Importer les redirections</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="analyseForm">

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-textarea" data-bs-toggle="tab" data-bs-target="#pane-textarea" type="button" role="tab">
                            <i class="bi bi-textarea-t me-1"></i>Coller le texte
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-fichier" data-bs-toggle="tab" data-bs-target="#pane-fichier" type="button" role="tab">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i>Importer un fichier
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pane-textarea" role="tabpanel">
                        <div class="mb-3">
                            <label for="contenu" class="form-label fw-semibold">Liste des redirections (source → destination)</label>
                            <textarea class="form-control font-mono" id="contenu" name="contenu" rows="12"
                                      placeholder="/ancienne-page;/nouvelle-page&#10;/ancien-produit;/nouveau-produit&#10;https://example.com/old;https://example.com/new"></textarea>
                            <div class="form-text">Une redirection par ligne. Formats acceptes : tabulation, point-virgule ou virgule comme separateur.</div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-fichier" role="tabpanel">
                        <div class="mb-3">
                            <label for="fichierCsv" class="form-label fw-semibold">Fichier CSV / TSV</label>
                            <input type="file" class="form-control" id="fichierCsv" name="fichier_csv" accept=".csv,.tsv,.txt">
                            <div class="form-text">Fichier avec 2 colonnes : source et destination. Max 5 Mo.</div>
                            <div id="fichierInfo" class="d-none mt-2"></div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="domaine" id="domaine" value="">

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="separateur" class="form-label fw-semibold">Separateur</label>
                        <select class="form-select" id="separateur" name="separateur">
                            <option value="auto" selected>Auto-detection</option>
                            <option value="&#9;">Tabulation</option>
                            <option value=";">Point-virgule (;)</option>
                            <option value=",">,Virgule (,)</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="verifier404" name="verifier_404" value="1" checked>
                            <label class="form-check-label fw-semibold" for="verifier404">
                                Verifier les 404 (HTTP)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
                    <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#optionsAvancees" role="button">
                        <i class="bi bi-gear me-1"></i>Options avancees du crawler
                    </a>
                    <span class="text-muted small">|</span>
                    <select class="form-select form-select-sm" id="selectConfig" style="width: auto; min-width: 180px;">
                        <option value="">-- Configs sauvegardees --</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnChargerConfig" title="Charger">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnSupprimerConfig" title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                    <input type="text" class="form-control form-control-sm" id="inputNomConfig"
                           placeholder="Nom de la config" style="width: 160px;">
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnSauvegarderConfig">
                        <i class="bi bi-save me-1"></i>Sauvegarder
                    </button>
                </div>
                <div class="collapse mb-3" id="optionsAvancees">
                    <div class="card card-body bg-light">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Requetes concurrentes</label>
                                <select class="form-select" name="concurrence">
                                    <option value="1">1</option>
                                    <option value="2" selected>2</option>
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Delai entre requetes</label>
                                <select class="form-select" name="delai_ms">
                                    <option value="0">Aucun</option>
                                    <option value="100" selected>100 ms</option>
                                    <option value="200">200 ms</option>
                                    <option value="300">300 ms</option>
                                    <option value="400">400 ms</option>
                                    <option value="500">500 ms</option>
                                    <option value="600">600 ms</option>
                                    <option value="700">700 ms</option>
                                    <option value="800">800 ms</option>
                                    <option value="900">900 ms</option>
                                    <option value="1000">1 s</option>
                                    <option value="2000">2 s</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Timeout par requete</label>
                                <select class="form-select" name="timeout">
                                    <option value="3">3 s</option>
                                    <option value="5" selected>5 s</option>
                                    <option value="10">10 s</option>
                                    <option value="15">15 s</option>
                                    <option value="30">30 s</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Max redirections suivies</label>
                                <select class="form-select" name="max_redirections">
                                    <option value="0">Ne pas suivre</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3" selected>3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">User-Agent</label>
                                <select class="form-select" name="user_agent_preset" id="userAgentPreset">
                                    <option value="custom" selected>Personnalise</option>
                                    <option value="googlebot">Googlebot</option>
                                    <option value="googlebot-mobile">Googlebot Mobile</option>
                                    <option value="bingbot">Bingbot</option>
                                    <option value="chrome">Chrome Desktop</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">&nbsp;</label>
                                <input type="text" class="form-control font-mono" name="user_agent"
                                       value="SalomonBotSEO/1.0" id="userAgentInput">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Header HTTP custom (optionnel)</label>
                                <input type="text" class="form-control font-mono" name="custom_header_nom"
                                       placeholder="X-Custom-Header" id="customHeaderNom">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">&nbsp;</label>
                                <input type="text" class="form-control font-mono" name="custom_header_valeur"
                                       placeholder="valeur" id="customHeaderValeur">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="statusZone" class="mb-3 d-none">
                    <div class="alert" id="statusMessage"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="bi bi-play-fill me-1"></i>Analyser les redirections
                </button>
            </form>
        </div>
    </div>
    </div><!-- /pane-nouvelle -->

    <div class="tab-pane fade" id="pane-historique" role="tabpanel">
    <div class="card shadow-sm mb-4 border-top-0 rounded-top-0">
        <div class="card-body">
            <div id="historiqueChargement" class="text-center py-4 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>Chargement...
            </div>
            <div id="historiqueVide" class="text-center py-4 text-muted d-none">
                <i class="bi bi-inbox me-1"></i>Aucune analyse enregistree.
            </div>
            <div class="table-responsive d-none" id="historiqueTableWrapper">
                <table class="table table-sm table-hover mb-0" id="tableHistorique">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Domaine</th>
                            <th>URLs</th>
                            <th>Crawlees</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bodyHistorique"></tbody>
                </table>
                <nav id="paginationHistorique" class="mt-2 d-none">
                    <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
                </nav>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Les analyses sont automatiquement supprimees apres 72h.</p>
        </div>
    </div>
    </div><!-- /pane-historique -->
    </div><!-- /tab-content -->
</div>

<div class="modal fade" id="modaleDomaine" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-globe me-1"></i>Domaine requis</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Des URLs relatives ont ete detectees. Indiquez le domaine pour les prefixer.</p>
                <input type="text" class="form-control" id="inputDomaineModale" placeholder="https://example.com">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmerDomaine">
                    <i class="bi bi-play-fill me-1"></i>Continuer
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="app.js"></script>
</body>
</html>
