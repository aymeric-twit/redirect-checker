(function () {
    'use strict';

    var baseUrl = window.MODULE_BASE_URL || '.';
    var pollingTimer = null;

    // -- Variables d'etat (results.php) — declarees ici pour eviter le hoisting undefined --
    var analyse = null;
    var resultatsHttp = null;
    var corrections = {};
    var filtreActif = 'tous';
    var paginProblemes = null;
    var paginListe = null;
    var paginAvertissements = null;
    var PAGE_SIZE = 50;

    // -- Page index.php : soumission formulaire --
    var formulaire = document.getElementById('analyseForm');
    if (formulaire) {
        initFormulaire();
    }

    // -- Page results.php : affichage resultats --
    var jobId = window.rcJobId;
    if (jobId) {
        initResultats();
    }

    // ===========================================
    // FORMULAIRE (index.php)
    // ===========================================

    var CHAMPS_CONFIG = [
        'domaine', 'concurrence', 'delai_ms', 'timeout', 'max_redirections',
        'user_agent_preset', 'user_agent', 'custom_header_nom', 'custom_header_valeur'
    ];

    function initFormulaire() {
        formulaire.addEventListener('submit', function (e) {
            e.preventDefault();
            soumettreAnalyse();
        });
        initPresetsUserAgent();
        initGestionConfigs();
        initFichierInfo();
        initHistorique();
    }

    // -- Feedback visuel a la selection du fichier --

    var fichierContenu = null; // contenu lu en arriere-plan

    function initFichierInfo() {
        var input = document.getElementById('fichierCsv');
        if (!input) return;

        input.addEventListener('change', function () {
            var infoDiv = document.getElementById('fichierInfo');
            if (!infoDiv) return;

            fichierContenu = null;

            if (!input.files || !input.files[0]) {
                infoDiv.classList.add('d-none');
                return;
            }

            var fichier = input.files[0];
            var tailleMo = (fichier.size / (1024 * 1024)).toFixed(2);
            var tailleKo = (fichier.size / 1024).toFixed(0);
            var tailleTexte = fichier.size >= 1024 * 1024 ? tailleMo + ' Mo' : tailleKo + ' Ko';

            infoDiv.classList.remove('d-none');
            infoDiv.innerHTML = '<div class="alert alert-info py-2 mb-0 d-flex align-items-center gap-2">'
                + '<span class="spinner-border spinner-border-sm"></span>'
                + '<span><strong>' + escapeHtml(fichier.name) + '</strong> (' + tailleTexte + ') — Lecture du fichier...</span>'
                + '</div>';

            var reader = new FileReader();
            reader.onload = function (e) {
                fichierContenu = e.target.result;
                var nbLignes = fichierContenu.split('\n').filter(function (l) { return l.trim(); }).length;

                infoDiv.innerHTML = '<div class="alert alert-success py-2 mb-0">'
                    + '<i class="bi bi-check-circle me-1"></i>'
                    + '<strong>' + escapeHtml(fichier.name) + '</strong> — '
                    + tailleTexte + ' — ~' + formaterNombre(nbLignes) + ' lignes'
                    + '</div>';
            };
            reader.onerror = function () {
                infoDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">'
                    + '<i class="bi bi-x-circle me-1"></i>Impossible de lire le fichier.'
                    + '</div>';
            };
            reader.readAsText(fichier);
        });
    }

    // -- Historique des analyses --

    var historiqueCharge = false;

    function initHistorique() {
        var tabHistorique = document.getElementById('tab-historique');
        if (!tabHistorique) return;

        tabHistorique.addEventListener('shown.bs.tab', function () {
            if (historiqueCharge) return;
            chargerHistorique();
        });
    }

    var HISTORIQUE_PAR_PAGE = 25;

    function chargerHistorique() {
        historiqueCharge = true;
        var chargement = document.getElementById('historiqueChargement');
        var vide = document.getElementById('historiqueVide');
        var wrapper = document.getElementById('historiqueTableWrapper');
        var tbody = document.getElementById('bodyHistorique');
        var navPagin = document.getElementById('paginationHistorique');

        fetch(baseUrl + '/historique.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (chargement) chargement.classList.add('d-none');
            var jobs = data.jobs || [];
            if (jobs.length === 0) {
                if (vide) vide.classList.remove('d-none');
                return;
            }
            if (wrapper) wrapper.classList.remove('d-none');
            if (!tbody) return;

            var pageActuelle = 1;
            var totalPages = Math.max(1, Math.ceil(jobs.length / HISTORIQUE_PAR_PAGE));

            function rendrePageHistorique() {
                var start = (pageActuelle - 1) * HISTORIQUE_PAR_PAGE;
                var end = Math.min(start + HISTORIQUE_PAR_PAGE, jobs.length);
                var html = '';
                for (var i = start; i < end; i++) {
                    var job = jobs[i];
                    var badge = '';
                    if (job.statut === 'termine') {
                        badge = '<span class="badge bg-success">Termine</span>';
                    } else if (job.statut === 'erreur') {
                        badge = '<span class="badge bg-danger">Erreur</span>';
                    } else {
                        badge = '<span class="badge bg-warning text-dark"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>En cours</span>';
                    }
                    var domaine = job.domaine ? escapeHtml(job.domaine) : '<span class="text-muted">\u2014</span>';
                    var lien = baseUrl + '/results.php?job=' + encodeURIComponent(job.jobId);
                    var crawlees = job.verifier404
                        ? formaterNombre(job.urlsCrawlees) + ' / ' + formaterNombre(job.totalPaires)
                        : '<span class="text-muted">N/A</span>';
                    html += '<tr>'
                        + '<td class="small">' + escapeHtml(job.dateCreation) + '</td>'
                        + '<td class="small">' + domaine + '</td>'
                        + '<td class="text-center">' + formaterNombre(job.totalPaires) + '</td>'
                        + '<td class="text-center small">' + crawlees + '</td>'
                        + '<td>' + badge + '</td>'
                        + '<td><a href="' + escapeHtml(lien) + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Voir</a></td>'
                        + '</tr>';
                }
                tbody.innerHTML = html;
                construirePaginationHistorique();
            }

            function construirePaginationHistorique() {
                if (!navPagin) return;
                if (totalPages <= 1) {
                    navPagin.classList.add('d-none');
                    return;
                }
                navPagin.classList.remove('d-none');
                var ul = navPagin.querySelector('ul');
                ul.innerHTML = '';
                ajouterBtnPagin(ul, '\u00AB', pageActuelle > 1 ? pageActuelle - 1 : null);
                for (var p = 1; p <= totalPages; p++) {
                    ajouterBtnPagin(ul, String(p), p, p === pageActuelle);
                }
                ajouterBtnPagin(ul, '\u00BB', pageActuelle < totalPages ? pageActuelle + 1 : null);
            }

            function ajouterBtnPagin(ul, label, target, isActive) {
                var li = document.createElement('li');
                li.className = 'page-item' + (isActive ? ' active' : '') + (target === null ? ' disabled' : '');
                var a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = label;
                if (target !== null) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        pageActuelle = target;
                        rendrePageHistorique();
                    });
                }
                li.appendChild(a);
                ul.appendChild(li);
            }

            rendrePageHistorique();
        })
        .catch(function () {
            if (chargement) chargement.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Erreur de chargement.</span>';
        });
    }

    // -- Gestion des configs sauvegardees (serveur) --

    var configsCache = null;

    function chargerConfigs(callback) {
        fetch(baseUrl + '/configs.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            configsCache = Array.isArray(data) ? data : [];
            callback(configsCache);
        })
        .catch(function () {
            configsCache = configsCache || [];
            callback(configsCache);
        });
    }

    function sauvegarderConfig(nom, valeurs, callback) {
        fetch(baseUrl + '/configs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ nom: nom, valeurs: valeurs })
        })
        .then(function (r) {
            if (!r.ok) return r.json().then(function (d) { throw d; });
            return r.json();
        })
        .then(function () { callback(null); })
        .catch(function (err) { callback(err && err.erreur ? err.erreur : 'Erreur de sauvegarde'); });
    }

    function supprimerConfig(nom, callback) {
        fetch(baseUrl + '/configs.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ nom: nom })
        })
        .then(function (r) { return r.json(); })
        .then(function () { callback(null); })
        .catch(function () { callback('Erreur de suppression'); });
    }

    function lireValeursFormulaire() {
        var valeurs = {};
        CHAMPS_CONFIG.forEach(function (nom) {
            var el = formulaire.querySelector('[name="' + nom + '"]');
            if (el) valeurs[nom] = el.value;
        });
        return valeurs;
    }

    function appliquerValeursFormulaire(valeurs) {
        CHAMPS_CONFIG.forEach(function (nom) {
            if (valeurs[nom] === undefined) return;
            var el = formulaire.querySelector('[name="' + nom + '"]');
            if (el) {
                el.value = valeurs[nom];
                // Declencher change pour les selects (ex: user_agent_preset)
                el.dispatchEvent(new Event('change'));
            }
        });
    }

    function rafraichirSelectConfigs(configs) {
        var select = document.getElementById('selectConfig');
        if (!select) return;
        select.innerHTML = '<option value="">-- Configs sauvegardees --</option>';
        (configs || []).forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.nom;
            opt.textContent = c.nom;
            select.appendChild(opt);
        });
    }

    function initGestionConfigs() {
        var btnSauver = document.getElementById('btnSauvegarderConfig');
        var btnCharger = document.getElementById('btnChargerConfig');
        var btnSupprimer = document.getElementById('btnSupprimerConfig');
        var selectConfig = document.getElementById('selectConfig');
        var inputNom = document.getElementById('inputNomConfig');

        if (!btnSauver || !selectConfig) return;

        // Charger les configs depuis le serveur au demarrage
        chargerConfigs(function (configs) {
            rafraichirSelectConfigs(configs);
        });

        btnSauver.addEventListener('click', function () {
            var nom = inputNom.value.trim();
            if (!nom) {
                inputNom.focus();
                inputNom.classList.add('is-invalid');
                setTimeout(function () { inputNom.classList.remove('is-invalid'); }, 1500);
                return;
            }
            btnSauver.disabled = true;
            sauvegarderConfig(nom, lireValeursFormulaire(), function (err) {
                btnSauver.disabled = false;
                if (err) { afficherToast(err); return; }
                inputNom.value = '';
                chargerConfigs(function (configs) {
                    rafraichirSelectConfigs(configs);
                    selectConfig.value = nom;
                });
                afficherToast('Config "' + nom + '" sauvegardee.');
            });
        });

        btnCharger.addEventListener('click', function () {
            var nom = selectConfig.value;
            if (!nom || !configsCache) return;
            var config = configsCache.find(function (c) { return c.nom === nom; });
            if (!config) return;
            appliquerValeursFormulaire(config.valeurs);
            var collapse = document.getElementById('optionsAvancees');
            if (collapse && !collapse.classList.contains('show')) {
                new bootstrap.Collapse(collapse, { toggle: true });
            }
            afficherToast('Config "' + nom + '" chargee.');
        });

        btnSupprimer.addEventListener('click', function () {
            var nom = selectConfig.value;
            if (!nom) return;
            btnSupprimer.disabled = true;
            supprimerConfig(nom, function (err) {
                btnSupprimer.disabled = false;
                if (err) { afficherToast(err); return; }
                chargerConfigs(function (configs) {
                    rafraichirSelectConfigs(configs);
                });
                afficherToast('Config "' + nom + '" supprimee.');
            });
        });
    }

    var USER_AGENTS = {
        'custom': '',
        'googlebot': 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'googlebot-mobile': 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'bingbot': 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'chrome': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    };

    function initPresetsUserAgent() {
        var select = document.getElementById('userAgentPreset');
        var input = document.getElementById('userAgentInput');
        if (!select || !input) return;

        select.addEventListener('change', function () {
            var preset = select.value;
            if (preset !== 'custom' && USER_AGENTS[preset]) {
                input.value = USER_AGENTS[preset];
            }
        });
    }

    function contientUrlsRelatives(contenu) {
        var lignes = contenu.split('\n');
        for (var i = 0; i < lignes.length; i++) {
            var ligne = lignes[i].trim();
            if (!ligne || ligne.charAt(0) === '#') continue;
            var champ = ligne.split(/[\t;,]/)[0].trim();
            if (champ && !/^https?:\/\//i.test(champ)) return true;
        }
        return false;
    }

    function obtenirContenuFormulaire() {
        var textarea = document.getElementById('contenu');
        if (textarea && textarea.value.trim()) return textarea.value;
        if (fichierContenu) return fichierContenu;
        return '';
    }

    function soumettreAnalyse() {
        // Si un fichier est selectionne mais pas encore lu, le lire d'abord
        var inputFichier = document.getElementById('fichierCsv');
        if (inputFichier && inputFichier.files && inputFichier.files[0] && !fichierContenu) {
            var btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Lecture du fichier\u2026';
            var reader = new FileReader();
            reader.onload = function (e) {
                fichierContenu = e.target.result;
                soumettreAnalyse();
            };
            reader.onerror = function () {
                afficherStatus('Impossible de lire le fichier.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Analyser les redirections';
            };
            reader.readAsText(inputFichier.files[0]);
            return;
        }

        // Detecter URLs relatives et ouvrir la modale si necessaire
        var contenu = obtenirContenuFormulaire();
        var domaineHidden = document.getElementById('domaine');

        if (contenu && contientUrlsRelatives(contenu) && !domaineHidden.value.trim()) {
            ouvrirModaleDomaine();
            return;
        }

        envoyerFormulaire();
    }

    function ouvrirModaleDomaine() {
        var modale = new bootstrap.Modal(document.getElementById('modaleDomaine'));
        var inputModale = document.getElementById('inputDomaineModale');
        var domaineHidden = document.getElementById('domaine');

        // Pre-remplir avec la valeur existante (ex: config chargee)
        inputModale.value = domaineHidden.value || '';

        modale.show();
        setTimeout(function () { inputModale.focus(); }, 300);

        var btnConfirmer = document.getElementById('btnConfirmerDomaine');
        // Eviter les listeners dupliques
        var nouveauBtn = btnConfirmer.cloneNode(true);
        btnConfirmer.parentNode.replaceChild(nouveauBtn, btnConfirmer);

        nouveauBtn.addEventListener('click', function () {
            domaineHidden.value = inputModale.value.trim();
            modale.hide();
            envoyerFormulaire();
        });

        // Soumettre aussi avec Enter
        inputModale.addEventListener('keydown', function handler(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                domaineHidden.value = inputModale.value.trim();
                modale.hide();
                envoyerFormulaire();
                inputModale.removeEventListener('keydown', handler);
            }
        });
    }

    function envoyerFormulaire() {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Envoi en cours\u2026';
        afficherStatus('Envoi des donnees...', 'upload', 0);

        var formData = new FormData(formulaire);

        // Envoyer le contenu du fichier via le champ texte (evite les limites PHP upload)
        if (fichierContenu) {
            formData.set('contenu', fichierContenu);
            formData.delete('fichier_csv');
        }

        // Ajouter le token CSRF si disponible
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('_csrf_token', csrfMeta.content);
        }
        var csrfInput = document.querySelector('input[name="_csrf_token"]');
        if (csrfInput) {
            formData.append('_csrf_token', csrfInput.value);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', baseUrl + '/process.php');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                afficherStatus('Envoi du fichier... ' + pct + '%', 'upload', pct);
                if (pct >= 100) {
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyse en cours\u2026';
                    afficherStatus('Analyse des redirections...', 'loading');
                }
            }
        });

        xhr.addEventListener('load', function () {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }

            if (xhr.status >= 200 && xhr.status < 300 && data && data.jobId) {
                window.location.href = baseUrl + '/results.php?job=' + encodeURIComponent(data.jobId);
            } else {
                var msg = (data && data.erreur) ? data.erreur : 'Erreur lors de l\'analyse.';
                afficherStatus(msg, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Analyser les redirections';
            }
        });

        xhr.addEventListener('error', function () {
            afficherStatus('Erreur reseau lors de l\'envoi.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Analyser les redirections';
        });

        xhr.send(formData);
    }

    function afficherStatus(message, type, pct) {
        var zone = document.getElementById('statusZone');
        var msg = document.getElementById('statusMessage');
        if (!zone || !msg) return;

        zone.classList.remove('d-none');
        msg.className = 'alert';

        if (type === 'upload') {
            msg.classList.add('alert-info');
            var p = pct || 0;
            msg.innerHTML = '<div class="d-flex align-items-center gap-2">'
                + '<span class="spinner-border spinner-border-sm"></span> '
                + escapeHtml(message)
                + '</div>'
                + '<div class="progress mt-2" style="height: 6px;">'
                + '<div class="progress-bar progress-bar-teal progress-bar-striped progress-bar-animated" style="width:' + p + '%"></div>'
                + '</div>';
        } else if (type === 'loading') {
            msg.classList.add('alert-info');
            msg.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + escapeHtml(message);
        } else if (type === 'error') {
            msg.classList.add('alert-danger');
            msg.textContent = message;
        } else if (type === 'success') {
            msg.classList.add('alert-success');
            msg.textContent = message;
        }
    }

    // ===========================================
    // RESULTATS (results.php)
    // ===========================================

    // (variables declarees en haut de la IIFE)

    // Resout une destination relative vers son URL prefixee (pour comparaison avec urlFinale)
    function resoudreDestination(url) {
        if (!resultatsHttp || !resultatsHttp.mappingDestinations) return url;
        return resultatsHttp.mappingDestinations[url] || url;
    }

    // Cherche le resultat HTTP pour une URL source
    function chercherVerifHttp(source) {
        if (!resultatsHttp || !resultatsHttp.verificationsHttp) return null;
        return resultatsHttp.verificationsHttp[source] || null;
    }

    function initResultats() {
        analyse = window.rcAnalyse;
        resultatsHttp = window.rcResultatsHttp;

        // Lien retour en mode embedded
        var lienRetour = document.getElementById('lienRetour');
        if (lienRetour) {
            lienRetour.href = baseUrl + '/index.php';
        }

        // Si verification 404 en cours, lancer le polling (meme si analyse est null)
        if (window.rcVerifier404 && !window.rcEstTermine) {
            demarrerPolling();
        }

        if (!analyse) return;

        afficherKpi();
        afficherAvertissements();
        construireTableProblemes();
        construireTableListe();
        initFiltres();
        initTriTableau();
        initExport();
    }

    // -- KPI --

    function afficherKpi() {
        var resume = analyse.resume;

        // KPI "Redirections 3xx" : nombre de vraies 3xx crawlees
        var nb3xx = compter3xx();
        if (window.rcVerifier404 && !window.rcEstTermine && nb3xx === 0) {
            setText('kpiTotal', '...');
        } else if (window.rcVerifier404 && nb3xx > 0) {
            setText('kpiTotal', formaterNombre(nb3xx));
        } else {
            setText('kpiTotal', formaterNombre(resume.total));
        }

        // KPI "Chaines" : statiques + chaines crawl
        var chainesCrawl = compterChainesCrawl();
        var totalChaines = resume.chaines + chainesCrawl;
        setText('kpiChaines', formaterNombre(totalChaines));
        setText('kpiBoucles', formaterNombre(resume.boucles));

        setKpiClass('kpiChainesCard', totalChaines);
        setKpiClass('kpiBouclesCard', resume.boucles);

        // Erreurs HTTP : sera mis a jour apres le polling
        var nbErreurs = compterErreursHttp();
        setText('kpi404', window.rcVerifier404 ? (window.rcEstTermine ? formaterNombre(nbErreurs) : '...') : 'N/A');
        if (window.rcEstTermine) {
            setKpiClass('kpi404Card', nbErreurs);
        }

        // Corrections : mis a jour apres construireTableProblemes
        setText('kpiCorrections', '0');
    }

    function compter3xx() {
        if (!resultatsHttp || !resultatsHttp.verificationsHttp) return 0;
        var verifs = resultatsHttp.verificationsHttp;
        var count = 0;
        var cles = Object.keys(verifs);
        for (var i = 0; i < cles.length; i++) {
            var verif = verifs[cles[i]];
            if (verif.chaineRedirections && verif.chaineRedirections.length > 0) {
                count++;
            } else if (verif.statut >= 300 && verif.statut < 400) {
                count++;
            }
        }
        return count;
    }

    function compterChainesCrawl() {
        if (!resultatsHttp || !resultatsHttp.verificationsHttp || !analyse) return 0;
        var verifs = resultatsHttp.verificationsHttp;
        // Sources deja dans les chaines statiques
        var sourcesChaines = {};
        (analyse.chaines || []).forEach(function (c) {
            for (var k = 0; k < c.chaine.length; k++) {
                sourcesChaines[c.chaine[k]] = true;
            }
        });
        var count = 0;
        var cles = Object.keys(verifs);
        for (var i = 0; i < cles.length; i++) {
            var verif = verifs[cles[i]];
            if (verif.chaineRedirections && verif.chaineRedirections.length > 1 && !sourcesChaines[cles[i]]) {
                count++;
            }
        }
        return count;
    }

    function setKpiClass(id, valeur) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('kpi-good', 'kpi-warn', 'kpi-bad');
        el.classList.add(valeur === 0 ? 'kpi-good' : 'kpi-bad');
    }

    function compterErreursHttp() {
        if (!resultatsHttp || !resultatsHttp.verificationsHttp || !analyse) return 0;
        var count = 0;
        var verifs = resultatsHttp.verificationsHttp;

        // Parcourir les paires pour detecter tous les problemes HTTP
        (analyse.paires || []).forEach(function (p) {
            var verif = verifs[p.source];
            if (!verif) return;

            // Erreur HTTP ou connexion
            if ((verif.statut && verif.statut >= 400) || verif.erreur) {
                count++;
                return;
            }

            // Redirect inattendue
            var destinationAttendue = resoudreDestination(p.destination);
            if (verif.urlFinale && verif.urlFinale !== destinationAttendue) {
                count++;
                return;
            }

            // Pas de redirection (200 sans urlFinale)
            if (!verif.urlFinale && verif.statut === 200) {
                count++;
            }
        });
        return count;
    }

    // -- Avertissements --

    function afficherAvertissements() {
        var avertissements = analyse.avertissements || [];
        if (avertissements.length === 0) return;

        var tabItem = document.getElementById('tabAvertissementsItem');
        if (tabItem) tabItem.classList.remove('d-none');

        setText('badgeAvertissements', String(avertissements.length));

        paginAvertissements = creerPagination('tableAvertissements', function (a, i) {
            return '<tr>'
                + '<td class="text-muted small">' + (i + 1) + '</td>'
                + '<td class="small">' + escapeHtml(a) + '</td>'
                + '</tr>';
        });
        paginAvertissements.setDonnees(avertissements);
    }

    // -- Table des problemes --

    function construireTableProblemes() {
        var lignes = [];

        // Chaines — une ligne par maillon intermediaire, chacun corrige vers la destination finale
        (analyse.chaines || []).forEach(function (c) {
            var destinationFinale = c.chaine[c.chaine.length - 1];
            var chaineTexte = c.chaine.join(' \u2192 ');

            // Pour chaque source intermediaire, proposer la correction vers la dest finale
            // Exclure le dernier maillon (qui pointe deja vers la dest finale)
            for (var k = 0; k < c.chaine.length - 1; k++) {
                var src = c.chaine[k];
                var destActuelle = c.chaine[k + 1];
                if (destActuelle === destinationFinale) continue;
                lignes.push({
                    type: 'chaine',
                    source: src,
                    destination: destActuelle,
                    detail: chaineTexte,
                    correction: destinationFinale,
                    classeType: 'badge-chaine',
                    classeLigne: 'ligne-chaine',
                    labelType: 'Chaine',
                });
            }
        });

        // Boucles
        (analyse.boucles || []).forEach(function (b) {
            lignes.push({
                type: 'boucle',
                source: b.boucle[0],
                destination: b.boucle[1] || '',
                detail: b.boucle.join(' \u2192 '),
                correction: '',
                classeType: 'badge-boucle',
                classeLigne: 'ligne-boucle',
                labelType: 'Boucle',
            });
        });

        // Auto-redirections
        (analyse.autoRedirections || []).forEach(function (url) {
            lignes.push({
                type: 'auto',
                source: url,
                destination: url,
                detail: 'Source = Destination',
                correction: '',
                classeType: 'badge-auto',
                classeLigne: 'ligne-auto',
                labelType: 'Auto',
            });
        });

        // Problemes HTTP (404, 403, 5xx, redirects inattendues)
        ajouterProblemesHttp(lignes);

        window._problemesData = lignes;

        // Filtrer par type
        var filtrees = lignes.filter(function (l) {
            return filtreActif === 'tous' || l.type === filtreActif;
        });

        setText('badgeProblemes', String(lignes.length));

        if (lignes.length === 0) {
            document.getElementById('aucunProbleme').classList.remove('d-none');
        } else {
            document.getElementById('aucunProbleme').classList.add('d-none');
        }

        if (!paginProblemes) {
            paginProblemes = creerPagination('tableProblemes', rendreLineProbleme);
            initRechercheInput('rechercheProblemes', paginProblemes);
        }
        paginProblemes.setDonnees(filtrees);

        // Init edition inline apres rendu
        initEditionInline();

        // Bouton appliquer toutes les corrections
        var btnAppliquer = document.getElementById('btnAppliquerTout');
        if (btnAppliquer) {
            var nbCorrigibles = lignes.filter(function (l) { return l.correction; }).length;
            btnAppliquer.classList.toggle('d-none', nbCorrigibles === 0);
            btnAppliquer.onclick = function () {
                appliquerToutesCorrections();
            };
        }

        mettreAJourKpiCorrections();
    }

    function rendreLineProbleme(l) {
        var correctionVal = corrections[l.source] || l.correction;
        var correctionHtml = l.type === 'boucle'
            ? '<span class="text-muted fst-italic">A resoudre manuellement</span>'
            : '<span class="cellule-editable url-cell" data-source="' + escapeAttr(l.source) + '" data-original="' + escapeAttr(l.correction) + '">'
              + escapeHtml(correctionVal || 'Cliquer pour corriger')
              + '</span>';

        return '<tr class="' + l.classeLigne + '" data-type="' + l.type + '">'
            + '<td><span class="' + l.classeType + '">' + l.labelType + '</span></td>'
            + '<td class="url-cell" title="' + escapeAttr(l.source) + '">' + escapeHtml(l.source) + '</td>'
            + '<td class="url-cell" title="' + escapeAttr(l.destination) + '">' + escapeHtml(l.destination) + '</td>'
            + '<td class="small">' + escapeHtml(l.detail) + '</td>'
            + '<td>' + correctionHtml + '</td>'
            + '</tr>';
    }

    function initEditionInline() {
        var tbody = document.getElementById('bodyProblemes');
        if (!tbody) return;
        tbody.querySelectorAll('.cellule-editable').forEach(function (el) {
            el.addEventListener('click', function () {
                activerEdition(el);
            });
        });
    }

    function appliquerToutesCorrections() {
        var lignes = window._problemesData || [];
        var nbAppliquees = 0;
        lignes.forEach(function (l) {
            if (l.correction && !corrections[l.source]) {
                corrections[l.source] = l.correction;
                nbAppliquees++;
            }
        });
        rendreTableProblemes();
        mettreAJourKpiCorrections();
        afficherToast(nbAppliquees + ' correction(s) appliquee(s).');
    }

    function compterCorrections() {
        var paires = analyse ? (analyse.paires || []) : [];
        var aplati = analyse ? (analyse.aplati || {}) : {};
        var nb = 0;
        paires.forEach(function (p) {
            var info = aplati[p.source] || {};
            var dest = corrections[p.source] || info.destination || p.destination;
            if (dest !== p.destination) nb++;
        });
        return nb;
    }

    function mettreAJourKpiCorrections() {
        var nb = compterCorrections();
        setText('kpiCorrections', formaterNombre(nb));
        var card = document.getElementById('kpiCorrectionsCard');
        if (card) {
            card.classList.remove('kpi-good', 'kpi-warn', 'kpi-bad');
            card.classList.add(nb > 0 ? 'kpi-warn' : 'kpi-good');
        }
    }

    function ajouterProblemesHttp(lignes) {
        if (!resultatsHttp || !resultatsHttp.verificationsHttp) return;
        var dejaAjoute = {};

        (analyse.paires || []).forEach(function (p) {
            var verif = chercherVerifHttp(p.source);
            if (!verif) return;

            var statut = verif.statut;
            var destinationAttendue = resoudreDestination(p.destination);

            // Erreur HTTP (4xx, 5xx) ou erreur de connexion sur la source
            if ((statut && statut >= 400) || verif.erreur) {
                var cle = 'http:' + p.source;
                if (dejaAjoute[cle]) return;
                dejaAjoute[cle] = true;

                var label = statut ? ('HTTP ' + statut) : 'Erreur';
                var detail = statut ? ('HTTP ' + statut + ' - ' + descriptionCodeHttp(statut)) : verif.erreur;

                var type = statut === 404 ? '404' : 'http-erreur';
                lignes.push({
                    type: type,
                    source: p.source,
                    destination: p.destination,
                    detail: detail,
                    correction: '',
                    classeType: statut === 404 ? 'badge-404' : 'badge-http-erreur',
                    classeLigne: statut === 404 ? 'ligne-404' : 'ligne-http-erreur',
                    labelType: label,
                });
                return;
            }

            // Redirect inattendue : l'URL finale ne correspond pas a la destination attendue
            if (verif.urlFinale && verif.urlFinale !== destinationAttendue && !dejaAjoute['redirect:' + p.source]) {
                dejaAjoute['redirect:' + p.source] = true;
                lignes.push({
                    type: 'redirect',
                    source: p.source,
                    destination: p.destination,
                    detail: 'URL finale : ' + verif.urlFinale + ' (attendue : ' + destinationAttendue + ')',
                    correction: verif.urlFinale,
                    classeType: 'badge-redirect',
                    classeLigne: 'ligne-redirect',
                    labelType: 'Redirect',
                });
            }

            // Pas de redirection : la source repond 200 sans rediriger
            if (!verif.urlFinale && statut === 200 && !dejaAjoute['no-redirect:' + p.source]) {
                dejaAjoute['no-redirect:' + p.source] = true;
                lignes.push({
                    type: 'http-erreur',
                    source: p.source,
                    destination: p.destination,
                    detail: 'La source repond 200 sans rediriger (redirection non configuree)',
                    correction: '',
                    classeType: 'badge-http-erreur',
                    classeLigne: 'ligne-http-erreur',
                    labelType: 'Pas de redir',
                });
            }
        });
    }

    function descriptionCodeHttp(code) {
        var descriptions = {
            400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden',
            404: 'Not Found', 405: 'Method Not Allowed', 408: 'Timeout',
            410: 'Gone', 429: 'Too Many Requests',
            500: 'Internal Server Error', 502: 'Bad Gateway',
            503: 'Service Unavailable', 504: 'Gateway Timeout'
        };
        return descriptions[code] || (code >= 500 ? 'Erreur serveur' : 'Erreur client');
    }

    function rendreTableProblemes() {
        if (!paginProblemes) return;
        var lignes = window._problemesData || [];
        var filtrees = lignes.filter(function (l) {
            return filtreActif === 'tous' || l.type === filtreActif;
        });
        paginProblemes.setDonnees(filtrees);
        initEditionInline();
    }

    // -- Edition inline --

    function activerEdition(cellule) {
        if (cellule.classList.contains('cellule-en-edition')) return;

        var source = cellule.getAttribute('data-source');
        var valeurActuelle = corrections[source] || cellule.getAttribute('data-original') || '';

        cellule.classList.add('cellule-en-edition');
        cellule.classList.remove('cellule-editable');

        var input = document.createElement('input');
        input.type = 'text';
        input.value = valeurActuelle;
        input.placeholder = 'Nouvelle destination';
        cellule.innerHTML = '';
        cellule.appendChild(input);
        input.focus();
        input.select();

        function valider() {
            var nouvelleValeur = input.value.trim();
            if (nouvelleValeur) {
                corrections[source] = nouvelleValeur;
            } else {
                delete corrections[source];
            }
            cellule.classList.remove('cellule-en-edition');
            cellule.classList.add('cellule-editable');
            cellule.textContent = nouvelleValeur || cellule.getAttribute('data-original') || 'Cliquer pour corriger';
            mettreAJourKpiCorrections();
        }

        input.addEventListener('blur', valider);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); valider(); }
            if (e.key === 'Escape') {
                cellule.classList.remove('cellule-en-edition');
                cellule.classList.add('cellule-editable');
                cellule.textContent = corrections[source] || cellule.getAttribute('data-original') || 'Cliquer pour corriger';
            }
        });
    }

    // -- Table liste complete --

    function rendreLigneListe(p, i) {
        var aplati = analyse.aplati || {};
        var info = aplati[p.source] || {};
        var statut = 'OK';
        var classeStatut = 'badge-ok';

        if (info.estBoucle) {
            statut = 'Boucle'; classeStatut = 'badge-boucle';
        } else if (p.source === p.destination) {
            statut = 'Auto'; classeStatut = 'badge-auto';
        } else if (info.destination && info.destination !== p.destination) {
            statut = 'Chaine'; classeStatut = 'badge-chaine';
        }

        var httpBadge = '';
        var urlFinaleInfo = '';
        var verif = chercherVerifHttp(p.source);

        if (verif) {
            var code = verif.statut;
            var classeHttp = code === 200 ? 'bg-success' : (code === 404 ? 'bg-danger' : (code >= 300 && code < 400 ? 'bg-warning text-dark' : (code >= 500 ? 'bg-danger' : 'bg-secondary')));
            httpBadge = '<span class="badge ' + classeHttp + ' badge-http">' + (code || 'ERR') + '</span>';

            if (code && code >= 400) {
                statut = code === 404 ? '404' : 'HTTP ' + code;
                classeStatut = code === 404 ? 'badge-404' : 'badge-http-erreur';
            }

            var destinationAttendue = resoudreDestination(p.destination);
            if (verif.urlFinale) {
                if (verif.urlFinale !== destinationAttendue) {
                    urlFinaleInfo = ' <i class="bi bi-arrow-right-short"></i><span class="url-cell text-warning" title="' + escapeAttr(verif.urlFinale) + '">' + escapeHtml(tronquer(verif.urlFinale, 50)) + '</span>';
                    if (statut === 'OK') { statut = 'Redirect'; classeStatut = 'badge-redirect'; }
                }
            } else if (code === 200 && statut === 'OK') {
                statut = 'Pas de redir';
                classeStatut = 'badge-http-erreur';
            }
        } else if (window.rcVerifier404 && window.rcEstTermine && statut === 'OK') {
            statut = 'Non verifie';
            classeStatut = 'badge-non-verifie';
        }

        return '<tr>'
            + '<td class="text-muted small">' + (i + 1) + '</td>'
            + '<td class="url-cell" title="' + escapeAttr(p.source) + '">' + escapeHtml(p.source) + '</td>'
            + '<td class="url-cell" title="' + escapeAttr(p.destination) + '">' + escapeHtml(p.destination) + urlFinaleInfo + '</td>'
            + '<td><span class="' + classeStatut + '">' + statut + '</span></td>'
            + '<td>' + httpBadge + '</td>'
            + '</tr>';
    }

    function construireTableListe() {
        if (!paginListe) {
            paginListe = creerPagination('tableListe', rendreLigneListe);
            initRechercheInput('rechercheListe', paginListe);
        }
        paginListe.setDonnees(analyse.paires || []);
    }

    // -- Filtres --

    function initFiltres() {
        var conteneur = document.getElementById('filtresProblemes');
        if (!conteneur) return;

        conteneur.querySelectorAll('[data-filtre]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                conteneur.querySelectorAll('[data-filtre]').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                filtreActif = btn.getAttribute('data-filtre');
                rendreTableProblemes();
            });
        });
    }

    // -- Tri --

    function initTriTableau() {
        document.querySelectorAll('.sortable-th').forEach(function (th) {
            th.addEventListener('click', function () {
                var table = th.closest('table');
                var colIndex = parseInt(th.getAttribute('data-col'));
                var type = th.getAttribute('data-type') || 'str';
                var direction = th.classList.contains('sort-asc') ? 'desc' : 'asc';

                table.querySelectorAll('.sortable-th').forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                th.classList.add('sort-' + direction);

                var tbody = table.querySelector('tbody');
                var lignes = Array.from(tbody.querySelectorAll('tr'));

                lignes.sort(function (a, b) {
                    var va = (a.cells[colIndex] || {}).textContent || '';
                    var vb = (b.cells[colIndex] || {}).textContent || '';
                    if (type === 'num') {
                        va = parseFloat(va) || 0;
                        vb = parseFloat(vb) || 0;
                    }
                    var cmp = va < vb ? -1 : (va > vb ? 1 : 0);
                    return direction === 'desc' ? -cmp : cmp;
                });

                lignes.forEach(function (tr) { tbody.appendChild(tr); });
            });
        });
    }

    // -- Polling 404 --

    var logOffset = window.rcLogOffset || 0;

    var pollingEnCours = false;

    function demarrerPolling() {
        pollingTimer = setInterval(verifierProgression, 1000);
        verifierProgression();
    }

    function verifierProgression() {
        if (pollingEnCours) return;
        pollingEnCours = true;

        fetch(baseUrl + '/progress.php?job=' + encodeURIComponent(jobId) + '&log_offset=' + logOffset, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            pollingEnCours = false;

            // Afficher les logs temps reel
            if (data.logs && data.logs.length > 0) {
                ajouterLogs(data.logs);
            }
            if (data.logOffset) {
                logOffset = data.logOffset;
            }

            if (data.status === 'done') {
                clearInterval(pollingTimer);
                chargerResultatsHttp();
                return;
            }
            if (data.status === 'error') {
                clearInterval(pollingTimer);
                document.getElementById('progressSection').innerHTML =
                    '<div class="card-body"><div class="alert alert-danger mb-0">' + escapeHtml(data.message || 'Erreur worker') + '</div></div>';
                return;
            }
            mettreAJourProgression(data);
        })
        .catch(function () { pollingEnCours = false; });
    }

    var MAX_LOGS_LIGNES = 200;

    function ajouterLogs(logs) {
        var panel = document.getElementById('logsPanel');
        if (!panel) return;

        var texte = '';
        logs.forEach(function (log) {
            var statutFinal = log.statut;
            var ligne = 'GET ' + log.urlCrawlee;

            var chaine = log.chaineRedirections || [];
            if (chaine.length > 0) {
                chaine.forEach(function (etape) {
                    ligne += '\n  [' + etape.statut + '] \u2192 ' + etape.url;
                });
            }

            ligne += '\n  \u2514\u2500 ' + (statutFinal ? statutFinal : 'ERR');
            if (log.erreur) {
                ligne += ' ' + log.erreur;
            }

            texte += ligne + '\n';
        });

        panel.value += texte;

        // Limiter le nombre de lignes
        var lignes = panel.value.split('\n');
        if (lignes.length > MAX_LOGS_LIGNES) {
            panel.value = lignes.slice(-MAX_LOGS_LIGNES).join('\n');
        }

        // Auto-scroll vers le bas
        panel.scrollTop = panel.scrollHeight;
    }

    function mettreAJourProgression(data) {
        var pct = data.progress || 0;
        var bar = document.getElementById('progressBar');
        var pctLabel = document.getElementById('progressPct');
        var text = document.getElementById('progressText');
        if (bar) bar.style.width = pct + '%';
        if (pctLabel) pctLabel.textContent = pct > 0 ? pct + '%' : '';
        if (text) text.textContent = (data.verifiees || 0) + ' / ' + (data.total || 0) + ' URLs verifiees';
    }

    function chargerResultatsHttp() {
        fetch(baseUrl + '/download.php?job=' + encodeURIComponent(jobId) + '&format=json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) {
            if (!r.ok) {
                return r.text().then(function (t) { throw new Error('HTTP ' + r.status + ': ' + t); });
            }
            return r.text().then(function (t) {
                try { return JSON.parse(t); }
                catch (e) { throw new Error('JSON invalide: ' + t.substring(0, 200)); }
            });
        })
        .then(function (data) {
            // Normaliser la structure (download.php renvoie { analyse, verificationsHttp, mappingUrls })
            resultatsHttp = {
                verificationsHttp: data.verificationsHttp || {},
                mappingDestinations: data.mappingDestinations || {}
            };

            // Si analyse n'est pas encore chargee, la recuperer depuis la reponse
            if (!analyse && data.analyse) {
                analyse = data.analyse;
            }

            // --- DEBUG LOG apres polling ---
            console.log('[RedirectsChecker] chargerResultatsHttp |',
                Object.keys(resultatsHttp.verificationsHttp).length, 'verifs |',
                'analyse:', analyse ? (analyse.paires || []).length + ' paires' : 'null');
            if (analyse && analyse.paires) {
                analyse.paires.slice(0, 3).forEach(function (p, i) {
                    var verif = chercherVerifHttp(p.source);
                    console.log('  Paire #' + i, '| src:', p.source, '| verif:', verif ? ('HTTP ' + verif.statut) : 'null');
                });
            }
            // --- FIN DEBUG ---

            document.getElementById('progressSection').classList.add('d-none');

            // Mettre a jour les KPI avec les erreurs HTTP
            var nbErreurs = compterErreursHttp();
            setText('kpi404', formaterNombre(nbErreurs));
            setKpiClass('kpi404Card', nbErreurs);

            // Mettre a jour le KPI "Redirections 3xx"
            var nb3xx = compter3xx();
            if (nb3xx > 0) {
                setText('kpiTotal', formaterNombre(nb3xx));
            }

            // Mettre a jour le KPI "Chaines" avec les chaines crawl
            if (analyse) {
                var chainesCrawl = compterChainesCrawl();
                var totalChaines = (analyse.resume ? analyse.resume.chaines : 0) + chainesCrawl;
                setText('kpiChaines', formaterNombre(totalChaines));
                setKpiClass('kpiChainesCard', totalChaines);
            }

            // Reconstruire les tables
            construireTableProblemes();
            construireTableListe();
        })
        .catch(function (err) {
            console.error('[RedirectsChecker] Erreur chargerResultatsHttp:', err);
            document.getElementById('progressSection').innerHTML =
                '<div class="card-body"><div class="alert alert-danger mb-0">Impossible de charger les resultats HTTP : ' + escapeHtml(String(err)) + '</div></div>';
        });
    }

    // -- Export CSV --

    function initExport() {
        var btnCorrige = document.getElementById('btnExportCorrige');
        var btnProblemes = document.getElementById('btnExportProblemes');

        if (btnCorrige) {
            btnCorrige.addEventListener('click', function () {
                exporterCsv('corrige');
            });
        }
        if (btnProblemes) {
            btnProblemes.addEventListener('click', function () {
                exporterCsv('problemes');
            });
        }
    }

    function exporterCsv(type) {
        var lignes = [];
        var sep = ';';

        if (type === 'problemes') {
            lignes.push(['Type', 'Source', 'Destination actuelle', 'Detail', 'Correction'].join(sep));

            (window._problemesData || []).forEach(function (p) {
                var corr = corrections[p.source] || p.correction || '';
                lignes.push([
                    csvEchapper(p.labelType),
                    csvEchapper(p.source),
                    csvEchapper(p.destination),
                    csvEchapper(p.detail),
                    csvEchapper(corr)
                ].join(sep));
            });
        } else {
            lignes.push(['Source', 'Destination originale', 'Destination corrigee', 'Raison'].join(sep));

            var aplati = analyse.aplati || {};

            (analyse.paires || []).forEach(function (p) {
                var info = aplati[p.source] || {};
                // Priorite : correction user > aplati > original
                var dest = corrections[p.source] || info.destination || p.destination;

                var raisons = [];

                // Problemes de graphe
                if (info.estBoucle) raisons.push('Boucle');
                if (p.source === p.destination) raisons.push('Auto-redirection');
                if (dest !== p.destination && !info.estBoucle) raisons.push('Chaine aplatie');

                // Problemes HTTP
                var verif = chercherVerifHttp(p.source);
                if (verif) {
                    if (verif.statut && verif.statut >= 400) raisons.push('HTTP ' + verif.statut);
                    if (verif.erreur) raisons.push('Erreur connexion');
                    var destinationAttendue = resoudreDestination(p.destination);
                    if (verif.urlFinale && verif.urlFinale !== destinationAttendue) {
                        raisons.push('Redirect inattendue vers ' + verif.urlFinale);
                    }
                    if (!verif.urlFinale && verif.statut === 200) {
                        raisons.push('Pas de redirection');
                    }
                }

                var raison = raisons.length > 0 ? raisons.join(' | ') : 'Inchange';

                lignes.push([
                    csvEchapper(p.source),
                    csvEchapper(p.destination),
                    csvEchapper(dest),
                    csvEchapper(raison)
                ].join(sep));
            });
        }

        var contenu = '\uFEFF' + lignes.join('\n');
        var blob = new Blob([contenu], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'redirections-' + type + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        afficherToast('Fichier CSV telecharge.');
    }

    // ===========================================
    // PAGINATION VIRTUELLE
    // ===========================================

    function initRechercheInput(inputId, pagin) {
        var input = document.getElementById(inputId);
        if (!input || !pagin) return;
        var debounce = null;
        input.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                pagin.setRecherche(input.value);
            }, 200);
        });
    }

    // Cree un gestionnaire de pagination virtuelle (ne rend que la page courante)
    function creerPagination(tableId, renderLigne) {
        var tbody = document.querySelector('#' + tableId + ' tbody');
        var nav = document.getElementById('pagination-' + tableId);
        var info = document.getElementById('page-info-' + tableId);

        var state = {
            donnees: [],
            filtrees: null,
            page: 1,
            recherche: '',
        };

        function donneesVisibles() {
            if (state.filtrees !== null) return state.filtrees;
            if (!state.recherche) return state.donnees;
            var q = state.recherche;
            state.filtrees = state.donnees.filter(function (d) {
                // Chercher dans les proprietes textuelles de l'objet ou dans la string
                var texte = typeof d === 'string' ? d : JSON.stringify(d);
                return texte.toLowerCase().indexOf(q) !== -1;
            });
            return state.filtrees;
        }

        function render() {
            var donnees = donneesVisibles();
            var total = donnees.length;
            var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            if (state.page > totalPages) state.page = totalPages;
            var start = (state.page - 1) * PAGE_SIZE;
            var end = Math.min(start + PAGE_SIZE, total);

            var html = '';
            for (var i = start; i < end; i++) {
                html += renderLigne(donnees[i], i);
            }
            tbody.innerHTML = html;

            if (info) {
                info.textContent = total > 0
                    ? (start + 1) + ' – ' + end + ' sur ' + formaterNombre(total)
                    : 'Aucun resultat';
            }
            buildNav(totalPages);
        }

        function buildNav(totalPages) {
            if (!nav) return;
            var ul = nav.querySelector('ul');
            ul.innerHTML = '';
            if (totalPages <= 1) return;

            addBtn(ul, '\u00AB', state.page > 1 ? state.page - 1 : null);
            for (var p = 1; p <= totalPages; p++) {
                if (totalPages > 7) {
                    if (p === 1 || p === totalPages || (p >= state.page - 1 && p <= state.page + 1)) {
                        addBtn(ul, String(p), p, p === state.page);
                    } else if (p === state.page - 2 || p === state.page + 2) {
                        var li = document.createElement('li');
                        li.className = 'page-item disabled';
                        li.innerHTML = '<span class="page-link">\u2026</span>';
                        ul.appendChild(li);
                    }
                } else {
                    addBtn(ul, String(p), p, p === state.page);
                }
            }
            addBtn(ul, '\u00BB', state.page < totalPages ? state.page + 1 : null);
        }

        function addBtn(ul, label, target, isActive) {
            var li = document.createElement('li');
            li.className = 'page-item' + (isActive ? ' active' : '') + (target === null ? ' disabled' : '');
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = label;
            if (target !== null) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    state.page = target;
                    render();
                });
            }
            li.appendChild(a);
            ul.appendChild(li);
        }

        return {
            setDonnees: function (d) { state.donnees = d; state.filtrees = null; state.page = 1; render(); },
            setRecherche: function (q) { state.recherche = q.toLowerCase().trim(); state.filtrees = null; state.page = 1; render(); },
            goPage: function (p) { state.page = p; render(); },
            render: render,
        };
    }

    // ===========================================
    // UTILITAIRES
    // ===========================================

    function setText(id, texte) {
        var el = document.getElementById(id);
        if (el) el.textContent = texte;
    }

    function tronquer(str, max) {
        if (!str) return '';
        return str.length > max ? str.substring(0, max) + '\u2026' : str;
    }

    function formaterNombre(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('fr-FR');
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function csvEchapper(str) {
        if (!str) return '';
        if (str.indexOf(';') !== -1 || str.indexOf('"') !== -1 || str.indexOf('\n') !== -1) {
            return '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    }

    function afficherToast(message) {
        var toast = document.getElementById('copyToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'copyToast';
            toast.className = 'copy-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(function () { toast.classList.remove('show'); }, 2000);
    }

})();
