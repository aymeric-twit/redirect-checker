<?php

test('devrait lister les jobs tries par date descendante', function (): void {
    $repertoire = sys_get_temp_dir() . '/test-jobs-' . bin2hex(random_bytes(6));
    mkdir($repertoire, 0755, true);

    $gestionnaire = new GestionnaireJobs($repertoire);

    // Creer 2 jobs avec des dates differentes
    $job1 = 'aaaa11112222bbbb33334444';
    mkdir($repertoire . '/' . $job1, 0755, true);
    file_put_contents($repertoire . '/' . $job1 . '/config.json', json_encode([
        'date_creation' => '2025-01-01 10:00:00',
        'total_paires' => 5,
        'domaine' => 'https://example.com',
        'verifier_404' => true,
    ]));
    file_put_contents($repertoire . '/' . $job1 . '/progress.json', json_encode([
        'status' => 'done',
        'verifiees' => 5,
    ]));

    $job2 = 'bbbb11112222cccc33334444';
    mkdir($repertoire . '/' . $job2, 0755, true);
    file_put_contents($repertoire . '/' . $job2 . '/config.json', json_encode([
        'date_creation' => '2025-02-15 14:30:00',
        'total_paires' => 10,
        'domaine' => 'https://other.com',
        'verifier_404' => false,
    ]));

    $jobs = $gestionnaire->listerJobs();

    expect($jobs)->toHaveCount(2);
    // Plus recent en premier
    expect($jobs[0]['jobId'])->toBe($job2);
    expect($jobs[0]['totalPaires'])->toBe(10);
    expect($jobs[0]['domaine'])->toBe('https://other.com');
    expect($jobs[0]['statut'])->toBe('termine');
    expect($jobs[0]['verifier404'])->toBeFalse();

    expect($jobs[1]['jobId'])->toBe($job1);
    expect($jobs[1]['statut'])->toBe('termine');
    expect($jobs[1]['verifier404'])->toBeTrue();
    expect($jobs[1]['urlsCrawlees'])->toBe(5);

    // Nettoyage
    array_map('unlink', glob($repertoire . '/*/*.json'));
    array_map('rmdir', glob($repertoire . '/*'));
    rmdir($repertoire);
});

test('devrait extraire le domaine depuis analyse.json si absent dans config', function (): void {
    $repertoire = sys_get_temp_dir() . '/test-jobs-' . bin2hex(random_bytes(6));
    mkdir($repertoire, 0755, true);

    $gestionnaire = new GestionnaireJobs($repertoire);

    $job1 = 'cccc11112222dddd33334444';
    mkdir($repertoire . '/' . $job1, 0755, true);
    file_put_contents($repertoire . '/' . $job1 . '/config.json', json_encode([
        'date_creation' => '2025-03-01 09:00:00',
        'total_paires' => 3,
        'domaine' => '',
        'verifier_404' => false,
    ]));
    file_put_contents($repertoire . '/' . $job1 . '/analyse.json', json_encode([
        'paires' => [
            ['source' => 'https://monsite.fr/old', 'destination' => 'https://monsite.fr/new'],
        ],
    ]));

    $jobs = $gestionnaire->listerJobs();

    expect($jobs)->toHaveCount(1);
    expect($jobs[0]['domaine'])->toBe('https://monsite.fr');

    // Nettoyage
    array_map('unlink', glob($repertoire . '/*/*.json'));
    array_map('rmdir', glob($repertoire . '/*'));
    rmdir($repertoire);
});

test('devrait ignorer les repertoires sans config.json', function (): void {
    $repertoire = sys_get_temp_dir() . '/test-jobs-' . bin2hex(random_bytes(6));
    mkdir($repertoire, 0755, true);

    $gestionnaire = new GestionnaireJobs($repertoire);

    // Repertoire sans config.json
    mkdir($repertoire . '/orphelin123456789abc', 0755, true);

    $jobs = $gestionnaire->listerJobs();

    expect($jobs)->toHaveCount(0);

    // Nettoyage
    rmdir($repertoire . '/orphelin123456789abc');
    rmdir($repertoire);
});

test('devrait detecter le statut en cours', function (): void {
    $repertoire = sys_get_temp_dir() . '/test-jobs-' . bin2hex(random_bytes(6));
    mkdir($repertoire, 0755, true);

    $gestionnaire = new GestionnaireJobs($repertoire);

    $job1 = 'dddd11112222eeee33334444';
    mkdir($repertoire . '/' . $job1, 0755, true);
    file_put_contents($repertoire . '/' . $job1 . '/config.json', json_encode([
        'date_creation' => '2025-04-01 12:00:00',
        'total_paires' => 8,
        'domaine' => 'https://test.com',
        'verifier_404' => true,
    ]));
    file_put_contents($repertoire . '/' . $job1 . '/progress.json', json_encode([
        'status' => 'running',
        'progress' => 50,
    ]));

    $jobs = $gestionnaire->listerJobs();

    expect($jobs)->toHaveCount(1);
    expect($jobs[0]['statut'])->toBe('en_cours');

    // Nettoyage
    array_map('unlink', glob($repertoire . '/*/*.json'));
    array_map('rmdir', glob($repertoire . '/*'));
    rmdir($repertoire);
});
