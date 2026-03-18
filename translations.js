var TRANSLATIONS = {
    fr: {
        // -- Navbar --
        'nav.titre':                'Redirects Checker',
        'nav.sousTitre':            'Chaines, boucles & 404',
        'nav.sousTitreResultats':   'Resultats de l\u2019analyse',

        // -- Onglets principaux --
        'tab.nouvelleAnalyse':      '<i class="bi bi-plus-circle me-1"></i>Nouvelle analyse',
        'tab.historique':           '<i class="bi bi-clock-history me-1"></i>Historique',

        // -- Carte import --
        'form.titreImport':         '<i class="bi bi-upload me-2"></i>Importer les redirections',
        'tab.collerTexte':          '<i class="bi bi-textarea-t me-1"></i>Coller le texte',
        'tab.importerFichier':      '<i class="bi bi-file-earmark-arrow-up me-1"></i>Importer un fichier',
        'form.labelRedirections':   'Liste des redirections (source \u2192 destination)',
        'form.placeholderTexte':    '/ancienne-page;/nouvelle-page\n/ancien-produit;/nouveau-produit\nhttps://example.com/old;https://example.com/new',
        'form.aideTexte':           'Une redirection par ligne. Formats acceptes : tabulation, point-virgule ou virgule comme separateur.',
        'form.labelFichier':        'Fichier CSV / TSV',
        'form.aideFichier':         'Fichier avec 2 colonnes : source et destination. Max 5 Mo.',
        'form.labelSeparateur':     'Separateur',
        'form.autoDetection':       'Auto-detection',
        'form.tabulation':          'Tabulation',
        'form.pointVirgule':        'Point-virgule (;)',
        'form.virgule':             'Virgule (,)',
        'form.verifier404':         'Verifier les 404 (HTTP)',
        'form.optionsAvancees':     '<i class="bi bi-gear me-1"></i>Options avancees du crawler',
        'form.configsSauvegardees': '-- Configs sauvegardees --',
        'form.nomConfig':           'Nom de la config',
        'form.sauvegarder':         '<i class="bi bi-save me-1"></i>Sauvegarder',
        'form.analyser':            '<i class="bi bi-play-fill me-1"></i>Analyser les redirections',

        // -- Options avancees --
        'options.concurrence':      'Requetes concurrentes',
        'options.delai':            'Delai entre requetes',
        'options.aucun':            'Aucun',
        'options.timeout':          'Timeout par requete',
        'options.maxRedirections':  'Max redirections suivies',
        'options.nePasSuivre':      'Ne pas suivre',
        'options.userAgent':        'User-Agent',
        'options.personnalise':     'Personnalise',
        'options.headerCustom':     'Header HTTP custom (optionnel)',

        // -- Modale domaine --
        'modal.titreDomaine':       '<i class="bi bi-globe me-1"></i>Domaine requis',
        'modal.descDomaine':        'Des URLs relatives ont ete detectees. Indiquez le domaine pour les prefixer.',
        'modal.placeholderDomaine': 'https://example.com',
        'modal.continuer':          '<i class="bi bi-play-fill me-1"></i>Continuer',

        // -- Historique --
        'historique.chargement':    '<span class="spinner-border spinner-border-sm me-2"></span>Chargement...',
        'historique.vide':          '<i class="bi bi-inbox me-1"></i>Aucune analyse enregistree.',
        'historique.info':          '<i class="bi bi-info-circle me-1"></i>Les analyses sont automatiquement supprimees apres 72h.',
        'historique.date':          'Date',
        'historique.domaine':       'Domaine',
        'historique.urls':          'URLs',
        'historique.crawlees':      'Crawlees',
        'historique.statut':        'Statut',
        'historique.action':        'Action',
        'historique.voir':          'Voir',
        'historique.termine':       'Termine',
        'historique.erreur':        'Erreur',
        'historique.enCours':       'En cours',
        'historique.erreurChargement': 'Erreur de chargement.',

        // -- Resultats : lien retour --
        'btn.retour':               '<i class="bi bi-arrow-left me-1"></i>Nouvelle analyse',

        // -- Resultats : progression --
        'progress.titre':           '<i class="bi bi-hourglass-split me-2"></i>Verification HTTP en cours...',
        'progress.demarrage':       'Demarrage...',
        'progress.urlsVerifiees':   '{verifiees} / {total} URLs verifiees',

        // -- Resultats : KPI --
        'kpi.redirections3xx':      'Redirections 3xx',
        'kpi.chaines':              'Chaines',
        'kpi.boucles':              'Boucles',
        'kpi.erreursHttp':          'Erreurs HTTP',
        'kpi.corrections':          'Corrections',

        // -- Resultats : onglets --
        'tab.problemes':            '<i class="bi bi-exclamation-diamond me-1"></i>Problemes',
        'tab.listeComplete':        '<i class="bi bi-list-ul me-1"></i>Liste complete',
        'tab.avertissements':       '<i class="bi bi-exclamation-triangle me-1"></i>Avertissements',

        // -- Resultats : filtres --
        'filtre.tous':              'Tous',
        'filtre.chaines':           '<i class="bi bi-link-45deg me-1"></i>Chaines',
        'filtre.boucles':           '<i class="bi bi-arrow-repeat me-1"></i>Boucles',
        'filtre.404':               '<i class="bi bi-x-circle me-1"></i>404',
        'filtre.httpErreur':        '<i class="bi bi-exclamation-octagon me-1"></i>Erreur HTTP',
        'filtre.auto':              '<i class="bi bi-arrow-return-right me-1"></i>Auto-redirect',
        'filtre.redirect':          '<i class="bi bi-shuffle me-1"></i>Redirect inattendue',
        'filtre.rechercher':        'Rechercher...',
        'btn.appliquerTout':        '<i class="bi bi-check2-all me-1"></i>Appliquer toutes les corrections',

        // -- Resultats : table problemes --
        'table.type':               'Type',
        'table.source':             'Source',
        'table.destActuelle':       'Destination actuelle',
        'table.detail':             'Detail',
        'table.destCorrigee':       'Destination corrigee',
        'table.aucunProbleme':      '<i class="bi bi-check-circle text-success me-1"></i>Aucun probleme detecte.',

        // -- Resultats : table liste --
        'table.destination':        'Destination',
        'table.statut':             'Statut',
        'table.httpSource':         'HTTP Src',
        'table.http':               'HTTP Dest',

        // -- Resultats : table avertissements --
        'table.avertissement':      'Avertissement',

        // -- Resultats : export --
        'btn.exportCorrections':    '<i class="bi bi-download me-1"></i>Exporter les corrections (CSV)',
        'btn.exportProblemes':      '<i class="bi bi-download me-1"></i>Exporter les problemes (CSV)',

        // -- Statuts / badges JS --
        'status.chaine':            'Chaine',
        'status.boucle':            'Boucle',
        'status.auto':              'Auto',
        'status.redirect':          'Redirect',
        'status.ok':                'OK',
        'status.nonVerifie':        'Non verifie',
        'status.pasDeRedir':        'Pas de redir',
        'status.erreur':            'Erreur',

        // -- Messages JS --
        'msg.envoiEnCours':         'Envoi en cours\u2026',
        'msg.lectureFichier':       'Lecture du fichier\u2026',
        'msg.envoiDonnees':         'Envoi des donnees...',
        'msg.envoiFichier':         'Envoi du fichier... {pct}%',
        'msg.analyseEnCours':       'Analyse des redirections...',
        'msg.analyseRedirections':  'Analyse en cours\u2026',
        'msg.erreurAnalyse':        'Erreur lors de l\u2019analyse.',
        'msg.erreurConfig':         'Erreur lors de la sauvegarde de la configuration.',
        'msg.erreurReseau':         'Erreur reseau lors de l\u2019envoi.',
        'msg.erreurLectureFichier': 'Impossible de lire le fichier.',
        'msg.quotaEpuise':          'Quota mensuel epuise.',
        'msg.fichierLecture':       'Lecture du fichier...',
        'msg.fichierLignes':        'lignes',
        'msg.configSauvegardee':    'Config "{nom}" sauvegardee.',
        'msg.configChargee':        'Config "{nom}" chargee.',
        'msg.configSupprimee':      'Config "{nom}" supprimee.',
        'msg.correctionsAppliquees': '{nb} correction(s) appliquee(s).',
        'msg.csvTelecharge':        'Fichier CSV telecharge.',
        'msg.erreurWorker':         'Erreur worker',
        'msg.erreurChargementHttp': 'Impossible de charger les resultats HTTP : ',
        'msg.aucunResultat':        'Aucun resultat',
        'msg.surTotal':             '{debut} \u2013 {fin} sur {total}',
        'msg.cliquerPourCorriger':  'Cliquer pour corriger',
        'msg.aResoudreManuellement':'A resoudre manuellement',
        'msg.nouvelleDest':         'Nouvelle destination',
        'msg.sourceEgalDest':       'Source = Destination',
        'msg.urlFinale':            'URL finale : {urlFinale} (attendue : {attendue})',
        'msg.pasDeRedirection':     'La source repond 200 sans rediriger (redirection non configuree)',
        'msg.erreurConnexion':      'Erreur connexion',
        'msg.redirectInattendue':   'Redirect inattendue vers {url}',
        'msg.inchange':             'Inchange',
        'msg.chaineAplatie':        'Chaine aplatie',
        'msg.autoRedirection':      'Auto-redirection',
        'msg.pasDeRedirectionCourt':'Pas de redirection',

        // -- CSV export headers --
        'csv.type':                 'Type',
        'csv.source':               'Source',
        'csv.destActuelle':         'Destination actuelle',
        'csv.detail':               'Detail',
        'csv.correction':           'Correction',
        'csv.destOriginale':        'Destination originale',
        'csv.destCorrigee':         'Destination corrigee',
        'csv.raison':               'Raison',

        // -- Help panel --
        'help.titre_comment':       '<i class="bi bi-info-circle me-1"></i> Comment ca marche',
        'help.etape1':              '<strong>1.</strong> Collez vos redirections (source \u2192 destination) ou importez un fichier CSV/TSV.',
        'help.etape2':              '<strong>2.</strong> Cochez <em>Verifier les 404</em> pour crawler les URLs et verifier les codes HTTP reels.',
        'help.etape3':              '<strong>3.</strong> Cliquez sur <em>Analyser</em>. L\u2019outil detecte chaines, boucles, 404 et redirections inattendues.',
        'help.etape4':              '<strong>4.</strong> Corrigez les destinations et exportez le CSV corrige.',
        'help.titre_fonctionnalites': '<i class="bi bi-lightbulb me-1"></i> Fonctionnalites <i class="bi bi-chevron-down help-chevron ms-1"></i>',
        'help.fonc_chaines':        'Detection des chaines de redirections (A \u2192 B \u2192 C) avec aplatissement automatique.',
        'help.fonc_boucles':        'Detection des boucles infinies et auto-redirections.',
        'help.fonc_http':           'Verification HTTP concurrente (HEAD + fallback GET) avec codes source et destination.',
        'help.fonc_crawler':        'Crawler configurable : concurrence, delai, timeout, User-Agent, headers custom.',
        'help.fonc_export':         'Export CSV des corrections et des problemes.',
        'help.fonc_configs':        'Sauvegarde des presets de configuration du crawler.',
        'help.titre_limites':       '<i class="bi bi-speedometer2 me-1"></i> Limites',
        'help.limite_fichier':      'Fichier max : <strong>5 Mo</strong> / <strong>50 000 redirections</strong>',
        'help.limite_historique':   'Historique conserve <strong>72 heures</strong>',
        'help.limite_configs':      'Jusqu\u2019a <strong>50 configs</strong> sauvegardees',
        'help.voirPlus':            'Voir plus',
        'help.reduire':             'Reduire',
    },

    en: {
        // -- Navbar --
        'nav.titre':                'Redirects Checker',
        'nav.sousTitre':            'Chains, loops & 404s',
        'nav.sousTitreResultats':   'Analysis results',

        // -- Onglets principaux --
        'tab.nouvelleAnalyse':      '<i class="bi bi-plus-circle me-1"></i>New analysis',
        'tab.historique':           '<i class="bi bi-clock-history me-1"></i>History',

        // -- Carte import --
        'form.titreImport':         '<i class="bi bi-upload me-2"></i>Import redirects',
        'tab.collerTexte':          '<i class="bi bi-textarea-t me-1"></i>Paste text',
        'tab.importerFichier':      '<i class="bi bi-file-earmark-arrow-up me-1"></i>Upload a file',
        'form.labelRedirections':   'Redirect list (source \u2192 destination)',
        'form.placeholderTexte':    '/old-page;/new-page\n/old-product;/new-product\nhttps://example.com/old;https://example.com/new',
        'form.aideTexte':           'One redirect per line. Accepted separators: tab, semicolon or comma.',
        'form.labelFichier':        'CSV / TSV file',
        'form.aideFichier':         'File with 2 columns: source and destination. Max 5 MB.',
        'form.labelSeparateur':     'Separator',
        'form.autoDetection':       'Auto-detect',
        'form.tabulation':          'Tab',
        'form.pointVirgule':        'Semicolon (;)',
        'form.virgule':             'Comma (,)',
        'form.verifier404':         'Check for 404s (HTTP)',
        'form.optionsAvancees':     '<i class="bi bi-gear me-1"></i>Advanced crawler options',
        'form.configsSauvegardees': '-- Saved configs --',
        'form.nomConfig':           'Config name',
        'form.sauvegarder':         '<i class="bi bi-save me-1"></i>Save',
        'form.analyser':            '<i class="bi bi-play-fill me-1"></i>Analyze redirects',

        // -- Options avancees --
        'options.concurrence':      'Concurrent requests',
        'options.delai':            'Delay between requests',
        'options.aucun':            'None',
        'options.timeout':          'Timeout per request',
        'options.maxRedirections':  'Max followed redirects',
        'options.nePasSuivre':      'Do not follow',
        'options.userAgent':        'User-Agent',
        'options.personnalise':     'Custom',
        'options.headerCustom':     'Custom HTTP header (optional)',

        // -- Modale domaine --
        'modal.titreDomaine':       '<i class="bi bi-globe me-1"></i>Domain required',
        'modal.descDomaine':        'Relative URLs were detected. Please specify the domain to prefix them.',
        'modal.placeholderDomaine': 'https://example.com',
        'modal.continuer':          '<i class="bi bi-play-fill me-1"></i>Continue',

        // -- Historique --
        'historique.chargement':    '<span class="spinner-border spinner-border-sm me-2"></span>Loading...',
        'historique.vide':          '<i class="bi bi-inbox me-1"></i>No analysis recorded.',
        'historique.info':          '<i class="bi bi-info-circle me-1"></i>Analyses are automatically deleted after 72 hours.',
        'historique.date':          'Date',
        'historique.domaine':       'Domain',
        'historique.urls':          'URLs',
        'historique.crawlees':      'Crawled',
        'historique.statut':        'Status',
        'historique.action':        'Action',
        'historique.voir':          'View',
        'historique.termine':       'Completed',
        'historique.erreur':        'Error',
        'historique.enCours':       'In progress',
        'historique.erreurChargement': 'Loading error.',

        // -- Resultats : lien retour --
        'btn.retour':               '<i class="bi bi-arrow-left me-1"></i>New analysis',

        // -- Resultats : progression --
        'progress.titre':           '<i class="bi bi-hourglass-split me-2"></i>HTTP verification in progress...',
        'progress.demarrage':       'Starting...',
        'progress.urlsVerifiees':   '{verifiees} / {total} URLs checked',

        // -- Resultats : KPI --
        'kpi.redirections3xx':      '3xx Redirects',
        'kpi.chaines':              'Chains',
        'kpi.boucles':              'Loops',
        'kpi.erreursHttp':          'HTTP Errors',
        'kpi.corrections':          'Corrections',

        // -- Resultats : onglets --
        'tab.problemes':            '<i class="bi bi-exclamation-diamond me-1"></i>Issues',
        'tab.listeComplete':        '<i class="bi bi-list-ul me-1"></i>Full list',
        'tab.avertissements':       '<i class="bi bi-exclamation-triangle me-1"></i>Warnings',

        // -- Resultats : filtres --
        'filtre.tous':              'All',
        'filtre.chaines':           '<i class="bi bi-link-45deg me-1"></i>Chains',
        'filtre.boucles':           '<i class="bi bi-arrow-repeat me-1"></i>Loops',
        'filtre.404':               '<i class="bi bi-x-circle me-1"></i>404',
        'filtre.httpErreur':        '<i class="bi bi-exclamation-octagon me-1"></i>HTTP Error',
        'filtre.auto':              '<i class="bi bi-arrow-return-right me-1"></i>Auto-redirect',
        'filtre.redirect':          '<i class="bi bi-shuffle me-1"></i>Unexpected redirect',
        'filtre.rechercher':        'Search...',
        'btn.appliquerTout':        '<i class="bi bi-check2-all me-1"></i>Apply all corrections',

        // -- Resultats : table problemes --
        'table.type':               'Type',
        'table.source':             'Source',
        'table.destActuelle':       'Current destination',
        'table.detail':             'Detail',
        'table.destCorrigee':       'Corrected destination',
        'table.aucunProbleme':      '<i class="bi bi-check-circle text-success me-1"></i>No issues detected.',

        // -- Resultats : table liste --
        'table.destination':        'Destination',
        'table.statut':             'Status',
        'table.httpSource':         'HTTP Src',
        'table.http':               'HTTP Dest',

        // -- Resultats : table avertissements --
        'table.avertissement':      'Warning',

        // -- Resultats : export --
        'btn.exportCorrections':    '<i class="bi bi-download me-1"></i>Export corrections (CSV)',
        'btn.exportProblemes':      '<i class="bi bi-download me-1"></i>Export issues (CSV)',

        // -- Statuts / badges JS --
        'status.chaine':            'Chain',
        'status.boucle':            'Loop',
        'status.auto':              'Auto',
        'status.redirect':          'Redirect',
        'status.ok':                'OK',
        'status.nonVerifie':        'Not checked',
        'status.pasDeRedir':        'No redirect',
        'status.erreur':            'Error',

        // -- Messages JS --
        'msg.envoiEnCours':         'Sending\u2026',
        'msg.lectureFichier':       'Reading file\u2026',
        'msg.envoiDonnees':         'Sending data...',
        'msg.envoiFichier':         'Uploading file... {pct}%',
        'msg.analyseEnCours':       'Analyzing redirects...',
        'msg.analyseRedirections':  'Analyzing\u2026',
        'msg.erreurAnalyse':        'Error during analysis.',
        'msg.erreurConfig':         'Error saving configuration.',
        'msg.erreurReseau':         'Network error during upload.',
        'msg.erreurLectureFichier': 'Unable to read file.',
        'msg.quotaEpuise':          'Monthly quota exhausted.',
        'msg.fichierLecture':       'Reading file...',
        'msg.fichierLignes':        'lines',
        'msg.configSauvegardee':    'Config "{nom}" saved.',
        'msg.configChargee':        'Config "{nom}" loaded.',
        'msg.configSupprimee':      'Config "{nom}" deleted.',
        'msg.correctionsAppliquees': '{nb} correction(s) applied.',
        'msg.csvTelecharge':        'CSV file downloaded.',
        'msg.erreurWorker':         'Worker error',
        'msg.erreurChargementHttp': 'Unable to load HTTP results: ',
        'msg.aucunResultat':        'No results',
        'msg.surTotal':             '{debut} \u2013 {fin} of {total}',
        'msg.cliquerPourCorriger':  'Click to correct',
        'msg.aResoudreManuellement':'Resolve manually',
        'msg.nouvelleDest':         'New destination',
        'msg.sourceEgalDest':       'Source = Destination',
        'msg.urlFinale':            'Final URL: {urlFinale} (expected: {attendue})',
        'msg.pasDeRedirection':     'Source responds 200 without redirecting (redirect not configured)',
        'msg.erreurConnexion':      'Connection error',
        'msg.redirectInattendue':   'Unexpected redirect to {url}',
        'msg.inchange':             'Unchanged',
        'msg.chaineAplatie':        'Flattened chain',
        'msg.autoRedirection':      'Self-redirect',
        'msg.pasDeRedirectionCourt':'No redirect',

        // -- CSV export headers --
        'csv.type':                 'Type',
        'csv.source':               'Source',
        'csv.destActuelle':         'Current destination',
        'csv.detail':               'Detail',
        'csv.correction':           'Correction',
        'csv.destOriginale':        'Original destination',
        'csv.destCorrigee':         'Corrected destination',
        'csv.raison':               'Reason',

        // -- Help panel --
        'help.titre_comment':       '<i class="bi bi-info-circle me-1"></i> How it works',
        'help.etape1':              '<strong>1.</strong> Paste your redirections (source \u2192 destination) or upload a CSV/TSV file.',
        'help.etape2':              '<strong>2.</strong> Check <em>Verify 404s</em> to crawl URLs and verify actual HTTP codes.',
        'help.etape3':              '<strong>3.</strong> Click <em>Analyze</em>. The tool detects chains, loops, 404s and unexpected redirects.',
        'help.etape4':              '<strong>4.</strong> Fix destinations and export the corrected CSV.',
        'help.titre_fonctionnalites': '<i class="bi bi-lightbulb me-1"></i> Features <i class="bi bi-chevron-down help-chevron ms-1"></i>',
        'help.fonc_chaines':        'Redirect chain detection (A \u2192 B \u2192 C) with automatic flattening.',
        'help.fonc_boucles':        'Infinite loop and self-redirect detection.',
        'help.fonc_http':           'Concurrent HTTP verification (HEAD + GET fallback) with source and destination codes.',
        'help.fonc_crawler':        'Configurable crawler: concurrency, delay, timeout, User-Agent, custom headers.',
        'help.fonc_export':         'CSV export for corrections and issues.',
        'help.fonc_configs':        'Save crawler configuration presets.',
        'help.titre_limites':       '<i class="bi bi-speedometer2 me-1"></i> Limits',
        'help.limite_fichier':      'Max file: <strong>5 MB</strong> / <strong>50,000 redirections</strong>',
        'help.limite_historique':   'History kept for <strong>72 hours</strong>',
        'help.limite_configs':      'Up to <strong>50 saved configs</strong>',
        'help.voirPlus':            'Show more',
        'help.reduire':             'Show less',
    }
};
