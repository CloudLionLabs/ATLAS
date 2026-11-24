<?php
declare(strict_types=1);

namespace App\Modules\Admin\Quality\Controller;

use App\Core\Controller;
use App\Services\StatsService;

final class DataQualityController extends Controller
{
    public function index(): string
    {
        $quality = (new StatsService())->quality();
        $pdo = db();
        $uid = $this->userId();
        $missingPhone = $pdo->prepare("SELECT b.id, b.business_name, b.city, c.iso2 FROM businesses b LEFT JOIN countries c ON c.id=b.country_id WHERE b.user_id = ? AND (b.phone IS NULL OR b.phone='') ORDER BY b.completeness_score DESC LIMIT 8");
        $missingPhone->execute([$uid]);
        $missingPhone = $missingPhone->fetchAll();
        $missingWebsite = $pdo->prepare("SELECT b.id, b.business_name, b.city, c.iso2 FROM businesses b LEFT JOIN countries c ON c.id=b.country_id WHERE b.user_id = ? AND (b.website IS NULL OR b.website='') ORDER BY b.completeness_score DESC LIMIT 8");
        $missingWebsite->execute([$uid]);
        $missingWebsite = $missingWebsite->fetchAll();
        $missingEmail = $pdo->prepare("SELECT b.id, b.business_name, b.city, c.iso2 FROM businesses b LEFT JOIN countries c ON c.id=b.country_id WHERE b.user_id = ? AND b.email_status <> 'found' AND NOT EXISTS (SELECT 1 FROM business_contacts bc WHERE bc.business_id=b.id AND bc.contact_type='email') ORDER BY b.completeness_score DESC LIMIT 8");
        $missingEmail->execute([$uid]);
        $missingEmail = $missingEmail->fetchAll();
        $missingIndustry = $pdo->prepare("SELECT b.id, b.business_name, b.city, c.iso2 FROM businesses b LEFT JOIN countries c ON c.id=b.country_id WHERE b.user_id = ? AND b.industry_id IS NULL ORDER BY b.completeness_score DESC LIMIT 8");
        $missingIndustry->execute([$uid]);
        $missingIndustry = $missingIndustry->fetchAll();
        $duplicatePhones = $pdo->prepare("SELECT normalized_phone, COUNT(*) n, GROUP_CONCAT(business_name SEPARATOR ' · ') names FROM businesses WHERE user_id = ? AND normalized_phone IS NOT NULL GROUP BY normalized_phone HAVING n > 1 ORDER BY n DESC LIMIT 8");
        $duplicatePhones->execute([$uid]);
        $duplicatePhones = $duplicatePhones->fetchAll();
        $failedImports = $pdo->prepare("SELECT l.*, j.task_name FROM import_logs l JOIN scrape_jobs j ON j.id=l.job_id WHERE j.user_id = ? AND l.status IN ('failed','partial') ORDER BY l.created_at DESC LIMIT 6");
        $failedImports->execute([$uid]);
        $failedImports = $failedImports->fetchAll();
        $topQuality = $pdo->prepare("SELECT b.*, c.iso2, i.name AS industry_name FROM businesses b LEFT JOIN countries c ON c.id=b.country_id LEFT JOIN industries i ON i.id=b.industry_id WHERE b.user_id = ? ORDER BY b.completeness_score DESC LIMIT 5");
        $topQuality->execute([$uid]);
        $topQuality = $topQuality->fetchAll();

        return $this->view('Admin/Quality/View/index', [
            'pageTitle' => '数据质量',
            'active' => 'quality',
            'quality' => $quality,
            'missingPhone' => $missingPhone,
            'missingWebsite' => $missingWebsite,
            'missingEmail' => $missingEmail,
            'missingIndustry' => $missingIndustry,
            'duplicatePhones' => $duplicatePhones,
            'failedImports' => $failedImports,
            'topQuality' => $topQuality,
            'emailFinderEnabled' => (string) app_setting('email_finder_enabled', '0') === '1',
        ]);
    }
}
