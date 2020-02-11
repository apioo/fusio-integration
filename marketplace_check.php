<?php

require __DIR__ . '/vendor/autoload.php';

$apps = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/master/marketplace.yaml'));
$httpClient = new \GuzzleHttp\Client(['verify' => false]);

foreach ($apps as $name => $app) {
    echo 'Check app ' . $name . "\n";

    \Webmozart\Assert\Assert::same(1, version_compare($app['version'], '0.0'), $name);
    \Webmozart\Assert\Assert::notEmpty($app['description'], $name);
    \Webmozart\Assert\Assert::eq($app['screenshot'], filter_var($app['screenshot'], FILTER_VALIDATE_URL), $name);
    \Webmozart\Assert\Assert::eq($app['website'], filter_var($app['website'], FILTER_VALIDATE_URL), $name);
    \Webmozart\Assert\Assert::eq($app['downloadUrl'], filter_var($app['downloadUrl'], FILTER_VALIDATE_URL), $name);

    $file = __DIR__ . '/' . $name . '.zip';
    $handle = fopen($file, 'w');

    $response = $httpClient->get($app['downloadUrl'], ['sink' => $handle]);

    \Webmozart\Assert\Assert::eq(200, $response->getStatusCode());
    \Webmozart\Assert\Assert::eq($app['sha1Hash'], sha1_file($file), $name);
    \Webmozart\Assert\Assert::true((new \ZipArchive())->open($file, \ZipArchive::CHECKCONS));
}
