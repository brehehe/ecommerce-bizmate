<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tech Stack — E-Commerce Bizmate</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.6;
            padding: 40px;
        }

        /* ── Cover ───────────────────────────────────── */
        .cover {
            text-align: center;
            padding: 40px 0 50px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 36px;
        }
        .cover .badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 99px;
            margin-bottom: 16px;
        }
        .cover h1 {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .cover p {
            font-size: 11px;
            color: #64748b;
        }
        .cover .meta {
            margin-top: 18px;
            font-size: 10px;
            color: #94a3b8;
        }

        /* ── Section ─────────────────────────────────── */
        .section { margin-bottom: 28px; }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #94a3b8;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #f1f5f9;
        }

        /* ── Grid ────────────────────────────────────── */
        .grid-2 {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
        }
        .grid-2-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        /* ── Stack table ─────────────────────────────── */
        .stack-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .stack-table tr {
            border-bottom: 1px solid #f8fafc;
        }
        .stack-table tr:last-child { border-bottom: none; }
        .stack-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }
        .stack-table td:first-child {
            font-weight: 600;
            color: #1e293b;
            font-size: 11px;
            width: 55%;
        }
        .stack-table td:last-child {
            font-size: 10px;
            color: #64748b;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .stack-table .version-pill {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 9px;
            font-weight: 700;
        }

        /* ── Card ────────────────────────────────────── */
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 4px;
        }
        .card-title {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        /* ── List ────────────────────────────────────── */
        .item-list { list-style: none; }
        .item-list li {
            font-size: 11px;
            color: #475569;
            padding: 3px 0;
            padding-left: 14px;
            position: relative;
        }
        .item-list li::before {
            content: '';
            position: absolute;
            left: 4px;
            top: 10px;
            width: 4px;
            height: 4px;
            background: #cbd5e1;
            border-radius: 50%;
        }
        .item-list li strong { color: #0f172a; font-weight: 600; }

        /* ── Tags ────────────────────────────────────── */
        .tags { line-height: 2; }
        .tag {
            display: inline-block;
            background: #0f172a;
            color: #f8fafc;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: 600;
            margin: 2px 2px 2px 0;
        }
        .tag.green  { background: #064e3b; }
        .tag.blue   { background: #1e3a8a; }
        .tag.purple { background: #3b0764; }
        .tag.orange { background: #7c2d12; }
        .tag.gray   { background: #334155; }

        /* ── Footer ──────────────────────────────────── */
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }

        /* ── Highlight box ───────────────────────────── */
        .highlight {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .highlight h2 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }
        .highlight .hl-grid {
            display: table;
            width: 100%;
        }
        .highlight .hl-item {
            display: table-cell;
            text-align: center;
            padding: 0 8px;
            border-right: 1px solid #334155;
        }
        .highlight .hl-item:last-child { border-right: none; }
        .highlight .hl-val {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            display: block;
        }
        .highlight .hl-lbl {
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- Cover -->
    <div class="cover">
        <div class="badge">Technical Documentation</div>
        <h1>E-Commerce Bizmate</h1>
        <p>Tech Stack &amp; Architecture Overview</p>
        <div class="meta">Generated: {{ now()->format('d F Y') }} &nbsp;&middot;&nbsp; Confidential</div>
    </div>

    <!-- Highlight summary -->
    <div class="highlight">
        <h2>Stack Summary</h2>
        <div class="hl-grid">
            <div class="hl-item">
                <span class="hl-val">PHP 8.3</span>
                <span class="hl-lbl">Language</span>
            </div>
            <div class="hl-item">
                <span class="hl-val">Laravel 13</span>
                <span class="hl-lbl">Framework</span>
            </div>
            <div class="hl-item">
                <span class="hl-val">Svelte 5</span>
                <span class="hl-lbl">Frontend</span>
            </div>
            <div class="hl-item">
                <span class="hl-val">PostgreSQL</span>
                <span class="hl-lbl">Database</span>
            </div>
            <div class="hl-item">
                <span class="hl-val">Inertia v3</span>
                <span class="hl-lbl">SPA Bridge</span>
            </div>
        </div>
    </div>

    <!-- Grid: Backend + Frontend -->
    <div class="grid-2">
        <div class="grid-2-col">

            <!-- Backend -->
            <div class="section">
                <div class="section-title">Backend</div>
                <div class="card">
                    <div class="card-title">PHP / Laravel</div>
                    <table class="stack-table">
                        <tr><td>PHP</td><td><span class="version-pill">8.3</span></td></tr>
                        <tr><td>Laravel Framework</td><td><span class="version-pill">v13.18</span></td></tr>
                        <tr><td>Inertia Laravel</td><td><span class="version-pill">v3</span></td></tr>
                        <tr><td>Laravel Sanctum</td><td><span class="version-pill">v4</span></td></tr>
                        <tr><td>Laravel Reverb</td><td><span class="version-pill">v1</span></td></tr>
                        <tr><td>Spatie Permission</td><td><span class="version-pill">v7</span></td></tr>
                        <tr><td>Laravel Wayfinder</td><td><span class="version-pill">v0</span></td></tr>
                        <tr><td>barryvdh/dompdf</td><td><span class="version-pill">v3</span></td></tr>
                    </table>
                </div>
            </div>

            <!-- Database -->
            <div class="section">
                <div class="section-title">Database</div>
                <div class="card">
                    <ul class="item-list">
                        <li><strong>PostgreSQL</strong> — Primary database</li>
                        <li><strong>UUID</strong> — Primary key semua tabel</li>
                        <li><strong>20+ Migrations</strong> — Termasuk membership</li>
                        <li><strong>Eloquent ORM</strong> — Query builder</li>
                        <li><strong>Soft Deletes</strong> — Pada model utama</li>
                    </ul>
                </div>
            </div>

            <!-- Realtime -->
            <div class="section">
                <div class="section-title">Realtime</div>
                <div class="card">
                    <ul class="item-list">
                        <li><strong>Laravel Reverb</strong> — WebSocket server</li>
                        <li><strong>Laravel Echo</strong> — Frontend client</li>
                        <li><strong>Pusher.js</strong> — Protocol adapter</li>
                        <li>Chat, notifikasi, stok real-time</li>
                    </ul>
                </div>
            </div>

        </div>
        <div class="grid-2-col">

            <!-- Frontend -->
            <div class="section">
                <div class="section-title">Frontend</div>
                <div class="card">
                    <div class="card-title">JavaScript / Svelte</div>
                    <table class="stack-table">
                        <tr><td>Svelte</td><td><span class="version-pill">v5 Runes</span></td></tr>
                        <tr><td>Inertia Svelte</td><td><span class="version-pill">v3</span></td></tr>
                        <tr><td>TypeScript</td><td><span class="version-pill">v5.7</span></td></tr>
                        <tr><td>Vite</td><td><span class="version-pill">v8</span></td></tr>
                        <tr><td>Tailwind CSS</td><td><span class="version-pill">v4</span></td></tr>
                        <tr><td>Chart.js</td><td><span class="version-pill">v4.5</span></td></tr>
                        <tr><td>Tabler Icons</td><td><span class="version-pill">v3.44</span></td></tr>
                        <tr><td>Quill Editor</td><td><span class="version-pill">v2</span></td></tr>
                    </table>
                </div>
            </div>

            <!-- Libraries -->
            <div class="section">
                <div class="section-title">Libraries &amp; Tools</div>
                <div class="card">
                    <ul class="item-list">
                        <li><strong>Leaflet</strong> — Interactive maps</li>
                        <li><strong>html5-qrcode</strong> — QR/barcode scanner</li>
                        <li><strong>SortableJS</strong> — Drag &amp; drop</li>
                        <li><strong>Plyr</strong> — Video player</li>
                        <li><strong>Coloris</strong> — Color picker</li>
                        <li><strong>Laravel Pint</strong> — PHP code formatter</li>
                        <li><strong>Pest v4</strong> — Testing framework</li>
                        <li><strong>Log Viewer</strong> — opcodesio/log-viewer</li>
                    </ul>
                </div>
            </div>

            <!-- Infrastructure -->
            <div class="section">
                <div class="section-title">Infrastructure</div>
                <div class="card">
                    <ul class="item-list">
                        <li><strong>Laravel Sail</strong> — Docker dev environment</li>
                        <li><strong>Laravel Pail</strong> — Log viewer CLI</li>
                        <li><strong>Laravel Boost</strong> — Dev tooling</li>
                        <li><strong>Laravel Cloud</strong> — Production deploy</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <!-- Architecture -->
    <div class="section">
        <div class="section-title">Architecture Pattern</div>
        <div class="card">
            <div class="tags">
                <span class="tag">MVC</span>
                <span class="tag">SPA (Inertia)</span>
                <span class="tag blue">Service Layer</span>
                <span class="tag blue">Observer Pattern</span>
                <span class="tag green">Repository-lite</span>
                <span class="tag green">Eloquent ORM</span>
                <span class="tag purple">Role &amp; Permission (Spatie)</span>
                <span class="tag orange">Event Broadcasting</span>
                <span class="tag gray">Scheduled Commands</span>
                <span class="tag gray">Queue Jobs</span>
            </div>
        </div>
    </div>

    <!-- Key Features -->
    <div class="section">
        <div class="section-title">Key Features Implemented</div>
        <div class="grid-2">
            <div class="grid-2-col">
                <div class="card">
                    <div class="card-title">Admin Panel</div>
                    <ul class="item-list">
                        <li>Dashboard analytics + Chart.js</li>
                        <li>Product management (CRUD + variants)</li>
                        <li>Transaction processing + resi</li>
                        <li>Promotion engine (flash sale, voucher)</li>
                        <li>Report (sales, stock, P&amp;L, pareto)</li>
                        <li>Master data (courier, payment, role)</li>
                        <li>CMS banners &amp; slider</li>
                        <li>Real-time chat admin</li>
                        <li>Membership program management</li>
                    </ul>
                </div>
            </div>
            <div class="grid-2-col">
                <div class="card">
                    <div class="card-title">Storefront</div>
                    <ul class="item-list">
                        <li>Home, category, product detail</li>
                        <li>Cart, checkout, payment</li>
                        <li>Flash sale dengan countdown</li>
                        <li>Voucher &amp; promo engine</li>
                        <li>Loyalty coins system</li>
                        <li>Membership dengan benefit</li>
                        <li>Chat pelanggan real-time</li>
                        <li>Tracking pengiriman</li>
                        <li>Return &amp; refund workflow</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        E-Commerce Bizmate &nbsp;&middot;&nbsp; Tech Stack Documentation &nbsp;&middot;&nbsp; {{ now()->format('Y') }}
        &nbsp;&middot;&nbsp; Confidential — Internal Use Only
    </div>

</body>
</html>
