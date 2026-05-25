<?php
require 'config/auth.php';
require_login();
require 'config/db.php';

function countRows(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? (int) $row['count'] : 0;
}

function formatPeso(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function percentage(int $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int) round(($value / $total) * 100);
}

$currentUser = $_SESSION['username'] ?? 'User';
$currentRole = $_SESSION['role'] ?? 'staff';

$totalApartments = countRows($conn, "SELECT COUNT(*) AS count FROM apartments WHERE is_archived = 0");
$vacantApartments = countRows($conn, "SELECT COUNT(*) AS count FROM apartments WHERE status = 'vacant' AND is_archived = 0");
$occupiedApartments = countRows($conn, "SELECT COUNT(*) AS count FROM apartments WHERE status = 'occupied' AND is_archived = 0");
$archivedApartments = countRows($conn, "SELECT COUNT(*) AS count FROM apartments WHERE is_archived = 1");
$totalTenants = countRows($conn, "SELECT COUNT(*) AS count FROM tenants");
$pendingMaintenance = countRows($conn, "SELECT COUNT(*) AS count FROM maintenance WHERE status = 'pending'");
$inProgressMaintenance = countRows($conn, "SELECT COUNT(*) AS count FROM maintenance WHERE status = 'in_progress'");
$resolvedMaintenance = countRows($conn, "SELECT COUNT(*) AS count FROM maintenance WHERE status = 'resolved'");
$totalMaintenance = $pendingMaintenance + $inProgressMaintenance + $resolvedMaintenance;

