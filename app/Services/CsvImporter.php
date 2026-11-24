<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class CsvImporter
{
    public const MAX_BYTES = 50 * 1024 * 1024;

    public function importPath(string $jobId, string $path, string $originalName = 'upload.csv'): array
    {
        $pdo = db();
        $uid = auth_id();
        $stmt = $pdo->prepare('SELECT * FROM scrape_jobs WHERE id = ? AND user_id = ?');
        $stmt->execute([$jobId, $uid]);
        $job = $stmt->fetch();
        if (!$job) {
            return ['ok' => false, 'error' => '本地任务不存在', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }
        if (!is_file($path) || filesize($path) === 0) {
            return ['ok' => false, 'error' => 'CSV 文件不存在或为空', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }
        if (filesize($path) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'CSV 超过 50 MB 上限', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'error' => '无法读取 CSV', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $headers = fgetcsv($fh);
        if (!is_array($headers) || $headers === []) {
            fclose($fh);
            return ['ok' => false, 'error' => 'CSV 表头无法解析', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }
        $headers = array_map(static fn ($h) => trim((string) $h), $headers);
        foreach (['title', 'data_id', 'website', 'phone', 'complete_address'] as $required) {
            if (!in_array($required, $headers, true)) {
                fclose($fh);
                return ['ok' => false, 'error' => 'CSV 缺少关键列：' . $required, 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
            }
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $originalName) ?: 'upload.csv';
        if (!str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }
        $rawName = $jobId . '-' . substr(sha1_file($path) ?: uniqid(), 0, 8) . '-' . $safeName;
        $rawDir = base_path('storage/raw');
        if (!is_dir($rawDir)) mkdir($rawDir, 0775, true);
        $rawPath = $rawDir . '/' . $rawName;
        if (!copy($path, $rawPath)) {
            fclose($fh);
            return ['ok' => false, 'error' => '无法保存原始 CSV', 'rows_total' => 0, 'rows_imported' => 0, 'rows_skipped' => 0];
        }

        $jobCountry = null;
        if (!empty($job['country_id'])) {
            $cq = $pdo->prepare('SELECT * FROM countries WHERE id = ?');
            $cq->execute([(int) $job['country_id']]);
            $jobCountry = $cq->fetch() ?: null;
        }

        $pdo->beginTransaction();
        $rowsTotal = 0;
        $rowsImported = 0;
        $rowsSkipped = 0;
        $errors = [];
        $created = 0;
        $merged = 0;
        try {
            while (($row = fgetcsv($fh)) !== false) {
                if ($row === [null] || $row === []) continue;
                $rowsTotal++;
                $rowNumber = $rowsTotal + 1;
                $row = array_pad($row, count($headers), '');
                $assoc = [];
                foreach ($headers as $i => $key) {
                    $assoc[$key] = isset($row[$i]) && $row[$i] !== '' ? (string) $row[$i] : null;
                }
                if (trim((string) ($assoc['title'] ?? '')) === '') {
                    $rowsSkipped++;
                    $errors[] = "第 {$rowNumber} 行缺少 title，已保留原始行但未生成商家";
                    continue;
                }
                $rawJson = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($rawJson === false) {
                    $rowsSkipped++;
                    $errors[] = "第 {$rowNumber} 行 JSON 编码失败";
                    continue;
                }
                $hash = hash('sha256', $rawJson);
                $dup = $pdo->prepare('SELECT id FROM raw_records WHERE job_id = ? AND (content_hash = ? OR row_no = ?)');
                $dup->execute([$jobId, $hash, $rowNumber]);
                if ($dup->fetchColumn()) {
                    $rowsSkipped++;
                    continue;
                }

                $externalId = $assoc['data_id'] ?? null;
                $insRaw = $pdo->prepare('INSERT INTO raw_records (job_id, row_no, external_data_id, raw_json, content_hash, imported_at) VALUES (?,?,?,?,?,?)');
                $insRaw->execute([$jobId, $rowNumber, $externalId, $rawJson, $hash, now_utc()]);
                $rawId = (int) $pdo->lastInsertId();

                $clean = $this->cleanRow($assoc, $jobCountry, $job);
                $regionId = $this->resolveRegion($clean['country_id'], $clean['city']);
                $clean['region_id'] = $regionId;

                $existing = $this->findStrongMatch($clean);
                if ($existing) {
                    $businessId = (int) $existing['id'];
                    $this->mergeBusiness($existing, $clean);
                    $merged++;
                } else {
                    $businessId = $this->insertBusiness($clean);
                    $created++;
                }
                $rowsImported++;
                $this->syncContacts($businessId, $clean, $assoc);
                $this->linkSource($businessId, $rawId, $jobId);

            }
            fclose($fh);

            $allDuplicates = $rowsImported === 0 && $rowsTotal > 0 && $rowsSkipped === $rowsTotal && !$errors;
            if ($allDuplicates) {
                // Idempotent re-import: keep the previous successful state and
                // never downgrade a completed job because every row already exists.
                $status = in_array($job['status'], ['completed', 'partial'], true) ? $job['status'] : 'completed';
            } else {
                $status = $rowsImported > 0 ? ($errors ? 'partial' : 'completed') : 'failed';
            }
            $countRaw = $pdo->prepare('SELECT COUNT(*) FROM raw_records WHERE job_id = ?');
            $countRaw->execute([$jobId]);
            $resultCount = (int) $countRaw->fetchColumn();
            $pdo->prepare('UPDATE scrape_jobs SET status = ?, result_count = ?, imported_at = ?, completed_at = ?, raw_csv_path = ?, error_message = ?, updated_at = ? WHERE id = ? AND user_id = ?')
                ->execute([
                    $status,
                    $resultCount,
                    now_utc(),
                    now_utc(),
                    'storage/raw/' . $rawName,
                    $errors ? implode("\n", array_slice($errors, 0, 20)) : null,
                    now_utc(),
                    $jobId,
                    $uid,
                ]);
            $log = $pdo->prepare('INSERT INTO import_logs (job_id, status, message, rows_total, rows_imported, rows_skipped, created_at) VALUES (?,?,?,?,?,?,?)');
            $log->execute([
                $jobId,
                $status,
                $allDuplicates
                    ? sprintf('重复导入已忽略：%d 行均已存在，未创建重复商家', $rowsSkipped)
                    : sprintf('导入完成：%d 行，新建 %d 家，合并 %d 家，跳过 %d 行', $rowsTotal, $created, $merged, $rowsSkipped),
                $rowsTotal,
                $rowsImported,
                $rowsSkipped,
                now_utc(),
            ]);
            audit('csv.import', 'scrape_job', $jobId, ['rows' => $rowsTotal, 'imported' => $rowsImported, 'created' => $created, 'merged' => $merged]);
            $pdo->commit();

            return [
                'ok' => true,
                'status' => $status,
                'rows_total' => $rowsTotal,
                'rows_imported' => $rowsImported,
                'rows_skipped' => $rowsSkipped,
                'businesses_created' => $created,
                'businesses_merged' => $merged,
                'warnings' => $errors,
                'raw_path' => $rawPath,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (is_resource($fh)) fclose($fh);
            return [
                'ok' => false,
                'error' => '导入失败：' . $e->getMessage(),
                'rows_total' => $rowsTotal,
                'rows_imported' => 0,
                'rows_skipped' => $rowsSkipped,
            ];
        }
    }

    private function cleanRow(array $row, ?array $jobCountry, array $job): array
    {
        $complete = $this->parseCompleteAddress($row['complete_address'] ?? null);
        $countryCode = strtoupper(trim((string) ($complete['country'] ?? '')));
        if (strlen($countryCode) !== 2) {
            $countryCode = strtoupper((string) ($jobCountry['iso2'] ?? ''));
        }
        $country = null;
        if (strlen($countryCode) === 2) {
            $q = db()->prepare('SELECT * FROM countries WHERE iso2 = ?');
            $q->execute([$countryCode]);
            $country = $q->fetch() ?: null;
        }
        if (!$country && $jobCountry) {
            $country = $jobCountry;
            $countryCode = (string) $jobCountry['iso2'];
        }

        $city = $this->nullable($complete['city'] ?? $complete['town'] ?? null);
        $state = $this->nullable($complete['state'] ?? $complete['region'] ?? null);
        $postal = $this->nullable($complete['postal_code'] ?? $complete['postcode'] ?? null);
        $street = $this->nullable($complete['street'] ?? $complete['road'] ?? null);
        $addressParts = array_filter([$street, $city, $state, $postal, $country['name_en'] ?? null]);
        $address = $addressParts ? implode(', ', $addressParts) : $this->nullable($row['address'] ?? null);

        $phone = $this->nullable($row['phone'] ?? null);
        $website = $this->cleanWebsite($row['website'] ?? null);
        $lat = $this->validCoord($row['latitude'] ?? null, -90, 90);
        $lng = $this->validCoord($row['longitude'] ?? null, -180, 180);
        $rating = $this->nullableFloat($row['review_rating'] ?? null);
        if ($rating !== null && ($rating < 0 || $rating > 5)) $rating = null;
        $reviewCount = $this->nullableInt($row['review_count'] ?? null);
        if ($reviewCount !== null && $reviewCount < 0) $reviewCount = null;

        $emails = $this->parseEmails($row['emails'] ?? null);
        $industryId = !empty($job['industry_id']) ? (int) $job['industry_id'] : null;
        $name = trim((string) ($row['title'] ?? 'Unnamed business'));

        $clean = [
            'business_name' => $name,
            'normalized_name' => $this->normalizeName($name),
            'industry_id' => $industryId,
            'country_id' => $country ? (int) $country['id'] : null,
            'region_id' => null,
            'country_code' => $countryCode ?: null,
            'city' => $city,
            'state_region' => $state,
            'postal_code' => $postal,
            'address' => $address,
            'phone' => $phone,
            'normalized_phone' => $this->normalizePhone($phone, $country['phone_code'] ?? null),
            'website' => $website,
            'website_domain' => $this->domainFromUrl($website),
            'description' => self::descriptionFromFields($row),
            'email_status' => $emails ? 'found' : 'not_checked',
            'google_data_id' => $this->nullable($row['data_id'] ?? null),
            'google_cid' => $this->nullable($row['cid'] ?? null),
            'google_place_id' => $this->nullable($row['place_id'] ?? null),
            'plus_code' => $this->nullable($row['plus_code'] ?? null),
            'latitude' => $lat,
            'longitude' => $lng,
            'rating' => $rating,
            'review_count' => $reviewCount,
            'business_status' => $this->nullable($row['status'] ?? null),
            'timezone' => $this->nullable($row['timezone'] ?? null),
            'source_first_seen' => now_utc(),
            'emails' => $emails,
            'source_url' => $this->nullable($row['link'] ?? null),
            'raw_complete_address' => $row['complete_address'] ?? null,
        ];
        $clean['completeness_score'] = $this->completeness($clean, $emails);
        return $clean;
    }

    private function completeness(array $b, array $emails): int
    {
        $score = 0;
        if (!empty($b['business_name'])) $score += 20;
        if (!empty($b['country_id']) && (!empty($b['city']) || !empty($b['address']))) $score += 15;
        if (!empty($b['phone'])) $score += 15;
        if (!empty($b['website'])) $score += 15;
        if ($emails) $score += 20;
        if ($b['latitude'] !== null && $b['longitude'] !== null) $score += 5;
        if ($b['rating'] !== null || $b['review_count'] !== null) $score += 5;
        if (!empty($b['industry_id'])) $score += 5;
        return min(100, $score);
    }

    private function findStrongMatch(array $clean): ?array
    {
        $pdo = db();
        foreach (['google_place_id', 'google_data_id', 'google_cid'] as $field) {
            if (empty($clean[$field])) continue;
            $stmt = $pdo->prepare("SELECT * FROM businesses WHERE {$field} = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$clean[$field], auth_id()]);
            $found = $stmt->fetch();
            if ($found) return $found;
        }

        // Grid/task coverage often returns the same business from overlapping
        // cells, and some scraper rows have no Google IDs. Merge those by
        // strong name + phone or name + website domain instead of duplicating.
        $name = trim((string) ($clean['normalized_name'] ?? ''));
        if ($name !== '') {
            foreach ([['normalized_phone', $clean['normalized_phone'] ?? null], ['website_domain', $clean['website_domain'] ?? null]] as [$field, $value]) {
                if (empty($value)) continue;
                $stmt = $pdo->prepare("SELECT * FROM businesses WHERE user_id = ? AND normalized_name = ? AND {$field} = ? LIMIT 1");
                $stmt->execute([auth_id(), $name, $value]);
                $found = $stmt->fetch();
                if ($found) return $found;
            }
        }
        return null;
    }

    private function insertBusiness(array $b): int
    {
        $cols = [
            'user_id', 'business_name', 'normalized_name', 'industry_id', 'country_id', 'region_id',
            'country_code', 'city', 'state_region', 'postal_code', 'address', 'phone',
            'normalized_phone', 'website', 'website_domain', 'description', 'email_status', 'google_data_id',
            'google_cid', 'google_place_id', 'plus_code', 'latitude', 'longitude', 'rating',
            'review_count', 'business_status', 'timezone', 'source_first_seen', 'completeness_score',
        ];
        $values = [auth_id()];
        foreach ($cols as $c) {
            if ($c === 'user_id') continue;
            $values[] = $b[$c] ?? null;
        }
        $values[] = now_utc();
        $values[] = now_utc();
        $sql = 'INSERT INTO businesses (' . implode(',', $cols) . ', created_at, updated_at) VALUES (' . rtrim(str_repeat('?,', count($cols) + 2), ',') . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($values);
        return (int) db()->lastInsertId();
    }

    private function mergeBusiness(array $existing, array $clean): void
    {
        $fillable = [
            'industry_id', 'country_id', 'region_id', 'country_code', 'city', 'state_region',
            'postal_code', 'address', 'phone', 'normalized_phone', 'website', 'website_domain', 'description',
            'google_cid', 'google_place_id', 'plus_code', 'latitude', 'longitude', 'rating',
            'review_count', 'business_status', 'timezone',
        ];
        $sets = [];
        $values = [];
        foreach ($fillable as $field) {
            if (empty($existing[$field]) && !empty($clean[$field])) {
                $sets[] = "{$field} = ?";
                $values[] = $clean[$field];
            }
        }
        if ($clean['completeness_score'] > (int) $existing['completeness_score']) {
            $sets[] = 'completeness_score = ?';
            $values[] = $clean['completeness_score'];
        }
        if ($clean['email_status'] === 'found' && $existing['email_status'] !== 'found') {
            $sets[] = 'email_status = ?';
            $values[] = 'found';
        }
        if ($sets) {
            $sets[] = 'updated_at = ?';
            $values[] = now_utc();
            $values[] = (int) $existing['id'];
            $values[] = auth_id();
            $stmt = db()->prepare('UPDATE businesses SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?');
            $stmt->execute($values);
        }
    }

    private function syncContacts(int $businessId, array $clean, array $raw): void
    {
        $pdo = db();
        $sourceUrl = $clean['website'] ?: $clean['source_url'];
        $hasPrimary = (int) $pdo->query('SELECT COUNT(*) FROM business_contacts WHERE business_id = ' . $businessId . " AND is_primary = 1")->fetchColumn() > 0;
        if (!empty($clean['phone'])) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO business_contacts (business_id, contact_type, contact_value, normalized_value, source_url, source_type, verification_status, is_primary) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$businessId, 'phone', $clean['phone'], $clean['normalized_phone'], $sourceUrl, 'scraper', 'unverified', $hasPrimary ? 0 : 1]);
            $hasPrimary = true;
        }
        foreach ($clean['emails'] as $email) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO business_contacts (business_id, contact_type, contact_value, normalized_value, source_url, source_type, verification_status, is_primary) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$businessId, 'email', $email, mb_strtolower($email), $sourceUrl, 'scraper', 'unverified', $hasPrimary ? 0 : 1]);
            $hasPrimary = true;
        }
    }

    private function linkSource(int $businessId, int $rawId, string $jobId): void
    {
        $stmt = db()->prepare('INSERT IGNORE INTO business_sources (business_id, raw_record_id, job_id, first_seen_at, last_seen_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$businessId, $rawId, $jobId, now_utc(), now_utc()]);
    }

    private function resolveRegion(?int $countryId, ?string $city): ?int
    {
        if (!$countryId || !$city) return null;
        $stmt = db()->prepare('SELECT id FROM regions WHERE country_id = ? AND (lower(name) = lower(?) OR lower(name_local) = lower(?)) LIMIT 1');
        $stmt->execute([$countryId, $city, $city]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /**
     * Extract a readable business introduction from the scraper's
     * `descriptions` / `about` fields. Values can be plain strings, JSON
     * arrays, or about objects depending on the scraper version.
     */
    public static function descriptionFromFields(array $row): ?string
    {
        $description = self::normalizeDescriptionValue($row['descriptions'] ?? null);
        if ($description === null) {
            $description = self::normalizeDescriptionValue($row['about'] ?? null);
        }
        return $description;
    }

    private static function normalizeDescriptionValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strtolower($value) === 'null') return null;
            if ($value[0] === '{' || $value[0] === '[') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return self::normalizeDescriptionValue($decoded);
                }
            }
            return mb_substr($value, 0, 5000);
        }
        if (is_array($value)) {
            // A single About item: {"name":"Service options","options":[...]}.
            if (!empty($value['name']) && isset($value['options']) && is_array($value['options'])) {
                $label = trim((string) $value['name']);
                $details = [];
                foreach ($value['options'] as $option) {
                    if (!is_array($option)) continue;
                    $name = trim((string) ($option['name'] ?? ''));
                    if ($name === '') continue;
                    $vals = [];
                    foreach ((array) ($option['values'] ?? []) as $val) {
                        if (is_scalar($val) && trim((string) $val) !== '') $vals[] = trim((string) $val);
                    }
                    $details[] = $name . ($vals ? ': ' . implode(', ', $vals) : '');
                }
                return $label . ($details ? ': ' . implode('; ', $details) : '');
            }
            foreach (['description', 'descriptions', 'summary', 'text', 'content', 'about', 'intro'] as $key) {
                if (array_key_exists($key, $value)) {
                    $nested = self::normalizeDescriptionValue($value[$key]);
                    if ($nested !== null) return $nested;
                }
            }
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $nested = self::normalizeDescriptionValue($item);
                    if ($nested !== null) $parts[] = $nested;
                } elseif (is_string($item) || is_numeric($item)) {
                    $part = trim((string) $item);
                    if ($part !== '' && strtolower($part) !== 'null') $parts[] = $part;
                }
            }
            if ($parts) return mb_substr(implode("\n", $parts), 0, 5000);
        }
        return null;
    }

    private function parseCompleteAddress(?string $value): array
    {
        if ($value === null || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseEmails(?string $value): array
    {
        if ($value === null || trim($value) === '') return [];
        $items = [];
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            array_walk_recursive($decoded, static function ($v) use (&$items) {
                if (is_string($v)) $items[] = $v;
            });
        } else {
            $items = preg_split('/[\s,;|]+/', $value) ?: [];
        }
        $emails = [];
        foreach ($items as $candidate) {
            $candidate = trim($candidate, " \t\n\r\0\x0B<>()[]\"'");
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $emails[mb_strtolower($candidate)] = mb_strtolower($candidate);
            }
        }
        return array_values($emails);
    }

    private function cleanWebsite(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^\[[^\]]*\]\(([^)]+)\)$/', $value, $m)) {
            $value = trim($m[1]);
        }
        $value = trim($value, " \t\n\r\0\x0B<>\"'");
        if ($value === '') return null;
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        $parts = parse_url($value);
        if (!$parts || empty($parts['host'])) return null;
        $scheme = strtolower($parts['scheme'] ?? 'https');
        if (!in_array($scheme, ['http', 'https'], true)) return null;
        return $value;
    }

    private function domainFromUrl(?string $url): ?string
    {
        if (!$url) return null;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        $host = mb_strtolower($host);
        if (str_starts_with($host, 'www.')) $host = substr($host, 4);
        return $host ?: null;
    }

    private function normalizePhone(?string $phone, ?string $phoneCode): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') return null;
        $code = preg_replace('/\D+/', '', (string) $phoneCode) ?? '';
        if (str_starts_with($phone, '+')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }
        if ($code !== '') {
            if (str_starts_with($digits, $code)) {
                return '+' . $digits;
            }
            if (str_starts_with($digits, '0')) {
                return '+' . $code . substr($digits, 1);
            }
            return '+' . $code . $digits;
        }
        return $digits;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function validCoord(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $n = (float) $value;
        return ($n >= $min && $n <= $max) ? $n : null;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return ($value !== null && $value !== '' && is_numeric($value)) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value !== null && $value !== '' && is_numeric($value)) ? (int) $value : null;
    }
}
