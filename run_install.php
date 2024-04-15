<?php

require __DIR__ . '/vendor/autoload.php';

const DATABASE = 'pdo-mysql://root:test1234@localhost/fusio';
const DATABASE_MASTER = 'pdo-mysql://root:test1234@localhost/fusio_master';

$releases = fetchReleases();
$releases = getReleases($releases, 'v4.0.0');
$releases = array_reverse($releases);

foreach ($releases as $release) {
    $file = fetchAsset($release['assets'], $release['tag_name']);
    $folder = extractZip($file, $release['tag_name']);

    runInstall($folder, DATABASE);
}

// migrate to master
$file = 'master.zip';
downloadUrl('https://github.com/apioo/fusio/archive/master.zip', $file);
$folder = extractZip($file, 'master-tmp');
rename($folder . '/fusio-master', 'master');

$folder = 'master';
installComposer($folder);
runInstall($folder, DATABASE);

// install fresh master on a different database
runInstall($folder, DATABASE_MASTER);

// compare databases
compareDatabases(DATABASE, DATABASE_MASTER);

// run SDK generation to check whether all routes and schemas are clean
mkdir($folder . '/output');
runSDKGeneration($folder, 'backend');
runSDKGeneration($folder, 'consumer');

echo 'Migration was executed successful!';

function runInstall(string $folder, string $database)
{
    echo '#################################################' . "\n";
    echo '## Starting installation ' . $folder . "\n";
    echo '#################################################' . "\n";

    $process = new \Symfony\Component\Process\Process(['php', 'bin/fusio', 'migrate', '--no-interaction'], $folder);

    echo '> ' . $process->getCommandLine() . "\n";

    $process->run(null, getEnvVars($database));

    echo $process->getOutput() . "\n";

    if ($process->getExitCode() !== 0) {
        echo 'Error:' . "\n";
        echo $process->getErrorOutput() . "\n";

        throw new RuntimeException('Installation command has failed');
    }
}

function compareDatabases(string $leftDatabase, string $rightDatabase)
{
    $migratedConnection = newConnection($leftDatabase);
    $newConnection = newConnection($rightDatabase);

    $migratedSchema = $migratedConnection->createSchemaManager()->introspectSchema();
    $newSchema = $newConnection->createSchemaManager()->introspectSchema();

    $queries = $migratedSchema->getMigrateToSql($newSchema, $newConnection->getDatabasePlatform());

    if (!empty($queries)) {
        foreach ($queries as $query) {
            echo '- ' . $query . "\n";
        }

        throw new RuntimeException('Migrated database differs from a fresh installation');
    }
}

function runSDKGeneration(string $folder, string $filter)
{
    echo '#################################################' . "\n";
    echo '## Starting SDK generation ' . $folder . "\n";
    echo '#################################################' . "\n";

    $process = new \Symfony\Component\Process\Process(['php', 'bin/fusio', 'generate:sdk', '--filter', $filter], $folder);

    echo '> ' . $process->getCommandLine() . "\n";

    $process->run();

    echo $process->getOutput() . "\n";

    if ($process->getExitCode() !== 0) {
        echo 'Error:' . "\n";
        echo $process->getErrorOutput() . "\n";

        throw new RuntimeException('SDK generation command has failed');
    }
}

function fetchAsset(array $assets, string $tagName): string
{
    $asset = current($assets);
    if (!empty($asset)) {
        $file = $tagName . '.zip';

        if ($asset['content_type'] !== 'application/x-zip-compressed') {
            throw new RuntimeException('Asset must be a zip file');
        }

        return downloadUrl($asset['browser_download_url'], $file);
    } else {
        throw new RuntimeException('Found no asset for release');
    }
}

function downloadUrl(string $url, string $file)
{
    $handle = fopen($file, 'w');
    if (!$handle) {
        throw new RuntimeException('Could not open zip file');
    }

    $httpClient = new \GuzzleHttp\Client(['verify' => false]);
    $response = $httpClient->get($url, ['sink' => $handle]);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Could not fetch assert');
    }

    return $file;
}

function extractZip(string $file, string $tagName)
{
    $zip = new ZipArchive();
    if ($zip->open($file)) {
        $zip->extractTo($tagName);
        $zip->close();

        return $tagName;
    } else {
        throw new RuntimeException('Could not open zip file');
    }
}

function fetchReleases(): array
{
    $httpClient = new \GuzzleHttp\Client(['verify' => false]);
    $response = $httpClient->get('https://api.github.com/repos/apioo/fusio/releases');

    return json_decode((string) $response->getBody(), true);
}

function getReleases(array $releases, string $minVersion): array
{
    return array_filter($releases, function(array $release) use ($minVersion){
        return version_compare($release['tag_name'], $minVersion) !== -1;
    });
}

function getEnvVars(string $database): array
{
    $env = [];
    $env['APP_PROJECT_KEY'] = '42eec18ffdbffc9fda6110dcc705d6ce';
    $env['APP_URL'] = 'http://127.0.0.1';
    $env['APP_ENV'] = 'dev';
    $env['APP_CONNECTION'] = $database;

    return $env;
}

function newConnection(string $database): \Doctrine\DBAL\Connection
{
    $params = (new \Doctrine\DBAL\Tools\DsnParser())->parse($database);
    return \Doctrine\DBAL\DriverManager::getConnection($params);
}

function installComposer(string $folder)
{
    $composerFile = $folder . '/composer.json';
    $data = \json_decode(\file_get_contents($composerFile));
    $data->require->{'fusio/impl'} = 'dev-master';
    \file_put_contents($composerFile, \json_encode($data));

    $composerLock = $folder . '/composer.lock';
    \unlink($composerLock);

    $process = new \Symfony\Component\Process\Process(['composer', 'install', '--no-interaction'], $folder);
    $process->setTimeout(3600 * 15);

    echo '> ' . $process->getCommandLine() . "\n";

    $process->mustRun();

    echo $process->getOutput();
}