$collectionResult = $conn->query("
    SELECT
        SUM(amount) AS total_collected,
        COUNT(*) AS payment_count,
        MAX(payment_date) AS latest_payment
    FROM payments
    WHERE MONTH(payment_date) = MONTH(CURDATE())
      AND YEAR(payment_date) = YEAR(CURDATE())
");
$collectionSummary = $collectionResult ? $collectionResult->fetch_assoc() : null;
$monthlyCollected = (float) ($collectionSummary['total_collected'] ?? 0);
$monthlyPaymentCount = (int) ($collectionSummary['payment_count'] ?? 0);
$latestPaymentDate = $collectionSummary['latest_payment'] ?? null;

$tenantsDueResult = $conn->query("
    SELECT
        t.name,
        a.unit_number,
        DATEDIFF(CURDATE(), IFNULL(p.last_payment, t.move_in_date)) AS days_late
    FROM tenants t
    JOIN apartments a ON t.apartment_id = a.id
    LEFT JOIN (
        SELECT tenant_id, MAX(payment_date) AS last_payment
        FROM payments
        GROUP BY tenant_id
    ) p ON t.id = p.tenant_id
    WHERE DATEDIFF(CURDATE(), IFNULL(p.last_payment, t.move_in_date)) >= 30
    ORDER BY days_late DESC, t.name ASC
    LIMIT 6
");
$tenantsDue = $tenantsDueResult ? $tenantsDueResult->fetch_all(MYSQLI_ASSOC) : [];
$overdueCount = count($tenantsDue);

$recentPaymentsResult = $conn->query("
    SELECT
        t.name,
        a.unit_number,
        p.amount,
        p.payment_date
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    JOIN apartments a ON t.apartment_id = a.id
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT 5
");
$recentPayments = $recentPaymentsResult ? $recentPaymentsResult->fetch_all(MYSQLI_ASSOC) : [];

$monthlyRevenueResult = $conn->query("
    SELECT MONTH(payment_date) AS month, SUM(amount) AS total
    FROM payments
    WHERE YEAR(payment_date) = YEAR(CURDATE())
    GROUP BY MONTH(payment_date)
");
$revenueData = array_fill(1, 12, 0);
if ($monthlyRevenueResult) {
    while ($row = $monthlyRevenueResult->fetch_assoc()) {
        $revenueData[(int) $row['month']] = (float) $row['total'];
    }
}

$vacancyRate = percentage($vacantApartments, max($totalApartments, 1));
$occupancyRate = percentage($occupiedApartments, max($totalApartments, 1));
$resolvedRate = percentage($resolvedMaintenance, max($totalMaintenance, 1));
$maintenanceOpen = $pendingMaintenance + $inProgressMaintenance;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Apartment Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg: #f4f7fb;
      --surface: #ffffff;
      --surface-alt: #eef4ff;
      --surface-dark: #0f172a;
      --text: #10233f;
      --muted: #63748b;
      --border: rgba(15, 23, 42, 0.08);
      --primary: #0f4c81;
      --primary-soft: #dceeff;
      --accent: #ffb703;
      --success: #1f9d73;
      --danger: #df5a49;
      --warning: #f3a712;
      --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background:
        radial-gradient(circle at top left, rgba(15, 76, 129, 0.08), transparent 28%),
        linear-gradient(180deg, #f7f9fc 0%, #eef4fb 100%);
      color: var(--text);
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .app-shell {
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      width: 270px;
      background:
        linear-gradient(180deg, #0a2540 0%, #12395f 55%, #194f77 100%);
      color: #fff;
      padding: 28px 18px;
      position: sticky;
      top: 0;
      min-height: 100vh;
      box-shadow: 14px 0 40px rgba(10, 37, 64, 0.18);
    }

    .brand {
      padding: 10px 12px 24px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 24px;
    }

    .brand .eyebrow {
      color: rgba(255, 255, 255, 0.68);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .brand h4 {
      margin: 0;
      font-size: 23px;
      line-height: 1.2;
    }

    .nav-group {
      margin-bottom: 24px;
    }

    .nav-label {
      padding: 0 12px;
      margin-bottom: 10px;
      color: rgba(255, 255, 255, 0.58);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .sidebar a {
      color: rgba(255, 255, 255, 0.82);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      margin-bottom: 8px;
      border-radius: 16px;
      transition: background 0.2s ease, transform 0.2s ease, color 0.2s ease;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background: rgba(255, 255, 255, 0.12);
      color: #fff;
      transform: translateX(3px);
    }

    .sidebar .cta {
      margin-top: 20px;
      padding: 16px;
      background: rgba(255, 255, 255, 0.09);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 18px;
    }

    .sidebar .cta strong {
      display: block;
      margin-bottom: 8px;
    }

    .sidebar .cta span {
      font-size: 13px;
      color: rgba(255, 255, 255, 0.72);
      display: block;
      margin-bottom: 12px;
    }

    .sidebar .cta a {
      margin: 0;
      padding: 10px 12px;
      background: rgba(255, 183, 3, 0.18);
      border: 1px solid rgba(255, 183, 3, 0.25);
      justify-content: center;
    }

    .main {
      flex: 1;
      padding: 28px;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      margin-bottom: 24px;
      padding: 28px;
      border-radius: 28px;
      background: linear-gradient(135deg, rgba(15, 76, 129, 0.98), rgba(26, 117, 159, 0.92));
      color: #fff;
      box-shadow: var(--shadow);
    }

    .topbar h1 {
      margin: 0 0 10px;
      font-size: clamp(28px, 4vw, 42px);
      line-height: 1.05;
    }

    .topbar p {
      margin: 0;
      max-width: 700px;
      color: rgba(255, 255, 255, 0.78);
      line-height: 1.7;
    }

    .topbar-meta {
      min-width: 220px;
      display: grid;
      gap: 14px;
    }

    .meta-card {
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 16px 18px;
    }

    .meta-card span {
      display: block;
      font-size: 13px;
      color: rgba(255, 255, 255, 0.72);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .meta-card strong {
      font-size: 20px;
    }

    .section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      margin-bottom: 14px;
    }

    .section-title h2 {
      font-size: 18px;
      margin: 0;
    }

    .section-title span {
      color: var(--muted);
      font-size: 14px;
    }

    .stat-card,
    .panel-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: var(--shadow);
    }

    .stat-card {
      padding: 22px;
      height: 100%;
      position: relative;
      overflow: hidden;
    }

    .stat-card::after {
      content: "";
      position: absolute;
      width: 100px;
      height: 100px;
      right: -20px;
      top: -20px;
      border-radius: 50%;
      background: rgba(15, 76, 129, 0.08);
    }

    .stat-card .icon-wrap {
      width: 52px;
      height: 52px;
      display: grid;
      place-items: center;
      border-radius: 16px;
      margin-bottom: 16px;
      background: var(--primary-soft);
      color: var(--primary);
      font-size: 20px;
    }

    .stat-card h3 {
      margin: 0;
      font-size: 30px;
    }

    .stat-card p {
      margin: 8px 0 0;
      color: var(--muted);
    }

    .stat-foot {
      margin-top: 14px;
      font-size: 13px;
      color: var(--primary);
      font-weight: 700;
    }

    .panel-card {
      padding: 24px;
      height: 100%;
    }

    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 14px;
    }

    .quick-action {
      display: block;
      text-decoration: none;
      color: var(--text);
      padding: 18px;
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff, #f5f9ff);
      border: 1px solid rgba(15, 76, 129, 0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .quick-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(15, 76, 129, 0.12);
    }

    .quick-action i {
      font-size: 18px;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .quick-action strong {
      display: block;
      margin-bottom: 6px;
    }

    .quick-action span {
      color: var(--muted);
      font-size: 14px;
    }

    .list-clean {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 12px;
    }

    .list-row {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
      padding: 16px 18px;
      border-radius: 18px;
      background: var(--surface-alt);
      border: 1px solid rgba(15, 76, 129, 0.08);
    }

    .list-row strong {
      display: block;
      margin-bottom: 4px;
    }

    .list-row span {
      color: var(--muted);
      font-size: 14px;
    }

    .badge-soft {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 88px;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
    }

    .badge-danger {
      color: var(--danger);
      background: rgba(223, 90, 73, 0.12);
    }

    .badge-success {
      color: var(--success);
      background: rgba(31, 157, 115, 0.14);
    }

    .progress-stack {
      display: grid;
      gap: 16px;
    }

    .progress-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--muted);
    }

    .progress {
      height: 12px;
      border-radius: 999px;
      background: #edf2f7;
    }

    .progress-bar {
      border-radius: 999px;
    }

    .empty-state {
      padding: 22px;
      text-align: center;
      color: var(--muted);
      background: var(--surface-alt);
      border-radius: 18px;
      border: 1px dashed rgba(15, 76, 129, 0.16);
    }

    .chart-wrap {
      position: relative;
      min-height: 290px;
    }

    .footer-note {
      margin-top: 26px;
      text-align: center;
      color: var(--muted);
      font-size: 14px;
    }

    @media (max-width: 1100px) {
      .app-shell {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        min-height: auto;
        position: static;
      }

      .main {
        padding: 18px;
      }
    }

    @media (max-width: 768px) {
      .topbar {
        padding: 22px;
      }

      .topbar,
      .section-title,
      .list-row {
        flex-direction: column;
        align-items: flex-start;
      }

      .main {
        padding: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="eyebrow">Control Center</div>
        <h4><i class="fa-solid fa-house-chimney"></i> Apartment Management</h4>
      </div>

      <div class="nav-group">
        <div class="nav-label">Workspace</div>
        <a href="index.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
        <a href="buildings/index.php"><i class="fa-solid fa-building"></i> Buildings</a>
        <a href="apartments/index.php"><i class="fa-solid fa-city"></i> Apartments</a>
        <a href="tenants/index.php"><i class="fa-solid fa-users"></i> Tenants</a>
        <a href="payments/index.php"><i class="fa-solid fa-hand-holding-dollar"></i> Payments</a>
        <a href="maintenance/index.php"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance</a>
        <a href="payment_info.php"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Info</a>
      </div>

      <div class="nav-group">
        <div class="nav-label">Access</div>
        <a href="auth/register.php"><i class="fa-solid fa-user-plus"></i> Add User</a>
        <a href="auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>

      <div class="cta">
        <strong>Need another account?</strong>
        <span>Create a new admin or staff user from inside the app so the password is hashed correctly.</span>
        <a href="auth/register.php"><i class="fa-solid fa-user-shield"></i> Open Registration</a>
      </div>
    </aside>

    <main class="main">
      <section class="topbar">
        <div>
          <h1>Welcome back, <?= htmlspecialchars(ucfirst($currentUser)) ?>.</h1>
          <p>
            Here’s your current rental snapshot: <?= $occupiedApartments ?> occupied units, <?= $vacantApartments ?> vacant units, <?= $maintenanceOpen ?> active maintenance cases, and <?= formatPeso($monthlyCollected) ?> collected this month.
          </p>
        </div>
        <div class="topbar-meta">
          <div class="meta-card">
            <span>Signed in as</span>
            <strong><?= htmlspecialchars(strtoupper($currentRole)) ?></strong>
          </div>
          <div class="meta-card">
            <span>Latest payment</span>
            <strong><?= $latestPaymentDate ? htmlspecialchars(date('M d, Y', strtotime($latestPaymentDate))) : 'No payments yet' ?></strong>
          </div>
        </div>
      </section>

      <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon-wrap"><i class="fa-solid fa-building-circle-check"></i></div>
            <h3><?= $totalApartments ?></h3>
            <p>Total active apartments in circulation.</p>
            <div class="stat-foot"><?= $archivedApartments ?> archived unit<?= $archivedApartments === 1 ? '' : 's' ?></div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon-wrap"><i class="fa-solid fa-door-open"></i></div>
            <h3><?= $vacantApartments ?></h3>
            <p>Vacant apartments ready for occupancy.</p>
            <div class="stat-foot"><?= $vacancyRate ?>% vacancy rate</div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon-wrap"><i class="fa-solid fa-users"></i></div>
            <h3><?= $totalTenants ?></h3>
            <p>Current tenants actively tracked in the system.</p>
            <div class="stat-foot"><?= $occupancyRate ?>% occupancy rate</div>
          </div>
        </div>

        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon-wrap"><i class="fa-solid fa-wallet"></i></div>
            <h3><?= formatPeso($monthlyCollected) ?></h3>
            <p>Payments posted for <?= date('F Y') ?>.</p>
            <div class="stat-foot"><?= $monthlyPaymentCount ?> payment<?= $monthlyPaymentCount === 1 ? '' : 's' ?> recorded this month</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-5">
          <section class="panel-card">
            <div class="section-title">
              <h2>Quick actions</h2>
              <span>Shortcuts for the most common work</span>
            </div>
            <div class="quick-actions">
              <a class="quick-action" href="buildings/index.php">
                <i class="fa-solid fa-building"></i>
                <strong>Manage buildings</strong>
                <span>Update building records and addresses.</span>
              </a>
              <a class="quick-action" href="apartments/index.php">
                <i class="fa-solid fa-city"></i>
                <strong>Review apartments</strong>
                <span>Track availability and occupancy.</span>
              </a>
              <a class="quick-action" href="tenants/index.php">
                <i class="fa-solid fa-user-plus"></i>
                <strong>Add tenants</strong>
                <span>Register new tenants and assign units.</span>
              </a>
              <a class="quick-action" href="payments/index.php">
                <i class="fa-solid fa-money-bill-wave"></i>
                <strong>Record payments</strong>
                <span>Log rent collection and receipts.</span>
              </a>
            </div>
          </section>
        </div>

        <div class="col-lg-7">
          <section class="panel-card">
            <div class="section-title">
              <h2>Monthly revenue</h2>
              <span><?= date('Y') ?> payment trend</span>
            </div>
            <div class="chart-wrap">
              <canvas id="revenueChart"></canvas>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-4">
          <section class="panel-card h-100">
            <div class="section-title">
              <h2>Collections watch</h2>
              <span><?= $overdueCount ?> tenant<?= $overdueCount === 1 ? '' : 's' ?> overdue</span>
            </div>

            <?php if ($overdueCount > 0): ?>
              <ul class="list-clean">
                <?php foreach ($tenantsDue as $due): ?>
                  <li class="list-row">
                    <div>
                      <strong><?= htmlspecialchars($due['name']) ?></strong>
                      <span>Unit <?= htmlspecialchars($due['unit_number']) ?></span>
                    </div>
                    <span class="badge-soft badge-danger"><?= (int) $due['days_late'] ?> days late</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="empty-state">
                No tenants are currently flagged as 30+ days overdue. Your collection record looks clear right now.
              </div>
            <?php endif; ?>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="panel-card h-100">
            <div class="section-title">
              <h2>Maintenance overview</h2>
              <span><?= $totalMaintenance ?> total request<?= $totalMaintenance === 1 ? '' : 's' ?></span>
            </div>

            <div class="progress-stack">
              <div>
                <div class="progress-label">
                  <span>Pending</span>
                  <strong><?= $pendingMaintenance ?></strong>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-warning" style="width: <?= percentage($pendingMaintenance, max($totalMaintenance, 1)) ?>%"></div>
                </div>
              </div>

              <div>
                <div class="progress-label">
                  <span>In progress</span>
                  <strong><?= $inProgressMaintenance ?></strong>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-info" style="width: <?= percentage($inProgressMaintenance, max($totalMaintenance, 1)) ?>%"></div>
                </div>
              </div>

              <div>
                <div class="progress-label">
                  <span>Resolved</span>
                  <strong><?= $resolvedMaintenance ?></strong>
                </div>
                <div class="progress">
                  <div class="progress-bar bg-success" style="width: <?= percentage($resolvedMaintenance, max($totalMaintenance, 1)) ?>%"></div>
                </div>
              </div>
            </div>

            <div class="empty-state mt-4" style="text-align: left;">
              <strong style="display:block; color: var(--text); margin-bottom: 6px;">Resolution health</strong>
              <?= $resolvedRate ?>% of maintenance requests are marked resolved based on the current records.
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="panel-card h-100">
            <div class="section-title">
              <h2>Recent payments</h2>
              <span>Latest activity feed</span>
            </div>

            <?php if (!empty($recentPayments)): ?>
              <ul class="list-clean">
                <?php foreach ($recentPayments as $payment): ?>
                  <li class="list-row">
                    <div>
                      <strong><?= htmlspecialchars($payment['name']) ?></strong>
                      <span>Unit <?= htmlspecialchars($payment['unit_number']) ?> on <?= htmlspecialchars(date('M d, Y', strtotime($payment['payment_date']))) ?></span>
                    </div>
                    <span class="badge-soft badge-success"><?= htmlspecialchars(formatPeso((float) $payment['amount'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="empty-state">
                No payment entries yet. Once rent payments are recorded, they’ll show up here automatically.
              </div>
            <?php endif; ?>
          </section>
        </div>
      </div>

      <div class="footer-note">
        Apartment Management System dashboard for <?= htmlspecialchars(date('F j, Y')) ?>.
      </div>
    </main>
  </div>

  <script>
    const revenueCtx = document.getElementById('revenueChart');
    const revenueData = <?= json_encode(array_values($revenueData)) ?>;

    new Chart(revenueCtx, {
      type: 'bar',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Monthly Revenue',
          data: revenueData,
          backgroundColor: 'rgba(15, 76, 129, 0.78)',
          borderRadius: 12,
          borderSkipped: false,
          hoverBackgroundColor: 'rgba(255, 183, 3, 0.82)'
        }]
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (context) => `PHP ${Number(context.raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
            }
          }
        },
        scales: {
          x: {
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: {
              callback: (value) => `PHP ${Number(value).toLocaleString()}`
            }
          }
        }
      }
    });
  </script>
</body>
</html>
