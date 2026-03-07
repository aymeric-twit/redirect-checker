<?php

declare(strict_types=1);

class AnalyseurRedirections
{
    private const array SEPARATEURS = ["\t", ';', ','];

    /**
     * Analyse le contenu texte et retourne les paires de redirections.
     * @return array{paires: array<int, array{source: string, destination: string}>, avertissements: string[]}
     */
    public function analyser(string $contenu, string $separateur = 'auto'): array
    {
        $contenuTrimme = trim($contenu);
        if ($contenuTrimme === '') {
            return ['paires' => [], 'avertissements' => ['Contenu vide.']];
        }

        $lignes = preg_split('/\r?\n/', $contenuTrimme);
        if ($lignes === false || $lignes === []) {
            return ['paires' => [], 'avertissements' => ['Contenu vide.']];
        }

        $separateurDetecte = $separateur === 'auto'
            ? $this->detecterSeparateur($lignes[0])
            : $separateur;

        $paires = [];
        $avertissements = [];
        $sourcesVues = [];

        foreach ($lignes as $index => $ligne) {
            $ligne = trim($ligne);
            $numeroLigne = $index + 1;

            if ($ligne === '' || str_starts_with($ligne, '#')) {
                continue;
            }

            $parties = explode($separateurDetecte, $ligne);
            if (count($parties) < 2) {
                $avertissements[] = "Ligne $numeroLigne : format invalide (separateur '$separateurDetecte' non trouve).";
                continue;
            }

            $source = $this->normaliserUrl(trim($parties[0]));
            $destination = $this->normaliserUrl(trim($parties[1]));

            if ($source === '' || $destination === '') {
                $avertissements[] = "Ligne $numeroLigne : URL source ou destination vide.";
                continue;
            }

            if (isset($sourcesVues[$source])) {
                $avertissements[] = "Ligne $numeroLigne : source en double '$source' (premiere occurrence conservee).";
                continue;
            }

            $sourcesVues[$source] = true;
            $paires[] = ['source' => $source, 'destination' => $destination];
        }

        return ['paires' => $paires, 'avertissements' => $avertissements];
    }

    /**
     * Detecte le separateur le plus probable a partir de la premiere ligne.
     */
    private function detecterSeparateur(string $premiereLigne): string
    {
        foreach (self::SEPARATEURS as $sep) {
            if (str_contains($premiereLigne, $sep)) {
                return $sep;
            }
        }
        return ';';
    }

    /**
     * Prefixe un domaine sur les URLs relatives d'une liste de paires.
     * @param array<int, array{source: string, destination: string}> $paires
     * @return array<int, array{source: string, destination: string}>
     */
    public function prefixerDomaine(array $paires, string $domaine): array
    {
        $domaine = rtrim(trim($domaine), '/');
        if ($domaine === '') {
            return $paires;
        }

        return array_map(function (array $paire) use ($domaine): array {
            return [
                'source' => $this->ajouterDomaine($paire['source'], $domaine),
                'destination' => $this->ajouterDomaine($paire['destination'], $domaine),
            ];
        }, $paires);
    }

    /**
     * Ajoute le domaine a une URL si elle est relative.
     */
    private function ajouterDomaine(string $url, string $domaine): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
            return $domaine . '/' . $url;
        }

        return $domaine . $url;
    }

    /**
     * Normalise une URL : trim, suppression du trailing slash sauf pour la racine.
     */
    private function normaliserUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || $url === '/') {
            return $url;
        }

        return rtrim($url, '/');
    }
}
