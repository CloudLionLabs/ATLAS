<?php
declare(strict_types=1);

use App\Modules\Admin\Api\Controller\ApiController;
use App\Modules\Admin\Businesses\Controller\BusinessController;
use App\Modules\Admin\Countries\Controller\CountryController;
use App\Modules\Admin\Dashboard\Controller\DashboardController;
use App\Modules\Admin\Exports\Controller\ExportController;
use App\Modules\Admin\Industries\Controller\IndustryController;
use App\Modules\Admin\Jobs\Controller\JobController;
use App\Modules\Admin\Keywords\Controller\KeywordController;
use App\Modules\Admin\Quality\Controller\DataQualityController;
use App\Modules\Admin\Settings\Controller\SettingsController;
use App\Modules\Admin\Users\Controller\UserController;

/** @var App\Core\Router $router */
$router->get('/account', [UserController::class, 'account']);
$router->post('/account/profile', [UserController::class, 'updateAccount']);
$router->post('/account/password', [UserController::class, 'changePassword']);

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->post('/users/{id}', [UserController::class, 'update']);
$router->post('/users/{id}/password', [UserController::class, 'resetPassword']);

$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/jobs', [JobController::class, 'index']);
$router->get('/jobs/create', [JobController::class, 'create']);
$router->post('/jobs', [JobController::class, 'store']);
$router->get('/jobs/batch', [JobController::class, 'batch']);
$router->post('/jobs/batch/preview', [JobController::class, 'batchPreview']);
$router->post('/jobs/batch/create', [JobController::class, 'batchCreate']);
$router->get('/jobs/{id}', [JobController::class, 'show']);
$router->post('/jobs/{id}/sync', [JobController::class, 'sync']);
$router->post('/jobs/{id}/retry', [JobController::class, 'retry']);
$router->post('/jobs/delete-cancelled', [JobController::class, 'deleteCancelled']);
$router->post('/jobs/delete-empty', [JobController::class, 'deleteEmpty']);
$router->post('/jobs/delete-unbatched', [JobController::class, 'deleteUnbatched']);
$router->post('/jobs/{id}/delete', [JobController::class, 'destroy']);
$router->post('/jobs/{id}/delete-cancelled', [JobController::class, 'deleteCancelledBatch']);
$router->post('/jobs/batches/{id}/queue-toggle', [JobController::class, 'toggleQueueBatch']);
$router->post('/jobs/batches/{id}/cancel-all', [JobController::class, 'cancelAllBatch']);
$router->post('/jobs/batches/{id}/delete', [JobController::class, 'deleteBatch']);
$router->post('/jobs/{id}/queue-toggle', [JobController::class, 'toggleQueue']);
$router->post('/jobs/{id}/cancel', [JobController::class, 'cancel']);
$router->post('/jobs/{id}/cancel-all', [JobController::class, 'cancelAll']);
$router->post('/jobs/{id}/import', [JobController::class, 'importCsv']);
$router->post('/jobs/import/upload', [JobController::class, 'uploadCsv']);
$router->get('/jobs/{id}/download', [JobController::class, 'download']);

$router->get('/businesses', [BusinessController::class, 'index']);
$router->post('/businesses/enrich-emails', [BusinessController::class, 'enrichEmails']);
$router->post('/businesses/{id}/enrich-email', [BusinessController::class, 'enrichEmail']);
$router->get('/businesses/{id}', [BusinessController::class, 'show']);
$router->post('/businesses/{id}/update', [BusinessController::class, 'update']);
$router->post('/businesses/{id}/email-status', [BusinessController::class, 'updateEmailStatus']);

$router->get('/countries', [CountryController::class, 'index']);
$router->post('/countries', [CountryController::class, 'store']);
$router->post('/countries/{id}', [CountryController::class, 'update']);
$router->post('/regions', [CountryController::class, 'storeRegion']);
$router->post('/regions/{id}', [CountryController::class, 'updateRegion']);

$router->get('/industries', [IndustryController::class, 'index']);
$router->post('/industries', [IndustryController::class, 'store']);
$router->post('/industries/{id}', [IndustryController::class, 'update']);
$router->post('/industries/{id}/translation', [IndustryController::class, 'translation']);

$router->get('/keywords', [KeywordController::class, 'index']);
$router->post('/keywords', [KeywordController::class, 'store']);
$router->post('/keywords/bulk', [KeywordController::class, 'bulk']);
$router->post('/keywords/{id}', [KeywordController::class, 'update']);
$router->post('/keywords/{id}/toggle', [KeywordController::class, 'toggle']);

$router->get('/data-quality', [DataQualityController::class, 'index']);
$router->get('/exports', [ExportController::class, 'index']);
$router->post('/exports/csv', [ExportController::class, 'csv']);
$router->post('/exports/xlsx', [ExportController::class, 'xlsx']);
$router->get('/exports/download/{file}', [ExportController::class, 'download']);

$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/scraper/test', [SettingsController::class, 'testScraper']);
$router->post('/settings', [SettingsController::class, 'save']);
$router->post('/settings/scraper/sync', [SettingsController::class, 'syncScraperJobs']);

$router->get('/api/system/status', [ApiController::class, 'status']);
$router->get('/api/regions', [ApiController::class, 'regions']);
$router->get('/api/cities', [ApiController::class, 'cities']);
$router->get('/api/region-boundary', [ApiController::class, 'regionBoundary']);
$router->get('/api/geocode', [ApiController::class, 'geocode']);
$router->get('/api/reverse', [ApiController::class, 'reverse']);
$router->get('/api/dashboard/stats', [ApiController::class, 'dashboardStats']);
$router->get('/api/businesses', [ApiController::class, 'businesses']);
$router->get('/api/jobs', [ApiController::class, 'jobs']);
$router->get('/api/jobs/active', [ApiController::class, 'activeJobs']);
$router->get('/api/scraper/jobs', [ApiController::class, 'scraperJobs']);
