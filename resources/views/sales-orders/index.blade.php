<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order Status Debug — Accurate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #13131a;
            --surface2: #1c1c28;
            --border: #2a2a3d;
            --text: #e8e8f0;
            --muted: #6b6b8a;
            --accent: #7c6af7;

            /* Nilai ASLI API Accurate: QUEUE | WAITING | PROCEED */
            --queue:   #f0b429;   /* kuning  — Menunggu diproses */
            --waiting: #f97316;   /* oranye  — Sebagian diproses */
            --proceed: #22c55e;   /* hijau   — Terproses         */
            --unknown: #6b6b8a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
        }

        /* ── Header ── */
        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
        }

        .header-title {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .header-title h1 {
            font-family: 'Space Mono', monospace;
            font-size: 1rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.05em;
        }

        .header-title p {
            font-size: 0.75rem;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
        }

        .db-badge {
            background: #1a1a2e;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 14px;
            font-family: 'Space Mono', monospace;
            font-size: 0.72rem;
            color: var(--accent);
        }

        /* ── Main ── */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* ── Status Legend ── */
        .legend {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }

        .legend-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-dot.queue   { background: var(--queue);   box-shadow: 0 0 8px var(--queue); }
        .legend-dot.waiting { background: var(--waiting); box-shadow: 0 0 8px var(--waiting); }
        .legend-dot.proceed { background: var(--proceed); box-shadow: 0 0 8px var(--proceed); }

        .legend-info .api-val {
            font-family: 'Space Mono', monospace;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .legend-info .api-val.queue   { color: var(--queue); }
        .legend-info .api-val.waiting { color: var(--waiting); }
        .legend-info .api-val.proceed { color: var(--proceed); }

        .legend-info .label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Filters ── */
        .filters {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filters-label {
            font-size: 0.78rem;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            margin-right: 4px;
        }

        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            font-size: 0.78rem;
            font-family: 'Space Mono', monospace;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
        }

        .filter-btn:hover { border-color: var(--accent); color: var(--accent); }
        .filter-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .filter-btn.queue.active   { background: var(--queue);   border-color: var(--queue); }
        .filter-btn.waiting.active { background: var(--waiting); border-color: var(--waiting); }
        .filter-btn.proceed.active { background: var(--proceed); border-color: var(--proceed); }

        /* ── Table ── */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header-bar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header-bar h2 {
            font-family: 'Space Mono', monospace;
            font-size: 0.82rem;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .count-badge {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 3px 10px;
            font-family: 'Space Mono', monospace;
            font-size: 0.75rem;
            color: var(--text);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-family: 'Space Mono', monospace;
            font-size: 0.7rem;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 700;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--surface2); }

        td {
            padding: 14px 16px;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        /* Number column */
        td.no-col {
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            color: var(--accent);
        }

        /* ── Status Badge (KEY COLUMN) ── */
        .status-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-api {
            font-family: 'Space Mono', monospace;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 4px;
            display: inline-block;
            letter-spacing: 0.05em;
        }

        /* Badge warna sesuai nilai ASLI API */
        .status-api.QUEUE   { background: rgba(240,180,41,0.15); color: var(--queue);   border: 1px solid rgba(240,180,41,0.3); }
        .status-api.WAITING { background: rgba(249,115,22,0.15); color: var(--waiting); border: 1px solid rgba(249,115,22,0.3); }
        .status-api.PROCEED { background: rgba(34,197,94,0.15);  color: var(--proceed); border: 1px solid rgba(34,197,94,0.3); }
        .status-api.UNKNOWN { background: rgba(107,107,138,0.15); color: var(--unknown); border: 1px solid rgba(107,107,138,0.3); }

        .status-label {
            font-size: 0.72rem;
            color: var(--muted);
        }

        /* Amount */
        td.amount-col {
            font-family: 'Space Mono', monospace;
            font-size: 0.82rem;
            text-align: right;
        }

        /* ── Error / Login Box ── */
        .alert {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-icon { font-size: 1.2rem; }

        .alert-text h3 {
            font-size: 0.9rem;
            color: #f87171;
            margin-bottom: 4px;
        }

        .alert-text p {
            font-size: 0.82rem;
            color: var(--muted);
        }

        /* ── Token Form ── */
        .token-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
            margin-top: 20px;
        }

        .token-form h3 {
            font-family: 'Space Mono', monospace;
            font-size: 0.9rem;
            color: var(--text);
            margin-bottom: 6px;
        }

        .token-form p {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-input {
            flex: 1;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            outline: none;
            transition: border-color 0.15s;
        }

        .form-input:focus { border-color: var(--accent); }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: opacity 0.15s;
        }

        .btn-primary:hover { opacity: 0.85; }

        /* ── Raw JSON Toggle ── */
        .raw-section {
            margin-top: 28px;
        }

        .raw-toggle {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 18px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            font-size: 0.78rem;
            cursor: pointer;
            width: 100%;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-color 0.15s;
        }

        .raw-toggle:hover { border-color: var(--accent); color: var(--accent); }

        .raw-json {
            display: none;
            background: #060608;
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 20px;
            overflow: auto;
            max-height: 400px;
        }

        .raw-json pre {
            font-family: 'Space Mono', monospace;
            font-size: 0.72rem;
            color: #a0ffa0;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .raw-json.show { display: block; }

        /* ── Empty State ── */
        .empty {
            padding: 60px 20px;
            text-align: center;
        }

        .empty .icon { font-size: 2.5rem; margin-bottom: 12px; }
        .empty p { color: var(--muted); font-size: 0.9rem; }

        /* ── Pagination Info ── */
        .pagination-info {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            font-family: 'Space Mono', monospace;
            font-size: 0.72rem;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .legend { grid-template-columns: 1fr; }
            .form-row { flex-direction: column; }
            table { font-size: 0.78rem; }
            th, td { padding: 10px 10px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-title">
        <h1>// ACCURATE — SALES ORDER STATUS DEBUG</h1>
        <p>Melihat nilai status SO langsung dari API response</p>
    </div>
    <div class="db-badge">DB_ID: {{ config('accurate.db_id') }}</div>
</div>

<div class="container">

    <!-- ── Alert Error ── -->
    @if($error)
    <div class="alert">
        <span class="alert-icon">⚠️</span>
        <div class="alert-text">
            <h3>
                @if(($errorType ?? '') === 'NO_TOKEN')
                    Token / Session Belum Ada
                @elseif(str_contains($error, 'Timeout') || str_contains($error, 'cURL'))
                    Koneksi ke Accurate Timeout
                @else
                    Terjadi Kesalahan
                @endif
            </h3>
            <p>{{ $error }}</p>
            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('accurate.open_db') }}"
                    style="background:var(--accent);color:#fff;padding:6px 16px;border-radius:6px;font-size:0.78rem;text-decoration:none;font-weight:600;">
                    🔄 Refresh Open-DB
                </a>
                <a href="{{ route('accurate.auth') }}"
                    style="background:var(--surface2);color:var(--muted);padding:6px 16px;border-radius:6px;font-size:0.78rem;text-decoration:none;border:1px solid var(--border);">
                    🔑 Re-Auth Accurate
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- ── Status Legend ── -->
    <!-- Legend — Nilai ASLI dari API Accurate -->
    <div class="legend">
        <div class="legend-card">
            <div class="legend-dot queue"></div>
            <div class="legend-info">
                <div class="api-val queue">QUEUE</div>
                <div class="label">Menunggu diproses</div>
            </div>
        </div>
        <div class="legend-card">
            <div class="legend-dot waiting"></div>
            <div class="legend-info">
                <div class="api-val waiting">WAITING</div>
                <div class="label">Sebagian diproses</div>
            </div>
        </div>
        <div class="legend-card">
            <div class="legend-dot proceed"></div>
            <div class="legend-info">
                <div class="api-val proceed">PROCEED</div>
                <div class="label">Terproses</div>
            </div>
        </div>
    </div>

    <!-- ── Filter Buttons ── -->
    <!-- Filter dilakukan di PHP — bukan pakai statusFilter API -->
    <div class="filters">
        <span class="filters-label">Filter:</span>

        <a href="{{ route('so.status.index') }}"
            class="filter-btn {{ !$filterStatus ? 'active' : '' }}">
            Semua
            @isset($allCount)
                <span style="margin-left:6px;opacity:.6;font-size:.7em">{{ $allCount }}</span>
            @endisset
        </a>

        <a href="{{ route('so.status.index', ['status' => 'QUEUE']) }}"
            class="filter-btn queue {{ $filterStatus === 'QUEUE' ? 'active' : '' }}">
            QUEUE
            @isset($counts['QUEUE'])
                <span style="margin-left:5px;opacity:.7;font-size:.7em">{{ $counts['QUEUE'] }}</span>
            @endisset
            &nbsp;<span style="font-weight:300;opacity:.6;font-size:.85em">Menunggu</span>
        </a>

        <a href="{{ route('so.status.index', ['status' => 'WAITING']) }}"
            class="filter-btn waiting {{ $filterStatus === 'WAITING' ? 'active' : '' }}">
            WAITING
            @isset($counts['WAITING'])
                <span style="margin-left:5px;opacity:.7;font-size:.7em">{{ $counts['WAITING'] }}</span>
            @endisset
            &nbsp;<span style="font-weight:300;opacity:.6;font-size:.85em">Sebagian</span>
        </a>

        <a href="{{ route('so.status.index', ['status' => 'PROCEED']) }}"
            class="filter-btn proceed {{ $filterStatus === 'PROCEED' ? 'active' : '' }}">
            PROCEED
            @isset($counts['PROCEED'])
                <span style="margin-left:5px;opacity:.7;font-size:.7em">{{ $counts['PROCEED'] }}</span>
            @endisset
            &nbsp;<span style="font-weight:300;opacity:.6;font-size:.85em">Terproses</span>
        </a>
    </div>

    <!-- ── Table ── -->
    @if(!empty($orders))
    <div class="table-wrap">
        <div class="table-header-bar">
            <h2>Sales Orders</h2>
            <div class="count-badge">{{ count($orders) }} records</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nomor SO</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Keterangan</th>
                    <th style="text-align:center">STATUS (API Value)</th>
                    <th style="text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $so)
                @php
                    $status = $so['status'] ?? 'UNKNOWN';
                    $info   = $statusMap[$status] ?? ['label' => 'Tidak diketahui', 'color' => 'UNKNOWN'];
                    $label  = $info['label'];
                @endphp
                <tr>
                    <td class="no-col">{{ $so['number'] ?? '-' }}</td>
                    <td>{{ $so['transDate'] ?? '-' }}</td>
                    <td>{{ $so['customerName'] ?? '-' }}</td>
                    <td style="max-width:220px; color: var(--muted); font-size:0.78rem;">
                        {{ $so['description'] ?? '' }}
                    </td>
                    <td style="text-align:center">
                        <div class="status-wrap" style="align-items:center">
                            <span class="status-api {{ $status }}">{{ $status }}</span>
                            <span class="status-label">{{ $label }}</span>
                        </div>
                    </td>
                    <td class="amount-col">
                        Rp {{ number_format($so['totalAmount'] ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if(!empty($pagination))
        <div class="pagination-info">
            <span>Halaman {{ $pagination['page'] ?? 1 }} dari {{ $pagination['pageCount'] ?? 1 }}</span>
            <span>Total {{ $pagination['rowCount'] ?? count($orders) }} records</span>
        </div>
        @endif
    </div>

    @elseif(!$error)
    <div class="table-wrap">
        <div class="empty">
            <div class="icon">📋</div>
            <p>Tidak ada Sales Order ditemukan</p>
        </div>
    </div>
    @endif

    <!-- ── Raw JSON Response ── -->
    @if(!empty($rawJson))
    <div class="raw-section">
        <button class="raw-toggle" onclick="toggleRaw()">
            <span>🔍 Lihat Raw JSON Response dari API</span>
            <span id="toggle-icon">▼</span>
        </button>
        <div class="raw-json" id="raw-panel">
            <pre>{{ $rawJson }}</pre>
        </div>
    </div>
    @endif

</div>

<script>
function toggleRaw() {
    const panel = document.getElementById('raw-panel');
    const icon  = document.getElementById('toggle-icon');
    panel.classList.toggle('show');
    icon.textContent = panel.classList.contains('show') ? '▲' : '▼';
}
</script>

</body>
</html>