<?php

require __DIR__ . '/vendor/autoload.php';

$httpClient = new \GuzzleHttp\Client(['verify' => false]);

$response = $httpClient->get('https://www.fusio-project.org/marketplace.yaml');
if ($response->getStatusCode() !== 200) {
    echo 'Could not fetch marketplace yaml';
    exit(1);
}

$apps = \Symfony\Component\Yaml\Yaml::parse((string) $response->getBody());
$error = false;

foreach ($apps as $name => $app) {
    echo 'Check app ' . $name . "\n";

    try {
        \Webmozart\Assert\Assert::same(1, version_compare($app['version'], '0.0'));
        \Webmozart\Assert\Assert::notEmpty($app['description']);
        \Webmozart\Assert\Assert::eq($app['screenshot'], filter_var($app['screenshot'], FILTER_VALIDATE_URL));
        \Webmozart\Assert\Assert::eq($app['website'], filter_var($app['website'], FILTER_VALIDATE_URL));
        \Webmozart\Assert\Assert::eq($app['downloadUrl'], filter_var($app['downloadUrl'], FILTER_VALIDATE_URL));

        $file = __DIR__ . '/' . $name . '.zip';
        $handle = fopen($file, 'w');

        $response = $httpClient->get($app['downloadUrl'], ['sink' => $handle]);

        \Webmozart\Assert\Assert::eq(200, $response->getStatusCode());
        \Webmozart\Assert\Assert::eq($app['sha1Hash'], sha1_file($file));
        \Webmozart\Assert\Assert::true((new \ZipArchive())->open($file, \ZipArchive::CHECKCONS));
    } catch (\InvalidArgumentException $e) {
        $error = true;
        echo $e->getMessage() . "\n";
    }
}

if ($error) {
    exit(1);
}
