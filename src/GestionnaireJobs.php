<?php

declare(strict_types=1);

class GestionnaireJobs
{
    private string $repertoireJobs;

    public function __construct(?string $repertoireJobs = null)
    {
        $this->repertoireJobs = $repertoireJobs ?? __DIR__ . '/../data/jobs';
    }

    /**
     * Cree un nouveau job et retourne son identifiant.
     */
    public function creerJob(): string
    {
        $jobId = bin2hex(random_bytes(12));
        $chemin = $this->cheminJob($jobId);
        if (!is_dir($chemin)) {
            mkdir($chemin, 0755, true);
        }
        return $jobId;
    }

    /**
     * Sauvegarde les donnees de configuration du job.
     * @param array<string, mixed> $config
     */
    public function sauvegarderConfig(string $jobId, array $config): void
    {
        $chemin = $this->cheminJob($jobId) . '/config.json';
        file_put_contents($chemin, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Sauvegarde les donnees d'analyse (redirections parsees + resultats graphe).
     * @param array<string, mixed> $donnees
     */
    public function sauvegarderAnalyse(string $jobId, array $donnees): void
    {
        $chemin = $this->cheminJob($jobId) . '/analyse.json';
        file_put_contents($chemin, json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Ecrit la progression de maniere atomique (via fichier .tmp + rename).
     * @param array<string, mixed> $donnees
     */
    public function ecrireProgression(string $jobId, array $donnees): void
    {
        $chemin = $this->cheminJob($jobId) . '/progress.json';
        $contenu = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $cheminTemp = $chemin . '.tmp';
        file_put_contents($cheminTemp, $contenu, LOCK_EX);
        rename($cheminTemp, $chemin);
    }

    /**
     * Lit la progression du job.
     * @return array<string, mixed>|null
     */
    public function lireProgression(string $jobId): ?array
    {
        $chemin = $this->cheminJob($jobId) . '/progress.json';
        if (!file_exists($chemin)) {
            return null;
        }
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            return null;
        }
        $donnees = json_decode($contenu, true);
        return is_array($donnees) ? $donnees : null;
    }

    /**
     * Sauvegarde les resultats finaux du worker.
     * @param array<string, mixed> $resultats
     */
    public function sauvegarderResultats(string $jobId, array $resultats): void
    {
        $chemin = $this->cheminJob($jobId) . '/resultats.json';
        file_put_contents($chemin, json_encode($resultats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Lit les resultats du job.
     * @return array<string, mixed>|null
     */
    public function lireResultats(string $jobId): ?array
    {
        $chemin = $this->cheminJob($jobId) . '/resultats.json';
        if (!file_exists($chemin)) {
            return null;
        }
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            return null;
        }
        $donnees = json_decode($contenu, true);
        return is_array($donnees) ? $donnees : null;
    }

    /**
     * Lit l'analyse sauvegardee du job.
     * @return array<string, mixed>|null
     */
    public function lireAnalyse(string $jobId): ?array
    {
        $chemin = $this->cheminJob($jobId) . '/analyse.json';
        if (!file_exists($chemin)) {
            return null;
        }
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            return null;
        }
        $donnees = json_decode($contenu, true);
        return is_array($donnees) ? $donnees : null;
    }

    /**
     * Supprime les jobs de plus de 72 heures.
     */
    public function nettoyerAnciensJobs(): int
    {
        $supprimes = 0;
        $repertoire = $this->repertoireJobs;

        if (!is_dir($repertoire)) {
            return 0;
        }

        $limite = time() - 259200; // 72 heures
        $elements = scandir($repertoire);
        if ($elements === false) {
            return 0;
        }

        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }
            $cheminElement = $repertoire . '/' . $element;
            if (!is_dir($cheminElement)) {
                continue;
            }
            if (filemtime($cheminElement) < $limite) {
                $this->supprimerRepertoire($cheminElement);
                $supprimes++;
            }
        }

        return $supprimes;
    }

    /**
     * Liste tous les jobs existants, tries par date de creation descendante.
     * @return array<int, array{jobId: string, dateCreation: string, totalPaires: int, domaine: string, statut: string, verifier404: bool}>
     */
    public function listerJobs(): array
    {
        $repertoire = $this->repertoireJobs;
        if (!is_dir($repertoire)) {
            return [];
        }

        $elements = scandir($repertoire);
        if ($elements === false) {
            return [];
        }

        $jobs = [];
        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }
            $cheminElement = $repertoire . '/' . $element;
            if (!is_dir($cheminElement)) {
                continue;
            }

            $cheminConfig = $cheminElement . '/config.json';
            if (!file_exists($cheminConfig)) {
                continue;
            }

            $contenuConfig = file_get_contents($cheminConfig);
            if ($contenuConfig === false) {
                continue;
            }
            $config = json_decode($contenuConfig, true);
            if (!is_array($config)) {
                continue;
            }

            $cheminProgress = $cheminElement . '/progress.json';
            $statut = 'en_cours';
            $urlsCrawlees = 0;
            if (file_exists($cheminProgress)) {
                $contenuProgress = file_get_contents($cheminProgress);
                if ($contenuProgress !== false) {
                    $progress = json_decode($contenuProgress, true);
                    if (is_array($progress)) {
                        $statusBrut = $progress['status'] ?? '';
                        if ($statusBrut === 'done') {
                            $statut = 'termine';
                        } elseif ($statusBrut === 'error') {
                            $statut = 'erreur';
                        }
                        $urlsCrawlees = (int) ($progress['verifiees'] ?? 0);
                    }
                }
            } elseif (!($config['verifier_404'] ?? false)) {
                $statut = 'termine';
            }

            $domaine = $config['domaine'] ?? '';
            if ($domaine === '') {
                $cheminAnalyse = $cheminElement . '/analyse.json';
                if (file_exists($cheminAnalyse)) {
                    $contenuAnalyse = file_get_contents($cheminAnalyse);
                    if ($contenuAnalyse !== false) {
                        $analyseData = json_decode($contenuAnalyse, true);
                        if (is_array($analyseData)) {
                            $premiereSource = $analyseData['paires'][0]['source'] ?? '';
                            if ($premiereSource !== '') {
                                $parsed = parse_url($premiereSource);
                                $domaine = isset($parsed['host'])
                                    ? ($parsed['scheme'] ?? 'https') . '://' . $parsed['host']
                                    : '';
                            }
                        }
                    }
                }
            }

            $jobs[] = [
                'jobId' => $element,
                'dateCreation' => $config['date_creation'] ?? date('Y-m-d H:i:s', (int) filemtime($cheminElement)),
                'totalPaires' => (int) ($config['total_paires'] ?? 0),
                'domaine' => $domaine,
                'statut' => $statut,
                'verifier404' => (bool) ($config['verifier_404'] ?? false),
                'urlsCrawlees' => $urlsCrawlees,
            ];
        }

        usort($jobs, function (array $a, array $b): int {
            return strcmp($b['dateCreation'], $a['dateCreation']);
        });

        return $jobs;
    }

    /**
     * Valide un identifiant de job (format hexadecimal).
     */
    public static function validerJobId(string $jobId): bool
    {
        return preg_match('/^[a-f0-9]{13,32}$/', $jobId) === 1;
    }

    public function cheminJob(string $jobId): string
    {
        return $this->repertoireJobs . '/' . $jobId;
    }

    private function supprimerRepertoire(string $chemin): void
    {
        $elements = scandir($chemin);
        if ($elements === false) {
            return;
        }
        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }
            $cheminComplet = $chemin . '/' . $element;
            if (is_dir($cheminComplet)) {
                $this->supprimerRepertoire($cheminComplet);
            } else {
                unlink($cheminComplet);
            }
        }
        rmdir($chemin);
    }
}
