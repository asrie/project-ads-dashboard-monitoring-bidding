<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BidderController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\GamController;
use App\Http\Controllers\Api\V1\IngestController;
use App\Http\Controllers\Api\V1\NetworkAdsController;
use App\Http\Controllers\Api\V1\PrebidController;
use App\Http\Controllers\Api\V1\PreviewController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\SlotController;
use App\Http\Controllers\Api\V1\WebVitalsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Public auth ----
    Route::post('auth/login', [AuthController::class, 'login']);

    // ---- Telemetry ingestion (browser push from publisher sites) ----
    // Guarded by an ingest key (not JWT) + rate limited. Prebid is push, not pull.
    Route::post('ingest/prebid', [IngestController::class, 'prebid'])
        ->middleware(['ingest.key', 'throttle:prebid-ingest']);

    // ---- Authenticated (JWT) ----
    Route::middleware('auth:api')->group(function () {
        // Auth session
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Dashboard overview
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);

        // Domains (reference list for filters & scanners)
        Route::get('domains', [DomainController::class, 'index']);

        // Slot performance
        Route::get('slots', [SlotController::class, 'index']);
        Route::get('slots/{id}', [SlotController::class, 'show']);

        // Bidding monitoring
        Route::get('bidders', [BidderController::class, 'index']);
        Route::get('bidders/{id}', [BidderController::class, 'show']);

        // Prebid health
        Route::get('prebid/health', [PrebidController::class, 'health']);
        Route::get('prebid/auctions', [PrebidController::class, 'auctions']);

        // GAM monitoring
        Route::get('gam/health', [GamController::class, 'health']);
        Route::get('gam/requests', [GamController::class, 'requests']);

        // Server health (uptime & response time)
        Route::get('server/health', [ServerController::class, 'health']);
        Route::get('server/checks', [ServerController::class, 'checks']);

        // Ad Layout preview (Playwright screenshot + slot overlay)
        Route::get('previews', [PreviewController::class, 'index']);
        Route::get('previews/{id}', [PreviewController::class, 'show']);
        Route::post('previews/capture', [PreviewController::class, 'capture'])
            ->middleware(['role:admin,programmatic_revenue,adops,tech', 'throttle:page-preview']);

        // Security Site Inspector (OSINT & security scanner)
        Route::get('security/scans', [SecurityController::class, 'index']);
        Route::get('security/scans/{id}', [SecurityController::class, 'show']);
        Route::get('security/scans/{id}/export', [SecurityController::class, 'export']);
        // Running a scan is a write/expensive action — viewers are read-only.
        Route::post('security/scan', [SecurityController::class, 'scan'])
            ->middleware('role:admin,programmatic_revenue,adops,tech');

        // Network ads
        Route::get('network-ads', [NetworkAdsController::class, 'index']);
        Route::get('network-ads/heavy-requests', [NetworkAdsController::class, 'heavyRequests']);

        // Web Core Vitals
        Route::get('web-vitals', [WebVitalsController::class, 'summary']);
        Route::get('web-vitals/pages', [WebVitalsController::class, 'pages']);

        // Alerts & insights
        Route::get('alerts', [AlertController::class, 'index']);
        Route::get('insights', [AlertController::class, 'insights']);
        Route::get('alerts/{id}', [AlertController::class, 'show']);

        // Acknowledging an alert is a write action — viewers are read-only (CLAUDE.md RBAC).
        Route::patch('alerts/{id}/acknowledge', [AlertController::class, 'acknowledge'])
            ->middleware('role:admin,programmatic_revenue,adops,tech');
    });
});
