<?php
require __DIR__ . '/app/bootstrap.php';

requireLogin();

$currentPage = 'dashboard';
$userType = currentUserType();

requireRoles(['gestor', 'admin']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Operacional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <style>
        .dashboard-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .dashboard-title {
            margin: 0;
        }
        .dashboard-subtitle {
            color: #94a3b8;
            margin: 4px 0 0;
        }
        .stats-grid .stat-card {
            background: linear-gradient(150deg, rgba(15, 23, 42, 0.75), rgba(30, 41, 59, 0.6));
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            padding: 18px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.25), transparent 65%);
        }
        .stat-label {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 8px;
        }
        .stat-meta {
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .section-title {
            font-weight: 600;
            margin-bottom: 12px;
        }
        .chart-stack {
            display: grid;
            gap: 12px;
        }
        .chart-row {
            display: grid;
            grid-template-columns: 140px 1fr 70px;
            gap: 12px;
            align-items: center;
        }
        .chart-label {
            font-size: 0.9rem;
            color: #e2e8f0;
        }
        .chart-bar {
            height: 10px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.2);
            overflow: hidden;
        }
        .chart-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #38bdf8, #0ea5e9);
        }
        .chart-value {
            text-align: right;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        .top-list {
            display: grid;
            gap: 10px;
        }
        .top-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.15);
        }
        .top-item-name {
            font-weight: 500;
        }
        .top-item-meta {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .sla-bar {
            display: flex;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(148, 163, 184, 0.2);
        }
        .sla-inside {
            background: linear-gradient(90deg, #22c55e, #16a34a);
        }
        .sla-outside {
            background: linear-gradient(90deg, #f97316, #ef4444);
        }
        .map-shell {
            height: 420px;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .loading-state {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        @media (max-width: 992px) {
            .chart-row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .chart-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/app/partials/sidebar.php'; ?>

    <main class="content">
        <div class="glass p-4 mb-4 dashboard-hero">
            <div>
                <p class="text-uppercase small text-info mb-1">Dashboard operacional</p>
                <h2 class="fw-bold dashboard-title">Visão geral de zeladoria</h2>
                <p class="dashboard-subtitle">Acompanhe volume, SLA e status em tempo real.</p>
            </div>
        </div>

        <div class="row g-3 stats-grid mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Chamados abertos</div>
                    <div class="stat-value" id="statOpen">--</div>
                    <div class="stat-meta" id="statOpenMeta">Total de solicitações</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Dentro do SLA</div>
                    <div class="stat-value" id="statSlaInside">--</div>
                    <div class="stat-meta" id="statSlaInsideMeta">0%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Fora do SLA</div>
                    <div class="stat-value" id="statSlaOutside">--</div>
                    <div class="stat-meta" id="statSlaOutsideMeta">0%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Tempo médio</div>
                    <div class="stat-value" id="statAvg">--</div>
                    <div class="stat-meta">Entre abertura e resolução</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="glass p-4 h-100">
                    <h5 class="section-title">Chamados por status</h5>
                    <div id="statusChart" class="chart-stack">
                        <span class="loading-state">Carregando indicadores...</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <h5 class="section-title">Top 5 subtipos</h5>
                    <div id="topSubtypes" class="top-list">
                        <span class="loading-state">Carregando subtipos...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h5 class="section-title mb-0">Indicadores por secretaria</h5>
                <span class="text-secondary small">Atualizado em tempo real</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Secretaria</th>
                        <th>Abertos</th>
                        <th>Total</th>
                        <th>SLA</th>
                        <th>Tempo médio</th>
                    </tr>
                    </thead>
                    <tbody id="secretariaTable">
                        <tr>
                            <td colspan="5" class="loading-state">Carregando secretarias...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h5 class="section-title mb-0">Mapa de chamados abertos</h5>
                <span class="text-secondary small">Pins por status</span>
            </div>
            <div id="dashboardMap" class="map-shell"></div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
    const statusColors = {
        RECEBIDO: '#38bdf8',
        EM_ANALISE: '#f59e0b',
        ENCAMINHADO: '#a855f7',
        EM_EXECUCAO: '#f97316',
        RESOLVIDO: '#22c55e',
        ENCERRADO: '#16a34a'
    };

    async function loadOverview() {
        const response = await fetch('api/v1/dashboard/overview.php');
        if (!response.ok) {
            throw new Error('Falha ao carregar overview.');
        }
        return response.json();
    }

    async function loadBySecretaria() {
        const response = await fetch('api/v1/dashboard/por-secretaria.php');
        if (!response.ok) {
            throw new Error('Falha ao carregar secretarias.');
        }
        return response.json();
    }

    async function loadMap() {
        const response = await fetch('api/v1/dashboard/mapa.php');
        if (!response.ok) {
            throw new Error('Falha ao carregar mapa.');
        }
        return response.json();
    }

    function renderOverview(data) {
        const totals = data?.totals || {};
        const sla = data?.sla || {};

        document.getElementById('statOpen').textContent = totals.open ?? 0;
        document.getElementById('statOpenMeta').textContent = `Total ${totals.total ?? 0}`;
        document.getElementById('statSlaInside').textContent = sla.dentro ?? 0;
        document.getElementById('statSlaInsideMeta').textContent = `${sla.dentro_percent ?? 0}% dentro`;
        document.getElementById('statSlaOutside').textContent = sla.vencido ?? 0;
        document.getElementById('statSlaOutsideMeta').textContent = `${sla.vencido_percent ?? 0}% vencido`;
        document.getElementById('statAvg').textContent = data?.avg_resolution_label || '--';

        const chart = document.getElementById('statusChart');
        const items = data?.status_breakdown || [];
        if (!items.length) {
            chart.innerHTML = '<span class="loading-state">Nenhum chamado disponível.</span>';
            return;
        }
        chart.innerHTML = items.map(item => `
            <div class="chart-row">
                <div class="chart-label">${item.label}</div>
                <div class="chart-bar">
                    <div class="chart-fill" style="width:${item.percent}%"></div>
                </div>
                <div class="chart-value">${item.total}</div>
            </div>
        `).join('');

        const topList = document.getElementById('topSubtypes');
        const topItems = data?.top_subtypes || [];
        if (!topItems.length) {
            topList.innerHTML = '<span class="loading-state">Sem subtipos cadastrados.</span>';
            return;
        }
        topList.innerHTML = topItems.map(item => `
            <div class="top-item">
                <div>
                    <div class="top-item-name">${item.name || 'Subtipo não informado'}</div>
                    <div class="top-item-meta">${item.percent}% do total</div>
                </div>
                <div class="top-item-meta">${item.total} chamados</div>
            </div>
        `).join('');
    }

    function renderSecretarias(data) {
        const tbody = document.getElementById('secretariaTable');
        const items = data?.items || [];
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="loading-state">Nenhum chamado nas secretarias.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(item => {
            const sla = item.sla || {};
            return `
                <tr>
                    <td>${item.secretaria?.nome || 'Não definida'}</td>
                    <td>${item.abertos ?? 0}</td>
                    <td>${item.total ?? 0}</td>
                    <td>
                        <div class="sla-bar mb-1">
                            <span class="sla-inside" style="width:${sla.dentro_percent ?? 0}%"></span>
                            <span class="sla-outside" style="width:${sla.vencido_percent ?? 0}%"></span>
                        </div>
                        <div class="text-secondary small">${sla.dentro ?? 0} dentro • ${sla.vencido ?? 0} vencido</div>
                    </td>
                    <td>${item.avg_resolution_label || '--'}</td>
                </tr>
            `;
        }).join('');
    }

    function renderMap(items) {
        const mapEl = document.getElementById('dashboardMap');
        if (!mapEl || typeof L === 'undefined') {
            mapEl.innerHTML = '<div class="loading-state p-3">Mapa indisponível.</div>';
            return;
        }
        const map = L.map(mapEl, { scrollWheelZoom: false }).setView([-3.7319, -38.5267], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const bounds = [];
        items.forEach(item => {
            if (!item.latitude || !item.longitude) {
                return;
            }
            const color = statusColors[item.status] || '#38bdf8';
            const marker = L.circleMarker([item.latitude, item.longitude], {
                radius: 8,
                color,
                weight: 2,
                fillColor: color,
                fillOpacity: 0.85
            }).addTo(map);
            marker.bindPopup(`
                <strong>${item.service_name || 'Chamado'}</strong><br>
                ${item.problem_type || ''}<br>
                <small>${item.status_label || item.status}</small><br>
                <small>${item.secretaria?.nome || 'Sem secretaria'}</small>
            `);
            bounds.push([item.latitude, item.longitude]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }

    Promise.all([loadOverview(), loadBySecretaria(), loadMap()])
        .then(([overview, bySecretaria, mapData]) => {
            renderOverview(overview);
            renderSecretarias(bySecretaria);
            renderMap(mapData?.items || []);
        })
        .catch((error) => {
            console.error(error);
            document.getElementById('statusChart').innerHTML = '<span class="loading-state">Erro ao carregar dados.</span>';
            document.getElementById('topSubtypes').innerHTML = '<span class="loading-state">Erro ao carregar dados.</span>';
            document.getElementById('secretariaTable').innerHTML = '<tr><td colspan="5" class="loading-state">Erro ao carregar dados.</td></tr>';
        });
</script>
</body>
</html>
