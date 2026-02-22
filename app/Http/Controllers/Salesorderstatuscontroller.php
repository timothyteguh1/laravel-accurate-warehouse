<?php

namespace App\Http\Controllers;

use App\Services\AccurateService;
use Illuminate\Http\Request;

class SalesOrderStatusController extends Controller
{
    public function __construct(private AccurateService $accurate) {}

    // ─── Mapping nilai API → Label Indonesia ─────────────────────────────────────
    // PENTING: Nilai ASLI dari API adalah QUEUE/WAITING/PROCEED
    // bukan OPEN/PARTIAL/CLOSED seperti dokumentasi umum

    private function statusMap(): array
    {
        return [
            'QUEUE'   => ['label' => 'Menunggu diproses', 'color' => 'queue'],
            'WAITING' => ['label' => 'Sebagian diproses', 'color' => 'waiting'],
            'PROCEED' => ['label' => 'Terproses',         'color' => 'proceed'],
        ];
    }

    // ─── List SO ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        // Filter status: QUEUE | WAITING | PROCEED | null (semua)
        $filterStatus = $request->query('status');

        // Ambil SEMUA SO dari API tanpa statusFilter
        // karena API Accurate tidak menerima QUEUE/WAITING/PROCEED sebagai nilai statusFilter
        // Filtering kita lakukan sendiri di PHP setelah dapat data
        $params = [
            'fields'      => 'id,number,transDate,customerName,status,totalAmount,description',
            'sp.page'     => 1,
            'sp.pageSize' => 100,
            'sp.sort'     => 'transDate|desc',
        ];

        $result = $this->accurate->get('sales-order/list.do', $params);

        // ── Error dari service (NO_TOKEN / timeout / host expired) ──
        if (isset($result['status']) && $result['status'] === false) {
            return view('sales-orders.index', [
                'error'        => $result['message'] ?? 'Gagal terhubung ke Accurate.',
                'errorType'    => $result['error_code'] ?? 'UNKNOWN',
                'orders'       => [],
                'allCount'     => 0,
                'counts'       => ['QUEUE' => 0, 'WAITING' => 0, 'PROCEED' => 0],
                'statusMap'    => $this->statusMap(),
                'filterStatus' => $filterStatus,
                'pagination'   => [],
                'rawJson'      => json_encode($result, JSON_PRETTY_PRINT),
            ]);
        }

        // ── Error dari Accurate API ──
        if (($result['s'] ?? false) !== true) {
            return view('sales-orders.index', [
                'error'        => implode(', ', (array) ($result['d'] ?? ['Response tidak valid.'])),
                'errorType'    => 'API_ERROR',
                'orders'       => [],
                'allCount'     => 0,
                'counts'       => ['QUEUE' => 0, 'WAITING' => 0, 'PROCEED' => 0],
                'statusMap'    => $this->statusMap(),
                'filterStatus' => $filterStatus,
                'pagination'   => [],
                'rawJson'      => json_encode($result, JSON_PRETTY_PRINT),
            ]);
        }

        $allOrders = $result['d'] ?? [];

        // ── Hitung jumlah per status (untuk badge di filter button) ──
        $counts = [
            'QUEUE'   => count(array_filter($allOrders, fn($so) => ($so['status'] ?? '') === 'QUEUE')),
            'WAITING' => count(array_filter($allOrders, fn($so) => ($so['status'] ?? '') === 'WAITING')),
            'PROCEED' => count(array_filter($allOrders, fn($so) => ($so['status'] ?? '') === 'PROCEED')),
        ];

        // ── Filter di PHP — bukan kirim statusFilter ke API ──
        $filteredOrders = $filterStatus
            ? array_values(array_filter($allOrders, fn($so) => ($so['status'] ?? '') === $filterStatus))
            : $allOrders;

        return view('sales-orders.index', [
            'orders'       => $filteredOrders,
            'allCount'     => count($allOrders),
            'counts'       => $counts,
            'pagination'   => $result['sp'] ?? [],
            'statusMap'    => $this->statusMap(),
            'filterStatus' => $filterStatus,
            'error'        => null,
            'errorType'    => null,
            'rawJson'      => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ─── JSON detail 1 SO — debug cepat via browser ──────────────────────────────

    public function show(int $id)
    {
        $result = $this->accurate->get('sales-order/detail.do', ['id' => $id]);
        $status = $result['d']['status'] ?? null;

        return response()->json([
            'api_value'  => $status,
            'label_indo' => $this->statusMap()[$status]['label'] ?? 'Tidak diketahui',
            'raw'        => $result,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}