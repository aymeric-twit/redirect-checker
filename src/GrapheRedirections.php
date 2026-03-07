<?php

declare(strict_types=1);

class GrapheRedirections
{
    private const int PROFONDEUR_MAX = 50;

    /** @var array<string, string> source -> destination */
    private array $adjacence = [];

    /** @param array<int, array{source: string, destination: string}> $paires */
    public function __construct(array $paires)
    {
        foreach ($paires as $paire) {
            if (!isset($this->adjacence[$paire['source']])) {
                $this->adjacence[$paire['source']] = $paire['destination'];
            }
        }
    }

    /**
     * Detecte les chaines de redirections (longueur >= 2 sauts).
     * @return array<int, array{chaine: string[], longueur: int, correction: array{source: string, destination: string}}>
     */
    public function detecterChaines(): array
    {
        $chaines = [];
        $dejaTraite = [];

        foreach ($this->adjacence as $source => $destination) {
            if (isset($dejaTraite[$source])) {
                continue;
            }
            if ($source === $destination) {
                continue;
            }

            $chemin = [$source];
            $visite = [$source => true];
            $courant = $destination;
            $estBoucle = false;

            while (isset($this->adjacence[$courant]) && count($chemin) < self::PROFONDEUR_MAX) {
                if (isset($visite[$courant])) {
                    $estBoucle = true;
                    break;
                }
                $chemin[] = $courant;
                $visite[$courant] = true;
                $courant = $this->adjacence[$courant];
            }
            $chemin[] = $courant;

            if ($estBoucle || count($chemin) < 3) {
                continue;
            }

            $chaines[] = [
                'chaine' => $chemin,
                'longueur' => count($chemin) - 1,
                'correction' => [
                    'source' => $source,
                    'destination' => $courant,
                ],
            ];

            foreach ($chemin as $noeud) {
                $dejaTraite[$noeud] = true;
            }
        }

        return $chaines;
    }

    /**
     * Detecte les boucles (cycles) dans le graphe.
     * @return array<int, array{boucle: string[], longueur: int}>
     */
    public function detecterBoucles(): array
    {
        $boucles = [];
        $couleur = []; // 0=blanc, 1=gris, 2=noir

        foreach ($this->adjacence as $source => $dest) {
            if ($source === $dest) {
                $couleur[$source] = 2;
                continue;
            }
            if (isset($couleur[$source]) && $couleur[$source] === 2) {
                continue;
            }

            $pile = [];
            $courant = $source;

            while ($courant !== null) {
                if (isset($couleur[$courant]) && $couleur[$courant] === 2) {
                    break;
                }

                if (isset($couleur[$courant]) && $couleur[$courant] === 1) {
                    $cycle = [];
                    foreach ($pile as $noeud) {
                        if (count($cycle) > 0 || $noeud === $courant) {
                            $cycle[] = $noeud;
                        }
                    }
                    $cycle[] = $courant;

                    if (count($cycle) >= 2) {
                        $boucles[] = [
                            'boucle' => $cycle,
                            'longueur' => count($cycle) - 1,
                        ];
                    }

                    foreach ($pile as $noeud) {
                        $couleur[$noeud] = 2;
                    }
                    break;
                }

                $couleur[$courant] = 1;
                $pile[] = $courant;
                $courant = $this->adjacence[$courant] ?? null;
            }

            foreach ($pile as $noeud) {
                $couleur[$noeud] = 2;
            }
        }

        return $boucles;
    }

    /**
     * Detecte les auto-redirections (source === destination).
     * @return string[]
     */
    public function detecterAutoRedirections(): array
    {
        $autoRedirections = [];
        foreach ($this->adjacence as $source => $destination) {
            if ($source === $destination) {
                $autoRedirections[] = $source;
            }
        }
        return $autoRedirections;
    }

    /**
     * Aplatit les chaines : chaque source pointe vers sa destination finale.
     * @return array<string, array{destination: string, estBoucle: bool}>
     */
    public function aplatirChaines(): array
    {
        $resultat = [];

        foreach ($this->adjacence as $source => $destination) {
            if ($source === $destination) {
                $resultat[$source] = ['destination' => $destination, 'estBoucle' => true];
                continue;
            }

            $courant = $destination;
            $visite = [$source => true];
            $profondeur = 0;
            $estBoucle = false;

            while (isset($this->adjacence[$courant]) && $profondeur < self::PROFONDEUR_MAX) {
                if (isset($visite[$courant])) {
                    $estBoucle = true;
                    break;
                }
                $visite[$courant] = true;
                $courant = $this->adjacence[$courant];
                $profondeur++;
            }

            $resultat[$source] = [
                'destination' => $courant,
                'estBoucle' => $estBoucle,
            ];
        }

        return $resultat;
    }

    /**
     * Resume statistique du graphe.
     * @return array{total: int, chaines: int, boucles: int, autoRedirections: int}
     */
    public function genererResume(): array
    {
        return [
            'total' => count($this->adjacence),
            'chaines' => count($this->detecterChaines()),
            'boucles' => count($this->detecterBoucles()),
            'autoRedirections' => count($this->detecterAutoRedirections()),
        ];
    }

    /** @return array<string, string> */
    public function getAdjacence(): array
    {
        return $this->adjacence;
    }
}
