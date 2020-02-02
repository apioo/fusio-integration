<?php

require __DIR__ . '/vendor/autoload.php';

const DATABASE = 'fusio';
const DATABASE_MASTER = 'fusio_master';

$releases = fetchReleases();
$releases = array_slice($releases, 0, 5);
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

echo 'Migration was executed successful!';

function runInstall(string $folder, string $database)
{
    echo '#################################################' . "\n";
    echo '## Starting installation ' . $folder . "\n";
    echo '#################################################' . "\n";

    $process = new \Symfony\Component\Process\Process(['php', 'bin/fusio', 'install', '--no-interaction'], $folder);
    $process->run(null, getEnvVars($database));

    if ($process->getExitCode() !== 0) {
        throw new RuntimeException('Installation command has failed');
    }

	echo $process->getOutput();
}

function compareDatabases(string $leftDatabase, string $rightDatabase)
{
    $migratedConnection = newConnection($leftDatabase);
    $newConnection = newConnection($rightDatabase);

    $migratedSchema = $migratedConnection->getSchemaManager()->createSchema();
    $newSchema = $newConnection->getSchemaManager()->createSchema();

    $queries = $migratedSchema->getMigrateToSql($newSchema, $newConnection->getDatabasePlatform());

    if (!empty($queries)) {
        foreach ($queries as $query) {
            echo '- ' . $query . "\n";
        }

        throw new RuntimeException('Migrated database differs from a fresh installation');
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

function getEnvVars(string $database): array
{
    $env = [];
    $env['FUSIO_PROJECT_KEY'] = '42eec18ffdbffc9fda6110dcc705d6ce';
    $env['FUSIO_URL'] = 'http://127.0.0.1';
    $env['FUSIO_ENV'] = 'dev';
    $env['FUSIO_DB_NAME'] = $database;
    $env['FUSIO_DB_USER'] = 'root';
    $env['FUSIO_DB_PW'] = '';
    $env['FUSIO_DB_HOST'] = 'localhost';

    return $env;
}

function newConnection(string $database): \Doctrine\DBAL\Connection
{
    return \Doctrine\DBAL\DriverManager::getConnection([
        'dbname' => $database,
        'user' => 'root',
        'password' => '',
        'host' => 'localhost',
        'driver' => 'pdo_mysql',
    ]);
}

function installComposer(string $folder)
{
    $process = new \Symfony\Component\Process\Process(['composer', 'install', '--no-interaction'], $folder);
    $process->setTimeout(3600 * 15);
    $process->run();

    if ($process->getExitCode() !== 0) {
        throw new RuntimeException('Composer command failed');
    }
}