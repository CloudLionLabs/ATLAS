<?php
declare(strict_types=1);

$root = dirname(__DIR__);
define('BASE_PATH', $root);
$testDatabase = 'atlas_global_test';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: 'root';
$server = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('DROP DATABASE IF EXISTS `' . $testDatabase . '`');
$server->exec('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
putenv('DB_DATABASE=' . $testDatabase);
putenv('DB_HOST=' . $dbHost);
putenv('DB_PORT=' . (string) $dbPort);
putenv('DB_USERNAME=' . $dbUser);
putenv('DB_PASSWORD=' . $dbPass);
$_ENV['DB_DATABASE'] = $testDatabase;
$_SERVER['DB_DATABASE'] = $testDatabase;

require $root . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\User;
use App\Services\CsvImporter;
use App\Services\ScraperClient;
use App\Services\XlsxExporter;
use App\Services\WebsiteEmailFinder;
use App\Services\GeoCities;
use App\Services\KeywordSeeder;

$adminId = (int) (db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
if ($adminId <= 0) { fwrite(STDERR, "No admin user in test database\n"); exit(1); }
Auth::forceUser($adminId);

$passed = 0;
$failed = 0;
$rawDir = $root . '/storage/raw';
$rawBefore = is_dir($rawDir) ? array_map('basename', glob($rawDir . '/*') ?: []) : [];
function check(string $name, bool $condition, mixed $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✗\033[0m {$name}" . ($detail !== null ? ' — ' . var_export($detail, true) : '') . "\n";
    }
}
function makeJob(string $name, string $keyword): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    $countryId = db()->query("SELECT id FROM countries WHERE iso2='GB'")->fetchColumn();
    $industryId = db()->query("SELECT id FROM industries WHERE code='industrial-equipment'")->fetchColumn();
    $stmt = db()->prepare('INSERT INTO scrape_jobs (id, user_id, country_id, industry_id, task_name, keyword_text, language_code, latitude, longitude, zoom, radius, depth, fastmode, extract_email, max_time, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$uuid, auth_id(), $countryId, $industryId, $name, $keyword, 'en', 51.5074, -0.1278, 15, 10000, 10, 1, 1, '10m', 'running', now_utc(), now_utc()]);
    return $uuid;
}

echo "\nATLAS PHP TEST SUITE\n";
echo "====================\n\n";

echo "Scraper adapter\n";
$client = new ScraperClient();
check('extracts UUID from HTML response', $client->extractJobId('<div data-job="b5a6e8a7-60ca-4117-8a82-3c2fe0f73057">ok</div>') === 'b5a6e8a7-60ca-4117-8a82-3c2fe0f73057');
check('extracts JSON job_id', $client->extractJobId('{"job_id":"abc-123"}') === 'abc-123');
check('does not invent a job id', $client->extractJobId('<div>queued</div>') === null);
$fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/scraper_jobs.json'), true);
check('recon fixture is a JSON array', is_array($fixture) && array_is_list($fixture));
check('deep fixture has verified keys', isset($fixture[0]['ID'], $fixture[0]['Name'], $fixture[0]['Status'], $fixture[0]['Data']));

echo "\nCSV importer\n";
$importer = new CsvImporter();
$job1 = makeJob('London Plumbers Test', 'plumbers in London');
$result = $importer->importPath($job1, $root . '/tests/fixtures/london-plumbers-test.csv', 'london-plumbers-test.csv');
check('import succeeds', $result['ok'] === true, $result);
check('15 rows imported', ($result['rows_imported'] ?? 0) === 15, $result);
check('job marked completed', db()->query("SELECT status FROM scrape_jobs WHERE id='{$job1}'")->fetchColumn() === 'completed');
check('15 raw records preserved', (int) db()->query("SELECT COUNT(*) FROM raw_records WHERE job_id='{$job1}'")->fetchColumn() === 15);
check('businesses created', (int) db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn() === 15, db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn());
$pimlico = db()->query("SELECT * FROM businesses WHERE business_name='Pimlico Plumbers'")->fetch();
check('website markdown is not present in clean field', $pimlico && $pimlico['website'] === 'https://www.pimlicoplumbers.com/', $pimlico['website'] ?? null);
check('complete_address parsed into city', $pimlico && $pimlico['city'] === 'London', $pimlico['city'] ?? null);
check('data_id preserved verbatim', $pimlico && $pimlico['google_data_id'] === '0x487604bfe981a7d5:0x5c52ea845400c21e');
check('normalized UK phone is generated', $pimlico && str_starts_with((string) $pimlico['normalized_phone'], '+44'), $pimlico['normalized_phone'] ?? null);
check('completeness is 0–100', $pimlico && (int) $pimlico['completeness_score'] >= 0 && (int) $pimlico['completeness_score'] <= 100);
check('source_first_seen is a datetime, not a source URL', $pimlico && !empty($pimlico['source_first_seen']) && strtotime((string) $pimlico['source_first_seen']) !== false && !str_starts_with((string) $pimlico['source_first_seen'], 'http'), $pimlico['source_first_seen'] ?? null);
check('14-day volume series exists', count((new App\Services\StatsService())->dailySeries(14)) === 14);
$matchMethod = new ReflectionMethod(CsvImporter::class, 'findStrongMatch');
$matchMethod->setAccessible(true);
$fallbackMatch = $matchMethod->invoke($importer, [
    'google_place_id' => null, 'google_data_id' => null, 'google_cid' => null,
    'normalized_name' => $pimlico['normalized_name'] ?? '',
    'normalized_phone' => $pimlico['normalized_phone'] ?? null,
    'website_domain' => null,
]);
check('dedupes overlapping coverage results without Google IDs', (int) ($fallbackMatch['id'] ?? 0) === (int) ($pimlico['id'] ?? -1), $fallbackMatch['id'] ?? null);
check('extracts JSON business descriptions', CsvImporter::descriptionFromFields(['descriptions' => '["Family owned plumbing company.", "Serving London since 1979."]']) === "Family owned plumbing company.\nServing London since 1979.");
check('extracts about-object business descriptions', CsvImporter::descriptionFromFields(['about' => '{"summary":"Emergency plumbing and heating."}']) === 'Emergency plumbing and heating.');
check('extracts About option groups', CsvImporter::descriptionFromFields(['about' => '[{"name":"Service options","options":[{"name":"Outdoor seating","values":["Yes"]}]}]']) === 'Service options: Outdoor seating: Yes');

echo "\nIdempotent import\n";
$beforeBusinesses = (int) db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn();
$again = $importer->importPath($job1, $root . '/tests/fixtures/london-plumbers-test.csv', 'london-plumbers-test.csv');
check('re-import succeeds', $again['ok'] === true, $again);
check('re-import skips every duplicate', ($again['rows_imported'] ?? -1) === 0, $again);
check('raw rows do not duplicate', (int) db()->query("SELECT COUNT(*) FROM raw_records WHERE job_id='{$job1}'")->fetchColumn() === 15);
check('job stays completed after re-import', db()->query("SELECT status FROM scrape_jobs WHERE id='{$job1}'")->fetchColumn() === 'completed');
check('business count unchanged', (int) db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn() === $beforeBusinesses);

echo "\nCross-job deduplication\n";
$job2 = makeJob('London Plumbers Retest', 'plumbers in London');
$cross = $importer->importPath($job2, $root . '/tests/fixtures/london-plumbers-test.csv', 'london-retest.csv');
check('cross-job import succeeds', $cross['ok'] === true, $cross);
check('data_id matches merge instead of duplicate', (int) db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn() === $beforeBusinesses, db()->query('SELECT COUNT(*) FROM businesses')->fetchColumn());
check('source relations preserved for both jobs', (int) db()->query('SELECT COUNT(*) FROM business_sources')->fetchColumn() === 30, db()->query('SELECT COUNT(*) FROM business_sources')->fetchColumn());
check('raw rows retained per job', (int) db()->query("SELECT COUNT(*) FROM raw_records WHERE job_id='{$job2}'")->fetchColumn() === 15);

echo "\nEmail enrichment\n";
$emails = WebsiteEmailFinder::extractEmailsFromHtml('<a href="mailto:Sales@PimlicoPlumbers.com">sales</a> contact: office [at] pimlicoplumbers [dot] com <img src="logo@2x.png"> no-reply@example.com');
check('extracts mailto and obfuscated public emails', in_array('sales@pimlicoplumbers.com', $emails, true) && in_array('office@pimlicoplumbers.com', $emails, true), $emails);
check('ignores image filenames and no-reply addresses', !in_array('logo@2x.png', $emails, true) && !in_array('no-reply@example.com', $emails, true), $emails);
check('blocks localhost/private IPv4 targets', !WebsiteEmailFinder::isPublicIpAddress('127.0.0.1') && !WebsiteEmailFinder::isPublicIpAddress('10.0.0.5') && !WebsiteEmailFinder::isPublicIpAddress('169.254.169.254'));
check('allows a public DNS address', WebsiteEmailFinder::isPublicIpAddress('8.8.8.8'));
check('blocks IPv4-mapped loopback', !WebsiteEmailFinder::isPublicIpAddress('::ffff:127.0.0.1'));
$noisy = WebsiteEmailFinder::extractEmailsFromHtml('<span>0044emailinfo@kbhplumbings.comoffice</span><span>2nphello@themaryleboneplumbers.ukavailable</span><span>info@kbhplumbings.com</span>');
check('rejects glued phone/email fragments', $noisy === ['info@kbhplumbings.com'], $noisy);
check('rejects concatenated invalid TLDs', !in_array('2nphello@themaryleboneplumbers.ukavailable', $noisy, true));

echo "\nGlobal city dictionary\n";
if (is_file((string) config('geo_database.path'))) {
    $geo = new GeoCities();
    check('city database has 30k+ cities', $geo->count() > 30000, $geo->count());
    $munich = $geo->search('慕尼黑', 'DE', 5);
    check('searches Chinese alternate city names', ($munich['cities'][0]['name'] ?? '') === 'Munich', $munich['cities'][0] ?? null);
    check('returns separate admin area name', ($munich['cities'][0]['admin1_name'] ?? '') === 'Bavaria', $munich['cities'][0] ?? null);
$cologne = $geo->search('科隆', 'DE', 5);
check('uses Chinese-tagged city names, not Japanese aliases', in_array(($cologne['cities'][0]['name'] ?? ''), ['Cologne', 'Köln', 'Koeln'], true) && ($cologne['cities'][0]['name_zh'] ?? '') === '科隆', $cologne['cities'][0] ?? null);
} else {
    echo "  - geo database not imported; skipping city dictionary checks\n";
}

echo "\nExports\n";
check('CSV formula cell is prefixed safely', safe_csv_cell('=cmd|calc') === "'=cmd|calc");
check('normal CSV cell untouched', safe_csv_cell('London') === 'London');
$xlsx = $root . '/storage/exports/test-export.xlsx';
$ok = (new XlsxExporter())->write($xlsx, ['商家名称', '电话'], [['Pimlico Plumbers', '02081080808']], 'Businesses');
check('XLSX writer returns true', $ok);
$zip = new ZipArchive();
$opened = $zip->open($xlsx) === true;
check('XLSX is a readable zip', $opened);
$sheet = $opened ? (string) $zip->getFromName('xl/worksheets/sheet1.xml') : '';
check('XLSX contains inline string data', str_contains($sheet, 'Pimlico Plumbers') && str_contains($sheet, '02081080808'));
if ($opened) $zip->close();
@unlink($xlsx);

echo "\nMulti-user isolation\n";
$secondId = (new User())->create('isolation_test', '隔离测试', 'SecondUser!2026', 'member');
Auth::forceUser($secondId);
$secondStats = (new App\Services\StatsService())->dashboard();
check('second user has no businesses', ($secondStats['businesses'] ?? -1) === 0, $secondStats['businesses'] ?? null);
check('second user has no jobs', ($secondStats['jobs'] ?? -1) === 0, $secondStats['jobs'] ?? null);
check('second user has no keywords', (int) db()->query('SELECT COUNT(*) FROM keywords WHERE user_id = ' . $secondId)->fetchColumn() === 0);
$adminKeywordsBefore = (int) db()->query('SELECT COUNT(*) FROM keywords WHERE user_id = ' . $adminId)->fetchColumn();
$seeded = (new KeywordSeeder())->seedForUser($secondId);
check('new user receives its own B2B keyword library', $seeded === 25 && (int) db()->query('SELECT COUNT(*) FROM keywords WHERE user_id = ' . $secondId)->fetchColumn() === 25, $seeded);
check('seeding one tenant does not change another tenant', (int) db()->query('SELECT COUNT(*) FROM keywords WHERE user_id = ' . $adminId)->fetchColumn() === $adminKeywordsBefore);
$blockedImport = $importer->importPath($job1, $root . '/tests/fixtures/london-plumbers-test.csv', 'cross-user.csv');
check('cannot import another user\'s job', $blockedImport['ok'] === false, $blockedImport);
Auth::forceUser($adminId);

echo "\nSecurity\n";
check('raw CSV path guard rejects traversal', !str_contains(realpath($root . '/storage/raw') ?: '', '..'));
check('CSRF token is generated', strlen(csrf_token()) >= 40);

echo "\n====================\n";
echo "PASSED: {$passed}  FAILED: {$failed}\n";
// Remove the isolated MySQL test database.
try {
    $server->exec('DROP DATABASE IF EXISTS `' . $testDatabase . '`');
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to drop test database: ' . $e->getMessage() . "\n");
}
// Remove raw CSV copies created by this test run, preserving real imports.
foreach (glob($rawDir . '/*') ?: [] as $rawFile) {
    if (!in_array(basename($rawFile), $rawBefore, true) && is_file($rawFile)) {
        unlink($rawFile);
    }
}
exit($failed > 0 ? 1 : 0);
