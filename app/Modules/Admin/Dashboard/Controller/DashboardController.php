<?php
declare(strict_types=1);

namespace App\Modules\Admin\Dashboard\Controller;

use App\Core\Controller;
use App\Services\ScraperClient;
use App\Services\StatsService;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $stats = (new StatsService())->dashboard();
        $status = scraper_status_cache();
        $coverageJson = json_encode($stats['coverage'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->view('Admin/Dashboard/View/index', [
            'pageTitle' => '指挥中心',
            'active' => 'dashboard',
            'stats' => $stats,
            'scraperStatus' => $status,
            'coverageJson' => $coverageJson,
            'mapConfig' => config('map'),
            'useMap' => true,
        ]);
    }
}
