<?php
/**
 * アクセスログ分析ダッシュボード
 * 統計情報の表示
 */

require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$pdo = getDB();

// 期間指定（デフォルトは過去30日）
$days = intval($_GET['days'] ?? 30);
$startDate = date('Y-m-d', strtotime("-{$days} days"));

// 今日の統計
$todayStats = $pdo->query("
    SELECT 
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM access_logs
    WHERE DATE(accessed_at) = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

// 期間内の統計
$periodStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_visitors,
        COUNT(DISTINCT DATE(accessed_at)) as active_days
    FROM access_logs
    WHERE DATE(accessed_at) >= ?
");
$periodStats->execute([$startDate]);
$periodData = $periodStats->fetch(PDO::FETCH_ASSOC);

// 日別アクセス数
$dailyStats = $pdo->prepare("
    SELECT 
        DATE(accessed_at) as date,
        COUNT(*) as views,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM access_logs
    WHERE DATE(accessed_at) >= ?
    GROUP BY DATE(accessed_at)
    ORDER BY date DESC
");
$dailyStats->execute([$startDate]);
$dailyData = $dailyStats->fetchAll(PDO::FETCH_ASSOC);

// ページ別アクセス数
$pageStats = $pdo->prepare("
    SELECT 
        page_url,
        COUNT(*) as views,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM access_logs
    WHERE DATE(accessed_at) >= ?
    GROUP BY page_url
    ORDER BY views DESC
    LIMIT 20
");
$pageStats->execute([$startDate]);
$pageData = $pageStats->fetchAll(PDO::FETCH_ASSOC);

// 時間帯別アクセス数（今日）
$hourlyStats = $pdo->query("
    SELECT 
        HOUR(accessed_at) as hour,
        COUNT(*) as views
    FROM access_logs
    WHERE DATE(accessed_at) = CURDATE()
    GROUP BY HOUR(accessed_at)
    ORDER BY hour
")->fetchAll(PDO::FETCH_ASSOC);

// 投稿制限状況
$postLimits = $pdo->query("
    SELECT 
        COUNT(*) as total_ips,
        SUM(post_count) as total_posts
    FROM post_limits
    WHERE post_date = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アクセス分析ダッシュボード - maruttoart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #333;
        }
        .period-select {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .period-select select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        .logout-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: normal;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-card .sub {
            font-size: 14px;
            color: #999;
            margin-top: 8px;
        }
        .chart-container {
            background: white;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-container h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        .table-container {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary { background: #e3f2fd; color: #1976d2; }
        .badge-success { background: #e8f5e9; color: #388e3c; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 アクセス分析ダッシュボード</h1>
            <div class="period-select">
                <label>期間:</label>
                <select onchange="location.href='?days='+this.value">
                    <option value="7" <?= $days === 7 ? 'selected' : '' ?>>過去7日間</option>
                    <option value="30" <?= $days === 30 ? 'selected' : '' ?>>過去30日間</option>
                    <option value="90" <?= $days === 90 ? 'selected' : '' ?>>過去90日間</option>
                </select>
                <a href="/admin/" class="logout-btn" style="background: #28a745; margin-right: 10px;">管理画面に戻る</a>
                <a href="/admin/logout.php" class="logout-btn" onclick="return confirm('ログアウトしますか？')">ログアウト</a>
            </div>
        </header>

        <!-- 今日の統計 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>今日のページビュー</h3>
                <div class="number"><?= number_format($todayStats['total_views']) ?></div>
                <div class="sub">PV</div>
            </div>
            <div class="stat-card">
                <h3>今日のユニーク訪問者</h3>
                <div class="number"><?= number_format($todayStats['unique_visitors']) ?></div>
                <div class="sub">UU</div>
            </div>
            <div class="stat-card">
                <h3>期間内の総PV</h3>
                <div class="number"><?= number_format($periodData['total_views']) ?></div>
                <div class="sub"><?= $days ?>日間</div>
            </div>
            <div class="stat-card">
                <h3>期間内のユニーク訪問者</h3>
                <div class="number"><?= number_format($periodData['unique_visitors']) ?></div>
                <div class="sub"><?= $days ?>日間</div>
            </div>
            <div class="stat-card">
                <h3>今日の投稿数</h3>
                <div class="number"><?= number_format($postLimits['total_posts'] ?? 0) ?></div>
                <div class="sub">件</div>
            </div>
            <div class="stat-card">
                <h3>今日の投稿者数</h3>
                <div class="number"><?= number_format($postLimits['total_ips'] ?? 0) ?></div>
                <div class="sub">IP</div>
            </div>
        </div>

        <!-- 日別アクセス推移 -->
        <div class="chart-container">
            <h2>📈 日別アクセス推移</h2>
            <canvas id="dailyChart" height="80"></canvas>
        </div>

        <!-- 時間帯別アクセス（今日） -->
        <div class="chart-container">
            <h2>🕐 時間帯別アクセス（今日）</h2>
            <canvas id="hourlyChart" height="80"></canvas>
        </div>

        <!-- ページ別アクセスランキング -->
        <div class="table-container">
            <h2>🏆 ページ別アクセスランキング（過去<?= $days ?>日間）</h2>
            <table>
                <thead>
                    <tr>
                        <th>順位</th>
                        <th>ページURL</th>
                        <th>ページビュー</th>
                        <th>ユニーク訪問者</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageData as $index => $page): ?>
                        <tr>
                            <td><span class="badge badge-primary">#<?= $index + 1 ?></span></td>
                            <td><?= htmlspecialchars($page['page_url']) ?></td>
                            <td><?= number_format($page['views']) ?> PV</td>
                            <td><?= number_format($page['unique_visitors']) ?> UU</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 日別詳細データ -->
        <div class="table-container">
            <h2>📅 日別詳細データ（過去<?= $days ?>日間）</h2>
            <table>
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>ページビュー</th>
                        <th>ユニーク訪問者</th>
                        <th>PV/UU比率</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyData as $day): ?>
                        <tr>
                            <td><?= htmlspecialchars($day['date']) ?></td>
                            <td><?= number_format($day['views']) ?> PV</td>
                            <td><?= number_format($day['unique_visitors']) ?> UU</td>
                            <td><?= number_format($day['views'] / max($day['unique_visitors'], 1), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // 日別アクセス推移グラフ
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($dailyData, 'date'))) ?>,
                datasets: [
                    {
                        label: 'ページビュー',
                        data: <?= json_encode(array_reverse(array_column($dailyData, 'views'))) ?>,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'ユニーク訪問者',
                        data: <?= json_encode(array_reverse(array_column($dailyData, 'unique_visitors'))) ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 時間帯別アクセスグラフ
        const hourlyData = <?= json_encode($hourlyStats) ?>;
        const hourlyLabels = Array.from({length: 24}, (_, i) => i + '時');
        const hourlyValues = Array.from({length: 24}, (_, i) => {
            const found = hourlyData.find(d => parseInt(d.hour) === i);
            return found ? parseInt(found.views) : 0;
        });

        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'アクセス数',
                    data: hourlyValues,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
