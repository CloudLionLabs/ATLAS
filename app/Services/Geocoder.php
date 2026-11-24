<?php
declare(strict_types=1);

namespace App\Services;

final class Geocoder
{
    private string $baseUrl;
    private string $userAgent;
    private string $language;
    private string $cacheDir;
    private float $lastRequestAt = 0.0;

    public function __construct()
    {
        $this->baseUrl = (string) config('geocoder.url');
        $this->userAgent = (string) config('geocoder.user_agent');
        $this->language = (string) config('geocoder.language');
        $this->cacheDir = base_path('storage/cache/geocode');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['ok' => false, 'error' => '请输入至少 2 个字符'];
        }
        $params = [
            'format' => 'jsonv2',
            'q' => $query,
            'addressdetails' => 1,
            'limit' => max(1, min(10, $limit)),
            'accept-language' => $this->language,
        ];
        $raw = $this->cachedGet('search-' . md5(json_encode($params)), '/search', $params);
        if (!$raw['ok']) {
            return $raw;
        }
        $decoded = json_decode($raw['body'], true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => '地理编码响应格式异常'];
        }
        $results = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['lat'], $item['lon'])) continue;
            $address = is_array($item['address'] ?? null) ? $item['address'] : [];
            $results[] = [
                'display_name' => (string) ($item['display_name'] ?? ''),
                'name' => (string) ($item['name'] ?? ($item['display_name'] ?? '')),
                'lat' => (float) $item['lat'],
                'lng' => (float) $item['lon'],
                'type' => (string) ($item['addresstype'] ?? $item['type'] ?? 'place'),
                'category' => (string) ($item['category'] ?? ''),
                'boundingbox' => array_map('floatval', $item['boundingbox'] ?? []),
                'address' => [
                    'country' => $address['country'] ?? null,
                    'country_code' => strtoupper((string) ($address['country_code'] ?? '')),
                    'state' => $address['state'] ?? $address['region'] ?? null,
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null,
                    'postcode' => $address['postcode'] ?? null,
                ],
            ];
        }
        return ['ok' => true, 'results' => $results, 'error' => null, 'cached' => $raw['cached']];
    }

    public function reverse(float $lat, float $lng): array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return ['ok' => false, 'error' => '坐标超出有效范围'];
        }
        $params = [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'zoom' => 12,
            'addressdetails' => 1,
            'accept-language' => $this->language,
        ];
        $raw = $this->cachedGet('reverse-' . md5(json_encode($params)), '/reverse', $params);
        if (!$raw['ok']) {
            return $raw;
        }
        $item = json_decode($raw['body'], true);
        if (!is_array($item)) {
            return ['ok' => false, 'error' => '逆地理编码响应格式异常'];
        }
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];
        return [
            'ok' => true,
            'result' => [
                'display_name' => (string) ($item['display_name'] ?? ''),
                'lat' => (float) ($item['lat'] ?? $lat),
                'lng' => (float) ($item['lon'] ?? $lng),
                'country' => $address['country'] ?? null,
                'country_code' => strtoupper((string) ($address['country_code'] ?? '')),
                'state' => $address['state'] ?? $address['region'] ?? null,
                'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null,
                'suburb' => $address['suburb'] ?? $address['neighbourhood'] ?? null,
                'postcode' => $address['postcode'] ?? null,
                'road' => $address['road'] ?? null,
                'house_number' => $address['house_number'] ?? null,
            ],
            'error' => null,
            'cached' => $raw['cached'],
        ];
    }

    /**
     * Fetch an administrative city boundary and derive its approximate area
     * and maximum distance from the selected city center. Used to size the
     * city-wide coverage grid without hard-coding a radius per city.
     */
    public function boundary(string $query, ?float $centerLat = null, ?float $centerLng = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['ok' => false, 'error' => '城市名称过短', 'available' => false];
        }
        $params = [
            'format' => 'jsonv2',
            'q' => $query,
            'polygon_geojson' => 1,
            'limit' => 1,
            'featureType' => 'city',
            'accept-language' => $this->language,
        ];
        $raw = $this->cachedGet('boundary-' . md5(json_encode($params)), '/search', $params);
        if (!$raw['ok']) {
            return ['ok' => false, 'error' => $raw['error'] ?? '行政区边界服务不可用', 'available' => false];
        }
        $decoded = json_decode($raw['body'], true);
        if (!is_array($decoded) || !isset($decoded[0]['geojson'])) {
            return ['ok' => true, 'available' => false, 'error' => '未找到城市行政区边界'];
        }
        $item = $decoded[0];
        $geojson = $item['geojson'];
        $type = (string) ($geojson['type'] ?? '');
        $coordinates = $geojson['coordinates'] ?? null;
        if (!is_array($coordinates)) {
            return ['ok' => true, 'available' => false, 'error' => '城市边界几何为空'];
        }
        $polygons = match ($type) {
            'Polygon' => [$coordinates],
            'MultiPolygon' => $coordinates,
            default => [],
        };
        if (!$polygons) {
            return ['ok' => true, 'available' => false, 'error' => '城市边界几何类型不支持'];
        }
        $points = [];
        $area = 0.0;
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || !$polygon) continue;
            $area += $this->ringAreaM2($polygon[0] ?? []);
            foreach ($polygon as $ring) {
                foreach ((array) $ring as $pair) {
                    if (is_array($pair) && count($pair) >= 2 && is_numeric($pair[0]) && is_numeric($pair[1])) {
                        $points[] = [(float) $pair[0], (float) $pair[1]];
                    }
                }
            }
        }
        if (!$points || $area <= 0) {
            return ['ok' => true, 'available' => false, 'error' => '城市边界面积无法计算'];
        }
        $lat = $centerLat ?? (float) ($item['lat'] ?? 0);
        $lng = $centerLng ?? (float) ($item['lon'] ?? 0);
        if ($lat === 0.0 && $lng === 0.0) {
            $bbox = array_map('floatval', $item['boundingbox'] ?? []);
            if (count($bbox) === 4) {
                $lat = ($bbox[0] + $bbox[1]) / 2;
                $lng = ($bbox[2] + $bbox[3]) / 2;
            }
        }
        $maxRadius = 0.0;
        foreach ($points as [$pLng, $pLat]) {
            $maxRadius = max($maxRadius, $this->haversineMeters($lat, $lng, $pLat, $pLng));
        }
        return [
            'ok' => true,
            'available' => true,
            'display_name' => (string) ($item['display_name'] ?? $query),
            'area_km2' => round($area / 1_000_000, 1),
            'radius_km' => round($maxRadius / 1000, 1),
            'cached' => $raw['cached'] ?? false,
            'error' => null,
        ];
    }

    private function ringAreaM2(array $ring): float
    {
        if (count($ring) < 3) return 0.0;
        $earth = 6_371_000.0;
        $lat0 = 0.0;
        $vals = [];
        foreach ($ring as $pair) {
            if (!is_array($pair) || count($pair) < 2) continue;
            $vals[] = deg2rad((float) $pair[1]);
            if (count($vals) === 1) $lat0 = (float) $pair[1];
        }
        if (count($vals) < 3) return 0.0;
        $lat0 = array_sum($vals) / count($vals);
        $cos = cos($lat0);
        $sum = 0.0;
        $count = count($ring);
        for ($i = 0; $i < $count; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $count];
            if (!is_array($a) || !is_array($b) || count($a) < 2 || count($b) < 2) continue;
            $x1 = deg2rad((float) $a[0]) * $earth * $cos;
            $y1 = deg2rad((float) $a[1]) * $earth;
            $x2 = deg2rad((float) $b[0]) * $earth * $cos;
            $y2 = deg2rad((float) $b[1]) * $earth;
            $sum += ($x1 * $y2) - ($x2 * $y1);
        }
        return abs($sum) / 2;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    private function cachedGet(string $key, string $path, array $params): array
    {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (is_file($file) && (time() - filemtime($file)) < 86400) {
            $body = file_get_contents($file);
            if ($body !== false) {
                return ['ok' => true, 'body' => $body, 'error' => null, 'cached' => true];
            }
        }

        // Nominatim usage policy: at most one request per second from this app.
        $elapsed = microtime(true) - $this->lastRequestAt;
        if ($elapsed < 1.0) {
            usleep((int) ((1.0 - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);

        $url = $this->baseUrl . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $error !== '') {
            return ['ok' => false, 'body' => '', 'error' => '地址服务暂时不可用：' . $error, 'cached' => false];
        }
        if ($status !== 200) {
            return ['ok' => false, 'body' => '', 'error' => '地址服务返回 HTTP ' . $status, 'cached' => false];
        }
        file_put_contents($file, $body);
        return ['ok' => true, 'body' => (string) $body, 'error' => null, 'cached' => false];
    }
}
