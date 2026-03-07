<?php

test('devrait detecter une chaine simple A->B->C', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
    ]);

    $chaines = $graphe->detecterChaines();

    expect($chaines)->toHaveCount(1);
    expect($chaines[0]['chaine'])->toBe(['/a', '/b', '/c']);
    expect($chaines[0]['longueur'])->toBe(2);
    expect($chaines[0]['correction'])->toBe(['source' => '/a', 'destination' => '/c']);
});

test('devrait detecter une chaine longue A->B->C->D->E', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
        ['source' => '/c', 'destination' => '/d'],
        ['source' => '/d', 'destination' => '/e'],
    ]);

    $chaines = $graphe->detecterChaines();

    expect($chaines)->toHaveCount(1);
    expect($chaines[0]['chaine'])->toBe(['/a', '/b', '/c', '/d', '/e']);
    expect($chaines[0]['longueur'])->toBe(4);
});

test('devrait detecter une boucle A->B->C->A', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
        ['source' => '/c', 'destination' => '/a'],
    ]);

    $boucles = $graphe->detecterBoucles();

    expect($boucles)->toHaveCount(1);
    expect($boucles[0]['boucle'])->toContain('/a', '/b', '/c');
    expect($boucles[0]['longueur'])->toBeGreaterThanOrEqual(2);
});

test('devrait detecter une auto-redirection A->A', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/a'],
        ['source' => '/b', 'destination' => '/c'],
    ]);

    $autoRedirections = $graphe->detecterAutoRedirections();

    expect($autoRedirections)->toBe(['/a']);
});

test('devrait aplatir les chaines correctement', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
        ['source' => '/d', 'destination' => '/e'],
    ]);

    $aplati = $graphe->aplatirChaines();

    expect($aplati['/a']['destination'])->toBe('/c');
    expect($aplati['/a']['estBoucle'])->toBeFalse();
    expect($aplati['/b']['destination'])->toBe('/c');
    expect($aplati['/d']['destination'])->toBe('/e');
});

test('devrait gerer les sources en double (premiere occurrence conservee)', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/a', 'destination' => '/c'],
    ]);

    $adjacence = $graphe->getAdjacence();

    expect($adjacence['/a'])->toBe('/b');
    expect($adjacence)->toHaveCount(1);
});

test('devrait gerer un graphe sans probleme', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/c', 'destination' => '/d'],
        ['source' => '/e', 'destination' => '/f'],
    ]);

    expect($graphe->detecterChaines())->toBeEmpty();
    expect($graphe->detecterBoucles())->toBeEmpty();
    expect($graphe->detecterAutoRedirections())->toBeEmpty();
});

test('devrait marquer les boucles comme non resolvables lors de l aplatissement', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/a'],
    ]);

    $aplati = $graphe->aplatirChaines();

    expect($aplati['/a']['estBoucle'])->toBeTrue();
    expect($aplati['/b']['estBoucle'])->toBeTrue();
});

test('devrait generer un resume correct', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
        ['source' => '/x', 'destination' => '/y'],
        ['source' => '/y', 'destination' => '/x'],
        ['source' => '/z', 'destination' => '/z'],
        ['source' => '/ok1', 'destination' => '/ok2'],
    ]);

    $resume = $graphe->genererResume();

    expect($resume['total'])->toBe(6);
    expect($resume['chaines'])->toBe(1);
    expect($resume['boucles'])->toBe(1);
    expect($resume['autoRedirections'])->toBe(1);
});

test('devrait detecter des chaines independantes', function (): void {
    $graphe = new GrapheRedirections([
        ['source' => '/a', 'destination' => '/b'],
        ['source' => '/b', 'destination' => '/c'],
        ['source' => '/x', 'destination' => '/y'],
        ['source' => '/y', 'destination' => '/z'],
    ]);

    $chaines = $graphe->detecterChaines();

    expect($chaines)->toHaveCount(2);
});
