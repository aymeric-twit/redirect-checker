<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class VerificateurHttp
{
    private Client $client;
    private int $concurrence;
    private int $timeout;
    private int $delaiMs;

    /** @var callable|null */
    private $callbackProgression;

    /** @var callable|null */
    private $callbackResultat;

    /**
     * @param array<string, string> $headersSupplementaires
     */
    public function __construct(
        int $concurrence = 10,
        int $timeout = 5,
        string $userAgent = 'SalomonBotSEO/1.0',
        int $maxRedirections = 10,
        int $delaiMs = 0,
        array $headersSupplementaires = [],
    ) {
        // Mode plateforme : lire la configuration centralisee + headers anti-blocage
        if (defined('PLATFORM_EMBEDDED') && class_exists(\Platform\Http\HttpConfig::class)) {
            try {
                $config = \Platform\Http\HttpConfig::charger();
                $timeout = $config->webTimeout;
                $sslVerify = $config->webSslVerify;
                $proxy = ($config->proxyEnabled && $config->proxyWeb && $config->proxyUrl !== '')
                    ? $config->proxyUrl
                    : null;
                $proxyAuth = $config->proxyAuth;

                // Si le module est configuré pour utiliser le Go service comme proxy
                if (class_exists(\Platform\Module\ModuleRegistry::class)) {
                    $moduleDesc = \Platform\Module\ModuleRegistry::get('redirects-checker');
                    if ($moduleDesc !== null
                        && $moduleDesc->crawlerBackend === \Platform\Enum\CrawlerBackend::Go
                        && $config->crawlerGoServiceUrl !== '') {
                        $proxy = $config->crawlerGoServiceUrl;
                        $proxyAuth = '';
                    }
                }

                // Headers anti-blocage enrichis (rotation UA, Accept-Language, Sec-Ch-Ua, etc.)
                $crawlerHeaders = class_exists(\Platform\Http\WebClient::class)
                    ? \Platform\Http\WebClient::construireHeadersCrawler()
                    : ['User-Agent' => $config->webUserAgent];
            } catch (\Throwable) {
                $sslVerify = false;
                $proxy = null;
                $proxyAuth = '';
                $crawlerHeaders = ['User-Agent' => $userAgent];
            }
        } else {
            $sslVerify = false;
            $proxy = null;
            $proxyAuth = '';
            $crawlerHeaders = ['User-Agent' => $userAgent];
        }

        $this->concurrence = $concurrence;
        $this->timeout = $timeout;
        $this->delaiMs = $delaiMs;

        $redirectConfig = $maxRedirections > 0
            ? ['max' => $maxRedirections, 'track_redirects' => true]
            : false;

        $headers = array_merge(
            $crawlerHeaders,
            $headersSupplementaires,
        );

        $clientOptions = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
            'allow_redirects' => $redirectConfig,
            'http_errors' => false,
            'verify' => $sslVerify,
            'headers' => $headers,
        ];

        if ($proxy !== null) {
            $proxyConfig = $proxy;
            if ($proxyAuth !== '') {
                // Inserer auth dans l'URL du proxy (user:pass@host:port)
                $parties = parse_url($proxy);
                $proxyConfig = ($parties['scheme'] ?? 'http') . '://' . $proxyAuth . '@'
                    . ($parties['host'] ?? '') . (isset($parties['port']) ? ':' . $parties['port'] : '');
            }
            $clientOptions['proxy'] = $proxyConfig;
        }

        $this->client = new Client($clientOptions);
    }

    public function setCallbackProgression(callable $callback): void
    {
        $this->callbackProgression = $callback;
    }

    public function setCallbackResultat(callable $callback): void
    {
        $this->callbackResultat = $callback;
    }

    /**
     * Verifie une liste d'URLs en HTTP HEAD concurrent.
     * @param string[] $urls
     * @return array<string, array{statut: int|null, erreur: string|null, tempsMs: int, urlFinale: string|null}>
     */
    public function verifier(array $urls): array
    {
        $urls = array_values(array_unique($urls));
        $resultats = [];
        $verifiees = 0;
        $aRetenterEnGet = [];
        $total = count($urls);

        // Service Go : batch concurrent avec TLS fingerprinting
        if (defined('PLATFORM_EMBEDDED') && class_exists('\\Platform\\Http\\HttpConfig')) {
            $config = \Platform\Http\HttpConfig::charger();
            if ($config->crawlerGoServiceUrl !== '') {
                return $this->verifierViaGoService($urls, $config);
            }
        }

        // Fallback : Guzzle Pool
        // Phase 1 : HEAD
        $this->executerPool($urls, 'HEAD', $resultats, $verifiees, $total, $aRetenterEnGet);

        // Phase 2 : fallback GET pour les 403/405 (serveurs qui bloquent HEAD)
        if (!empty($aRetenterEnGet)) {
            $this->executerPool($aRetenterEnGet, 'GET', $resultats, $verifiees, $total);
        }

        // Marquer les URLs invalides non verifiees
        foreach ($urls as $url) {
            if (!isset($resultats[$url])) {
                $resultats[$url] = [
                    'statut' => null,
                    'erreur' => 'URL invalide ou IP privee',
                    'tempsMs' => 0,
                    'urlFinale' => null,
                ];
            }
        }

        return $resultats;
    }

    /**
     * Execute un pool de requetes HTTP.
     * @param string[] $urls
     * @param array<string, array{statut: int|null, erreur: string|null, tempsMs: int, urlFinale: string|null}> $resultats
     * @param string[] $aRetenterEnGet URLs a retenter en GET (rempli si methode=HEAD et 403/405)
     */
    private function executerPool(
        array $urls,
        string $methode,
        array &$resultats,
        int &$verifiees,
        int $total,
        ?array &$aRetenterEnGet = null,
    ): void {
        $requetes = function () use ($urls, $methode): \Generator {
            foreach ($urls as $url) {
                if (!$this->estUrlValide($url)) {
                    continue;
                }
                yield $url => new Request($methode, $url);
            }
        };

        $pool = new Pool($this->client, $requetes(), [
            'concurrency' => $this->concurrence,
            'fulfilled' => function ($reponse, $url) use (&$resultats, &$verifiees, $total, $methode, &$aRetenterEnGet): void {
                if ($this->delaiMs > 0) {
                    usleep($this->delaiMs * 1000);
                }

                $statut = $reponse->getStatusCode();

                // Fallback GET si HEAD retourne 403/405
                if ($methode === 'HEAD' && $aRetenterEnGet !== null && in_array($statut, [403, 405], true)) {
                    $aRetenterEnGet[] = $url;
                    return;
                }

                // Historique complet des redirections
                $historiqueUrls = $reponse->getHeader('X-Guzzle-Redirect-History');
                $historiqueStatuts = $reponse->getHeader('X-Guzzle-Redirect-Status-History');

                $urlFinale = !empty($historiqueUrls) ? end($historiqueUrls) : null;

                // Construire la chaine de redirections : [url, statut] pour chaque etape
                $chaineRedirections = [];
                if (!empty($historiqueUrls)) {
                    for ($j = 0, $n = count($historiqueUrls); $j < $n; $j++) {
                        $chaineRedirections[] = [
                            'url' => $historiqueUrls[$j],
                            'statut' => (int) ($historiqueStatuts[$j] ?? 0),
                        ];
                    }
                }

                $resultats[$url] = [
                    'statut' => $statut,
                    'erreur' => null,
                    'tempsMs' => 0,
                    'urlFinale' => $urlFinale,
                    'chaineRedirections' => $chaineRedirections,
                ];
                $this->notifierResultat($url, $resultats[$url]);
                $verifiees++;
                $this->notifierProgression($verifiees, $total);
            },
            'rejected' => function ($raison, $url) use (&$resultats, &$verifiees, $total): void {
                if ($this->delaiMs > 0) {
                    usleep($this->delaiMs * 1000);
                }

                $erreur = $raison instanceof ConnectException
                    ? 'Connexion impossible : ' . $raison->getMessage()
                    : ($raison instanceof \Throwable ? $raison->getMessage() : 'Erreur inconnue');

                $resultats[$url] = [
                    'statut' => null,
                    'erreur' => $erreur,
                    'tempsMs' => 0,
                    'urlFinale' => null,
                    'chaineRedirections' => [],
                ];
                $this->notifierResultat($url, $resultats[$url]);
                $verifiees++;
                $this->notifierProgression($verifiees, $total);
            },
        ]);

        $pool->promise()->wait();
    }

    /**
     * Verifie les URLs via le service Go crawl-proxy (batch concurrent + TLS fingerprinting).
     *
     * @param string[] $urls
     * @return array<string, array{statut: int|null, erreur: string|null, tempsMs: int, urlFinale: string|null, chaineRedirections: array}>
     */
    private function verifierViaGoService(array $urls, \Platform\Http\HttpConfig $config): array
    {
        $serviceUrl = rtrim($config->crawlerGoServiceUrl, '/') . '/fetch-batch';
        $headers = class_exists('\\Platform\\Http\\WebClient')
            ? \Platform\Http\WebClient::construireHeadersCrawler()
            : ['User-Agent' => $config->webUserAgent];

        $resultats = [];
        $total = count($urls);
        $verifiees = 0;

        // Filtrer les URLs valides
        $urlsValides = array_filter($urls, fn(string $u): bool => $this->estUrlValide($u));

        // Phase 1 : HEAD en batch
        $aRetenterEnGet = [];
        $batches = array_chunk($urlsValides, 500);

        foreach ($batches as $batch) {
            $requests = array_map(fn(string $url): array => [
                'url'              => $url,
                'method'           => 'HEAD',
                'timeout'          => $this->timeout,
                'tls_profile'      => $config->crawlerProfilTls,
                'follow_redirects' => true,
                'max_redirects'    => 10,
                'headers'          => $headers,
            ], $batch);

            $reponses = $this->envoyerBatchGo($serviceUrl, $config->crawlerGoServiceToken, $requests);

            foreach ($reponses as $i => $resp) {
                $url = $batch[$i] ?? '';
                $statut = (int) ($resp['status_code'] ?? 0);

                if ($statut === 403 || $statut === 405) {
                    $aRetenterEnGet[] = $url;
                    continue;
                }

                $resultats[$url] = [
                    'statut'              => $statut > 0 ? $statut : null,
                    'erreur'              => ($resp['error'] ?? '') !== '' ? $resp['error'] : null,
                    'tempsMs'             => (int) ($resp['timings']['total_ms'] ?? 0),
                    'urlFinale'           => ($resp['url_finale'] ?? '') !== '' && $resp['url_finale'] !== $url ? $resp['url_finale'] : null,
                    'chaineRedirections'  => [],
                ];
                $this->notifierResultat($url, $resultats[$url]);
                $verifiees++;
                $this->notifierProgression($verifiees, $total);
            }
        }

        // Phase 2 : GET fallback pour les 403/405
        if (!empty($aRetenterEnGet)) {
            $batchesGet = array_chunk($aRetenterEnGet, 500);
            foreach ($batchesGet as $batch) {
                $requests = array_map(fn(string $url): array => [
                    'url'              => $url,
                    'method'           => 'GET',
                    'timeout'          => $this->timeout,
                    'tls_profile'      => $config->crawlerProfilTls,
                    'follow_redirects' => true,
                    'max_redirects'    => 10,
                    'max_size_bytes'   => 1024,
                    'headers'          => $headers,
                ], $batch);

                $reponses = $this->envoyerBatchGo($serviceUrl, $config->crawlerGoServiceToken, $requests);

                foreach ($reponses as $i => $resp) {
                    $url = $batch[$i] ?? '';
                    $statut = (int) ($resp['status_code'] ?? 0);
                    $resultats[$url] = [
                        'statut'              => $statut > 0 ? $statut : null,
                        'erreur'              => ($resp['error'] ?? '') !== '' ? $resp['error'] : null,
                        'tempsMs'             => (int) ($resp['timings']['total_ms'] ?? 0),
                        'urlFinale'           => ($resp['url_finale'] ?? '') !== '' && $resp['url_finale'] !== $url ? $resp['url_finale'] : null,
                        'chaineRedirections'  => [],
                    ];
                    $this->notifierResultat($url, $resultats[$url]);
                    $verifiees++;
                    $this->notifierProgression($verifiees, $total);
                }
            }
        }

        // Marquer les URLs invalides
        foreach ($urls as $url) {
            if (!isset($resultats[$url])) {
                $resultats[$url] = [
                    'statut'              => null,
                    'erreur'              => 'URL invalide ou IP privee',
                    'tempsMs'             => 0,
                    'urlFinale'           => null,
                    'chaineRedirections'  => [],
                ];
            }
        }

        return $resultats;
    }

    /**
     * Envoie un batch de requetes au service Go crawl-proxy.
     *
     * @param array<int, array<string, mixed>> $requests
     * @return array<int, array<string, mixed>>
     */
    private function envoyerBatchGo(string $serviceUrl, string $token, array $requests): array
    {
        $payload = json_encode([
            'requests'    => $requests,
            'concurrency' => $this->concurrence,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($serviceUrl);
        $curlHeaders = ['Content-Type: application/json'];
        if ($token !== '') {
            $curlHeaders[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_TIMEOUT        => ($this->timeout * count($requests) / max(1, $this->concurrence)) + 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $resultat = curl_exec($ch);
        curl_close($ch);

        if ($resultat === false) {
            return [];
        }

        $data = json_decode($resultat, true);
        return $data['results'] ?? [];
    }

    /**
     * Verifie qu'une URL est valide et ne pointe pas vers une IP privee (SSRF).
     */
    private function estUrlValide(string $url): bool
    {
        $parties = parse_url($url);
        if ($parties === false || !isset($parties['host'])) {
            return false;
        }

        $schema = $parties['scheme'] ?? 'http';
        if (!in_array($schema, ['http', 'https'], true)) {
            return false;
        }

        $port = $parties['port'] ?? null;
        if ($port !== null && !in_array($port, [80, 443], true)) {
            return false;
        }

        $ips = gethostbynamel($parties['host']);
        if ($ips === false) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function notifierProgression(int $verifiees, int $total): void
    {
        if ($this->callbackProgression !== null) {
            ($this->callbackProgression)($verifiees, $total);
        }
    }

    /**
     * @param array{statut: int|null, erreur: string|null, tempsMs: int, urlFinale: string|null} $resultat
     */
    private function notifierResultat(string $url, array $resultat): void
    {
        if ($this->callbackResultat !== null) {
            ($this->callbackResultat)($url, $resultat);
        }
    }
}
