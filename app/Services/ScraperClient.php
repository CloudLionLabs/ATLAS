<?php
declare(strict_types=1);

namespace App\Services;

final class ScraperClient
{
    private string $baseUrl;
    private int $connectTimeout;
    private int $readTimeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('scraper.base_url');
        $this->connectTimeout = max(1, (int) config('scraper.connect_timeout', 5));
        $this->readTimeout = max(1, (int) config('scraper.read_timeout', 45));
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The Scraper does not expose a dedicated health endpoint. We use the known
     * JSON jobs endpoint as a non-destructive connectivity probe.
     */
    public function status(bool $persist = true): array
    {
        $started = microtime(true);
        $response = $this->request('GET', '/api/v1/jobs');
        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($response['error'] !== null) {
            $result = [
                'state' => 'offline',
                'checked_at' => now_utc(),
                'latency_ms' => $latency,
                'message' => $response['error'],
                'job_count' => null,
                'http_status' => $response['status'],
            ];
        } else {
            $data = json_decode((string) $response['body'], true);
            $result = [
                'state' => is_array($data) ? 'online' : 'degraded',
                'checked_at' => now_utc(),
                'latency_ms' => $latency,
                'message' => is_array($data) ? '连接正常 · JSON 可读取' : '服务可达，但响应不是 JSON',
                'job_count' => is_array($data) ? count($data) : null,
                'http_status' => $response['status'],
            ];
        }

        if ($persist) {
            $cacheDir = base_path('storage/cache');
            if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
            file_put_contents($cacheDir . '/scraper-status.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        return $result;
    }

    /**
     * Returns only normalised fields verified against the current JSON sample.
     * Unknown fields are preserved under `raw`.
     */
    public function jobs(): array
    {
        $response = $this->request('GET', '/api/v1/jobs');
        if ($response['error'] !== null) {
            return ['ok' => false, 'jobs' => [], 'error' => $response['error'], 'http_status' => $response['status']];
        }
        $decoded = json_decode((string) $response['body'], true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'jobs' => [], 'error' => 'Scraper JSON 响应结构无法识别', 'http_status' => $response['status']];
        }

        $jobs = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;
            $id = $item['ID'] ?? $item['id'] ?? null;
            if (!$id) continue;
            $data = is_array($item['Data'] ?? null) ? $item['Data'] : [];
            $jobs[] = [
                'id' => (string) $id,
                'name' => (string) ($item['Name'] ?? $item['name'] ?? 'Untitled job'),
                'status' => (string) ($item['Status'] ?? $item['status'] ?? ''),
                'date' => $item['Date'] ?? $item['date'] ?? null,
                'keywords' => $data['keywords'] ?? [],
                'lang' => $data['lang'] ?? null,
                'zoom' => $data['zoom'] ?? null,
                'latitude' => $data['lat'] ?? $data['latitude'] ?? null,
                'longitude' => $data['lon'] ?? $data['lng'] ?? $data['longitude'] ?? null,
                'fast_mode' => $data['fast_mode'] ?? null,
                'radius' => $data['radius'] ?? null,
                'depth' => $data['depth'] ?? null,
                'email' => $data['email'] ?? null,
                'max_time' => $data['max_time'] ?? null,
                'raw' => $item,
            ];
        }
        return ['ok' => true, 'jobs' => $jobs, 'error' => null, 'http_status' => $response['status']];
    }

    /**
     * Submit a job. A timeout is treated as `submission_unknown`; callers must
     * never blindly retry because that can duplicate external tasks.
     */
    public function submit(array $payload): array
    {
        $response = $this->request('POST', '/scrape', $payload);
        if ($response['error'] !== null) {
            return [
                'ok' => false,
                'state' => 'submission_unknown',
                'job_id' => null,
                'error' => $response['error'],
                'http_status' => $response['status'],
                'body_excerpt' => mb_substr((string) $response['body'], 0, 500),
            ];
        }

        $jobId = $this->extractJobId((string) $response['body']);
        $ok = $response['status'] >= 200 && $response['status'] < 400;
        return [
            'ok' => $ok,
            'state' => $ok ? 'submitted' : 'failed',
            'job_id' => $jobId,
            'error' => $ok ? null : ('Scraper 返回 HTTP ' . $response['status']),
            'http_status' => $response['status'],
            'body_excerpt' => mb_substr(strip_tags((string) $response['body']), 0, 500),
        ];
    }

    public function extractJobId(string $body): ?string
    {
        // Current HTML responses include a UUID. Accept JSON `id`/`job_id` too,
        // but do not invent a job ID when neither is present.
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $body, $m)) {
            return $m[0];
        }
        $json = json_decode($body, true);
        if (is_array($json)) {
            foreach (['id', 'ID', 'job_id', 'jobId'] as $key) {
                if (!empty($json[$key]) && is_string($json[$key])) return $json[$key];
            }
            if (!empty($json['data']['id']) && is_string($json['data']['id'])) return $json['data']['id'];
        }
        return null;
    }

    /**
     * Delete a scraper job by id. The API exposes DELETE /api/v1/jobs/{id};
     * 404 is treated as already gone.
     */
    public function cancel(string $jobId): array
    {
        $jobId = trim($jobId);
        if ($jobId === '') {
            return ['ok' => false, 'error' => '缺少外部任务 ID', 'http_status' => 0];
        }
        $response = $this->request('DELETE', '/api/v1/jobs/' . rawurlencode($jobId));
        $ok = ($response['status'] >= 200 && $response['status'] < 300) || $response['status'] === 404;
        return [
            'ok' => $ok && $response['error'] === null,
            'error' => $response['error'] ?? ($ok ? null : 'Scraper 返回 HTTP ' . $response['status']),
            'http_status' => $response['status'],
            'body_excerpt' => mb_substr((string) $response['body'], 0, 300),
        ];
    }

    public function downloadCsv(string $jobId): array
    {
        $response = $this->request('GET', '/download?id=' . rawurlencode($jobId), null, 120);
        if ($response['error'] !== null) {
            return ['ok' => false, 'body' => '', 'error' => $response['error'], 'http_status' => $response['status']];
        }
        if ($response['status'] !== 200) {
            return ['ok' => false, 'body' => '', 'error' => '下载失败，HTTP ' . $response['status'], 'http_status' => $response['status']];
        }
        $body = (string) $response['body'];
        if (strlen($body) > 50 * 1024 * 1024) {
            return ['ok' => false, 'empty' => false, 'body' => '', 'error' => 'CSV 超过 50 MB 上限', 'http_status' => 413];
        }
        if (trim($body) === '') {
            return [
                'ok' => false,
                'empty' => true,
                'body' => '',
                'error' => 'CSV 为空（0 bytes）',
                'http_status' => $response['status'],
                'content_type' => $response['content_type'] ?? 'text/csv',
            ];
        }
        if (!str_contains(substr($body, 0, 4000), 'input_id')) {
            return ['ok' => false, 'empty' => false, 'body' => '', 'error' => 'CSV 表头校验失败', 'http_status' => 422];
        }
        return ['ok' => true, 'empty' => false, 'body' => $body, 'error' => null, 'http_status' => 200];
    }

    private function request(string $method, string $path, ?array $form = null, ?int $readTimeout = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $timeout = $readTimeout ?? $this->readTimeout;
        $headers = ['Accept: application/json, text/html;q=0.9'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'AtlasGlobalBusinessAdmin/1.0',
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($form !== null) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($form);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return [
            'body' => $body === false ? '' : $body,
            'error' => $error !== '' ? $error : null,
            'status' => $status,
            'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        ];
    }
}
