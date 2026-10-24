<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    /**
     * Get aggregated stats for the dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess($request);

        $refresh = $this->isRefreshRequested($request);
        $stats = $this->dashboardService->getStats($refresh);

        return response()->json([
            'data' => $stats,
            'message' => 'Dashboard stats retrieved successfully.',
        ]);
    }

    /**
     * Get recent ledger entries.
     */
    public function recentEntries(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess($request);

        $orderBy = (string) $request->input('order_by', 'created_at');
        $direction = (string) $request->input('order_direction', 'desc');
        $limit = (int) $request->input('limit', 5);
        $refresh = $this->isRefreshRequested($request);

        $entries = $this->dashboardService->getRecentEntries($orderBy, $direction, $limit, $refresh);

        return response()->json([
            'data' => $entries,
            'message' => 'Recent entries retrieved successfully.',
        ]);
    }

    /**
     * Get recent clients.
     */
    public function recentClients(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess($request);

        $orderBy = (string) $request->input('order_by', 'created_at');
        $direction = (string) $request->input('order_direction', 'desc');
        $limit = (int) $request->input('limit', 5);
        $refresh = $this->isRefreshRequested($request);

        $clients = $this->dashboardService->getRecentClients($orderBy, $direction, $limit, $refresh);

        return response()->json([
            'data' => $clients,
            'message' => 'Recent clients retrieved successfully.',
        ]);
    }

    /**
     * Get recent invoices.
     */
    public function recentInvoices(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess($request);

        $orderBy = (string) $request->input('order_by', 'created_at');
        $direction = (string) $request->input('order_direction', 'desc');
        $limit = (int) $request->input('limit', 5);
        $refresh = $this->isRefreshRequested($request);

        $invoices = $this->dashboardService->getRecentInvoices($orderBy, $direction, $limit, $refresh);

        return response()->json([
            'data' => $invoices,
            'message' => 'Recent invoices retrieved successfully.',
        ]);
    }

    /**
     * Clear all cached dashboard data.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess($request);

        $this->dashboardService->clearAllCache();

        return response()->json([
            'message' => 'Dashboard cache cleared successfully.',
        ]);
    }

    /**
     * Authorize user access to the admin dashboard.
     */
    private function authorizeDashboardAccess(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(HttpResponse::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $canView = $user->can('dashboard.view') || $user->hasAnyRole(['admin', 'super_admin', 'manager']);

        if (! $canView) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'You do not have permission to view the dashboard.');
        }
    }

    /**
     * Determine if cache refresh was requested.
     */
    private function isRefreshRequested(Request $request): bool
    {
        $refresh = $request->input('refresh');
        $clearCache = $request->input('clear_cache');

        return filter_var($refresh, FILTER_VALIDATE_BOOLEAN) || filter_var($clearCache, FILTER_VALIDATE_BOOLEAN);
    }
}
