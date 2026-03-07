<?php

test('devrait parser des paires separees par tabulation', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a\t/b\n/c\t/d", 'auto');

    expect($resultat['paires'])->toHaveCount(2);
    expect($resultat['paires'][0])->toBe(['source' => '/a', 'destination' => '/b']);
    expect($resultat['paires'][1])->toBe(['source' => '/c', 'destination' => '/d']);
});

test('devrait parser des paires separees par point-virgule', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a;/b\n/c;/d", ';');

    expect($resultat['paires'])->toHaveCount(2);
    expect($resultat['paires'][0])->toBe(['source' => '/a', 'destination' => '/b']);
});

test('devrait parser des paires separees par virgule', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a,/b\n/c,/d", ',');

    expect($resultat['paires'])->toHaveCount(2);
});

test('devrait auto-detecter le separateur tabulation', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a\t/b\n/c\t/d");

    expect($resultat['paires'])->toHaveCount(2);
    expect($resultat['avertissements'])->toBeEmpty();
});

test('devrait auto-detecter le separateur point-virgule', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a;/b\n/c;/d");

    expect($resultat['paires'])->toHaveCount(2);
});

test('devrait ignorer les lignes vides et commentaires', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("# Commentaire\n/a;/b\n\n/c;/d\n# Autre commentaire");

    expect($resultat['paires'])->toHaveCount(2);
    expect($resultat['avertissements'])->toBeEmpty();
});

test('devrait normaliser les URLs en supprimant le trailing slash', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a/;/b/");

    expect($resultat['paires'][0]['source'])->toBe('/a');
    expect($resultat['paires'][0]['destination'])->toBe('/b');
});

test('devrait conserver le slash racine', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/ancien;/");

    expect($resultat['paires'][0]['destination'])->toBe('/');
});

test('devrait signaler les lignes malformees', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a;/b\nligne-sans-separateur\n/c;/d", ';');

    expect($resultat['paires'])->toHaveCount(2);
    expect($resultat['avertissements'])->toHaveCount(1);
    expect($resultat['avertissements'][0])->toContain('Ligne 2');
});

test('devrait signaler les sources en double', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser("/a;/b\n/a;/c");

    expect($resultat['paires'])->toHaveCount(1);
    expect($resultat['avertissements'])->toHaveCount(1);
    expect($resultat['avertissements'][0])->toContain('source en double');
});

test('devrait gerer le contenu vide', function (): void {
    $analyseur = new AnalyseurRedirections();
    $resultat = $analyseur->analyser('');

    expect($resultat['paires'])->toBeEmpty();
    expect($resultat['avertissements'])->toHaveCount(1);
});

// --- Tests prefixerDomaine ---

test('devrait prefixer le domaine sur les URLs relatives', function (): void {
    $analyseur = new AnalyseurRedirections();
    $paires = [
        ['source' => '/ancienne', 'destination' => '/nouvelle'],
        ['source' => '/page-a', 'destination' => '/page-b'],
    ];

    $resultat = $analyseur->prefixerDomaine($paires, 'https://example.com');

    expect($resultat[0]['source'])->toBe('https://example.com/ancienne');
    expect($resultat[0]['destination'])->toBe('https://example.com/nouvelle');
    expect($resultat[1]['source'])->toBe('https://example.com/page-a');
});

test('devrait ne pas modifier les URLs absolues', function (): void {
    $analyseur = new AnalyseurRedirections();
    $paires = [
        ['source' => 'https://other.com/old', 'destination' => 'https://other.com/new'],
        ['source' => '/relative', 'destination' => 'https://autre.fr/page'],
    ];

    $resultat = $analyseur->prefixerDomaine($paires, 'https://example.com');

    expect($resultat[0]['source'])->toBe('https://other.com/old');
    expect($resultat[0]['destination'])->toBe('https://other.com/new');
    expect($resultat[1]['source'])->toBe('https://example.com/relative');
    expect($resultat[1]['destination'])->toBe('https://autre.fr/page');
});

test('devrait gerer le domaine avec trailing slash', function (): void {
    $analyseur = new AnalyseurRedirections();
    $paires = [['source' => '/page', 'destination' => '/autre']];

    $resultat = $analyseur->prefixerDomaine($paires, 'https://example.com/');

    expect($resultat[0]['source'])->toBe('https://example.com/page');
    expect($resultat[0]['destination'])->toBe('https://example.com/autre');
});

test('devrait gerer les chemins relatifs sans slash initial', function (): void {
    $analyseur = new AnalyseurRedirections();
    $paires = [['source' => 'page-a', 'destination' => 'page-b']];

    $resultat = $analyseur->prefixerDomaine($paires, 'https://example.com');

    expect($resultat[0]['source'])->toBe('https://example.com/page-a');
    expect($resultat[0]['destination'])->toBe('https://example.com/page-b');
});

test('devrait retourner les paires inchangees si domaine vide', function (): void {
    $analyseur = new AnalyseurRedirections();
    $paires = [['source' => '/a', 'destination' => '/b']];

    $resultat = $analyseur->prefixerDomaine($paires, '');

    expect($resultat[0]['source'])->toBe('/a');
    expect($resultat[0]['destination'])->toBe('/b');
});
