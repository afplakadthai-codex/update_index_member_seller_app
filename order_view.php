<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$root = dirname(__DIR__);
$memberRoot = __DIR__;

$guardCandidates = [
    $memberRoot . '/_guard.php',
    $memberRoot . '/guard.php',
    $root . '/includes/auth.php',
    $root . '/includes/auth_bootstrap.php',
];

foreach ($guardCandidates as $guardFile) {
    if (is_file($guardFile)) {
        require_once $guardFile;
    }
}

$configCandidates = [
    $root . '/config/db.php',
    $root . '/includes/db.php',
    $root . '/db.php',
];

foreach ($configCandidates as $configFile) {
    if (is_file($configFile)) {
        require_once $configFile;
        break;
    }
}

$orderCancelHelper = $root . '/includes/order_cancel.php';
if (is_file($orderCancelHelper)) {
    require_once $orderCancelHelper;
}

if (!function_exists('bv_member_orders_h')) {
    function bv_member_orders_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_member_orders_db')) {
    function bv_member_orders_db(): PDO
    {
        $candidates = [
            $GLOBALS['pdo'] ?? null,
            $GLOBALS['PDO'] ?? null,
            $GLOBALS['db'] ?? null,
            $GLOBALS['conn'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof PDO) {
                return $candidate;
            }
        }

        throw new RuntimeException('PDO connection not found.');
    }
}

if (!function_exists('bv_member_orders_current_user_id')) {
    function bv_member_orders_current_user_id(): int
    {
        $candidates = [
            $_SESSION['user']['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['member']['id'] ?? null,
            $_SESSION['seller']['id'] ?? null,
            $_SESSION['user_id'] ?? null,
            $_SESSION['member_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }
}

if (!function_exists('bv_member_orders_current_user_name')) {
    function bv_member_orders_current_user_name(): string
    {
        $candidates = [
            trim((string) (($_SESSION['user']['display_name'] ?? '') ?: '')),
            trim((string) (((string) ($_SESSION['user']['first_name'] ?? '')) . ' ' . ((string) ($_SESSION['user']['last_name'] ?? '')))),
            trim((string) ($_SESSION['user']['email'] ?? '')),
            trim((string) ($_SESSION['member']['name'] ?? '')),
            trim((string) ($_SESSION['member']['email'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'Customer';
    }
}

if (!function_exists('bv_member_orders_is_logged_in')) {
    function bv_member_orders_is_logged_in(): bool
    {
        return bv_member_orders_current_user_id() > 0;
    }
}

if (!function_exists('bv_member_orders_build_url')) {
    function bv_member_orders_build_url(string $path, array $params = []): string
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = $value;
        }

        if (!$clean) {
            return $path;
        }

        return $path . (strpos($path, '?') === false ? '?' : '&') . http_build_query($clean);
    }
}

if (!function_exists('bv_member_orders_current_request_uri')) {
    function bv_member_orders_current_request_uri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/member/order_view.php');
        if ($uri === '' || strpos($uri, '://') !== false || strpos($uri, "\n") !== false || strpos($uri, "\r") !== false) {
            return '/member/order_view.php';
        }
        return $uri[0] === '/' ? $uri : '/' . ltrim($uri, '/');
    }
}

if (!function_exists('bv_member_orders_login_url')) {
    function bv_member_orders_login_url(): string
    {
        $redirect = bv_member_orders_current_request_uri();
        $candidates = [
            '/login.php',
            '/member/login.php',
            'login.php',
            'member/login.php',
        ];

        foreach ($candidates as $candidate) {
            $full = $candidate[0] === '/' ? dirname(__DIR__) . $candidate : dirname(__DIR__) . '/' . $candidate;
            if (is_file($full)) {
                return $candidate . '?redirect=' . rawurlencode($redirect);
            }
        }

        return '/login.php?redirect=' . rawurlencode($redirect);
    }
}

if (!function_exists('bv_member_orders_redirect')) {
    function bv_member_orders_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('bv_member_orders_table_exists')) {
    function bv_member_orders_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bv_member_orders_columns')) {
    function bv_member_orders_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        $key = $table;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['Field'])) {
                    $columns[(string) $row['Field']] = true;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        $cache[$key] = $columns;
        return $columns;
    }
}

if (!function_exists('bv_member_orders_has_col')) {
    function bv_member_orders_has_col(PDO $pdo, string $table, string $column): bool
    {
        $cols = bv_member_orders_columns($pdo, $table);
        return isset($cols[$column]);
    }
}

if (!function_exists('bv_member_orders_money')) {
    function bv_member_orders_money($amount, ?string $currency = null): string
    {
        $currency = strtoupper(trim((string) ($currency ?: 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        if ($amount === null || $amount === '') {
            return $currency . ' 0.00';
        }

        if (function_exists('money') && is_numeric($amount)) {
            return (string) money((float) $amount, $currency);
        }

        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('bv_member_orders_status_badge')) {
    function bv_member_orders_status_badge(string $status): array
    {
        $status = strtolower(trim($status));

        $map = [
            'pending' => ['Pending', '#9a6700', '#fff8c5'],
            'pending_payment' => ['Pending Payment', '#9a6700', '#fff8c5'],
            'reserved' => ['Reserved', '#7c3aed', '#ede9fe'],
            'paid' => ['Paid', '#166534', '#dcfce7'],
            'paid-awaiting-verify' => ['Awaiting Verify', '#0f766e', '#ccfbf1'],
            'processing' => ['Processing', '#1d4ed8', '#dbeafe'],
            'confirmed' => ['Confirmed', '#0369a1', '#e0f2fe'],
            'packing' => ['Packing', '#4338ca', '#e0e7ff'],
            'shipped' => ['Shipped', '#334155', '#e2e8f0'],
            'completed' => ['Completed', '#065f46', '#d1fae5'],
            'cancelled' => ['Cancelled', '#991b1b', '#fee2e2'],
            'refunded' => ['Refunded', '#be123c', '#ffe4e6'],
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        return [ucfirst($status !== '' ? str_replace('_', ' ', $status) : 'Unknown'), '#374151', '#e5e7eb'];
    }
}

if (!function_exists('bv_member_orders_payment_badge')) {
    function bv_member_orders_payment_badge(string $status): array
    {
        $status = strtolower(trim($status));

        $map = [
            'paid' => ['Paid', '#166534', '#dcfce7'],
            'pending' => ['Pending', '#9a6700', '#fff8c5'],
            'unpaid' => ['Unpaid', '#b45309', '#ffedd5'],
            'authorized' => ['Authorized', '#0369a1', '#e0f2fe'],
            'failed' => ['Failed', '#991b1b', '#fee2e2'],
            'refunded' => ['Refunded', '#be123c', '#ffe4e6'],
            'partially_refunded' => ['Partial Refund', '#9d174d', '#fce7f3'],
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        return [ucfirst($status !== '' ? str_replace('_', ' ', $status) : 'Unknown'), '#374151', '#e5e7eb'];
    }
}

if (!function_exists('bv_member_orders_can_cancel')) {
    function bv_member_orders_can_cancel(array $order): bool
    {
        if (function_exists('bv_order_cancel_is_allowed')) {
            try {
                return bv_order_cancel_is_allowed(
                    $order,
                    bv_member_orders_current_user_id(),
                    'user',
                    'buyer'
                );
            } catch (Throwable $e) {
                return false;
            }
        }

        $status = strtolower(trim((string) ($order['status'] ?? '')));
        return in_array($status, ['pending', 'pending_payment', 'paid-awaiting-verify', 'paid'], true);
    }
}

if (!function_exists('bv_member_orders_cancel_info')) {
    function bv_member_orders_cancel_info(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        if (function_exists('bv_order_cancel_get_by_order_id')) {
            try {
                return bv_order_cancel_get_by_order_id($orderId);
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('bv_member_orders_order_code')) {
    function bv_member_orders_order_code(array $row): string
    {
        foreach (['order_code', 'code', 'reference'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '#' . (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('bv_member_orders_order_total')) {
    function bv_member_orders_order_total(array $row): float
    {
        foreach (['total', 'grand_total', 'amount_total'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null && is_numeric($row[$field])) {
                return round((float) $row[$field], 2);
            }
        }

        $subtotal = isset($row['subtotal']) && is_numeric($row['subtotal']) ? (float) $row['subtotal'] : 0.0;
        $shipping = isset($row['shipping_amount']) && is_numeric($row['shipping_amount']) ? (float) $row['shipping_amount'] : 0.0;
        $discount = isset($row['discount_amount']) && is_numeric($row['discount_amount']) ? (float) $row['discount_amount'] : 0.0;

        return round(($subtotal - $discount) + $shipping, 2);
    }
}

if (!function_exists('bv_member_orders_find_detail_url')) {
    function bv_member_orders_find_detail_url(int $orderId): string
    {
        $candidates = [
            '/member/order_detail.php',
            '/member/order-view.php',
            '/member/order_view_detail.php',
            '/member/orders_detail.php',
            '/member/order.php',
        ];

        foreach ($candidates as $candidate) {
            $full = dirname(__DIR__) . $candidate;
            if (is_file($full)) {
                return bv_member_orders_build_url($candidate, ['id' => $orderId]);
            }
        }

        return bv_member_orders_build_url('/member/order_detail.php', ['id' => $orderId]);
    }
}

if (!function_exists('bv_member_orders_find_dashboard_url')) {
    function bv_member_orders_find_dashboard_url(): string
    {
        $candidates = [
            '/member/index.php',
            '/member/dashboard.php',
            '/member/home.php',
            '/index.php',
        ];

        foreach ($candidates as $candidate) {
            $full = dirname(__DIR__) . $candidate;
            if (is_file($full)) {
                return $candidate;
            }
        }

        return '/member/index.php';
    }
}

if (!function_exists('bv_member_orders_collect_summary_counts')) {
    function bv_member_orders_collect_summary_counts(array $orders): array
    {
        $summary = [
            'all' => count($orders),
            'pending' => 0,
            'paid' => 0,
            'shipped' => 0,
            'completed' => 0,
            'cancel_requested' => 0,
        ];

        foreach ($orders as $order) {
            $status = strtolower(trim((string) ($order['status'] ?? '')));
            if (in_array($status, ['pending', 'pending_payment', 'reserved', 'paid-awaiting-verify'], true)) {
                $summary['pending']++;
            }
            if (in_array($status, ['paid', 'processing', 'confirmed', 'packing'], true)) {
                $summary['paid']++;
            }
            if ($status === 'shipped') {
                $summary['shipped']++;
            }
            if ($status === 'completed') {
                $summary['completed']++;
            }
            if (!empty($order['__cancel_request'])) {
                $summary['cancel_requested']++;
            }
        }

        return $summary;
    }
}

if (!bv_member_orders_is_logged_in()) {
    bv_member_orders_redirect(bv_member_orders_login_url());
}

try {
    $pdo = bv_member_orders_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection not available.';
    exit;
}

if (!bv_member_orders_table_exists($pdo, 'orders')) {
    http_response_code(500);
    echo 'Orders table not found.';
    exit;
}

$currentUserId = bv_member_orders_current_user_id();
$currentUserName = bv_member_orders_current_user_name();

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
$search = trim((string) ($_GET['q'] ?? ''));
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$allowedStatusFilters = [
    'all',
    'pending',
    'paid',
    'shipped',
    'completed',
    'cancelled',
    'refunded',
    'cancel_requested',
];

if ($statusFilter === '' || !in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$where = ["user_id = :user_id"];
$params = [':user_id' => $currentUserId];

if ($statusFilter !== 'all' && $statusFilter !== 'cancel_requested') {
    if ($statusFilter === 'pending') {
        $where[] = "status IN ('pending','pending_payment','reserved','paid-awaiting-verify')";
    } elseif ($statusFilter === 'paid') {
        $where[] = "status IN ('paid','processing','confirmed','packing')";
    } elseif ($statusFilter === 'shipped') {
        $where[] = "status = 'shipped'";
    } elseif ($statusFilter === 'completed') {
        $where[] = "status = 'completed'";
    } elseif ($statusFilter === 'cancelled') {
        $where[] = "status = 'cancelled'";
    } elseif ($statusFilter === 'refunded') {
        $where[] = "status = 'refunded'";
    }
}

$searchParts = [];
if ($search !== '') {
    $searchLike = '%' . $search . '%';

    foreach (['order_code', 'buyer_name', 'buyer_email', 'payment_reference', 'stripe_payment_intent_id', 'stripe_session_id'] as $field) {
        if (bv_member_orders_has_col($pdo, 'orders', $field)) {
            $searchParts[] = "{$field} LIKE :search";
        }
    }

    if ($searchParts) {
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
        $params[':search'] = $searchLike;
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderBy = 'ORDER BY id DESC';
if (bv_member_orders_has_col($pdo, 'orders', 'created_at')) {
    $orderBy = 'ORDER BY created_at DESC, id DESC';
}

$countSql = "SELECT COUNT(*) FROM orders {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$sql = "SELECT * FROM orders {$whereSql} {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($orders)) {
    $orders = [];
}

foreach ($orders as &$order) {
    $orderId = (int) ($order['id'] ?? 0);
    $cancelRequest = bv_member_orders_cancel_info($orderId);
    $order['__cancel_request'] = $cancelRequest;
    $order['__can_cancel'] = bv_member_orders_can_cancel($order) && empty($cancelRequest);
    $order['__detail_url'] = bv_member_orders_find_detail_url($orderId);
    $order['__display_total'] = bv_member_orders_order_total($order);
    $order['__display_code'] = bv_member_orders_order_code($order);
}
unset($order);

$allOrdersForSummary = [];
try {
    $summaryStmt = $pdo->prepare("SELECT id, status, order_code, total, currency FROM orders WHERE user_id = ? ORDER BY id DESC");
    $summaryStmt->execute([$currentUserId]);
    $allOrdersForSummary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($allOrdersForSummary as &$summaryOrder) {
        $summaryOrder['__cancel_request'] = bv_member_orders_cancel_info((int) ($summaryOrder['id'] ?? 0));
    }
    unset($summaryOrder);
} catch (Throwable $e) {
    $allOrdersForSummary = $orders;
}

$summary = bv_member_orders_collect_summary_counts($allOrdersForSummary);

$pageTitle = 'My Orders | Bettavaro';
$dashboardUrl = bv_member_orders_find_dashboard_url();

$flash = $_SESSION['order_cancel_flash'] ?? null;
unset($_SESSION['order_cancel_flash']);

$flashStatus = is_array($flash) ? (string) ($flash['status'] ?? '') : '';
$flashMessage = is_array($flash) ? (string) ($flash['message'] ?? '') : '';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bv_member_orders_h($pageTitle); ?></title>
    <meta name="description" content="View your Bettavaro orders securely.">
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root{
            --bg:#07130e;
            --bg-2:#0b1b14;
            --panel:#ffffff;
            --ink:#0f172a;
            --muted:#64748b;
            --line:#dbe2ea;
            --gold:#d4b06a;
            --green:#166534;
            --green-soft:#dcfce7;
            --amber:#9a6700;
            --amber-soft:#fff8c5;
            --red:#991b1b;
            --red-soft:#fee2e2;
            --blue:#1d4ed8;
            --blue-soft:#dbeafe;
            --shadow:0 24px 70px rgba(0,0,0,.24);
            --radius:22px;
            --radius-sm:14px;
            --max:1240px;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{
            font-family:Inter,"Segoe UI",Arial,sans-serif;
            color:#eef4ef;
            background:
                radial-gradient(circle at top, #123021 0%, #08140f 42%, #040b08 100%);
            min-height:100vh;
        }
        a{text-decoration:none;color:inherit}
        .wrap{max-width:var(--max);margin:0 auto;padding:28px 16px 80px}
        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:14px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        .crumbs{
            color:#d5dfd7;
            font-size:14px;
        }
        .crumbs a{color:var(--gold)}
        .actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn,
        .btn-outline,
        .btn-soft{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:44px;
            padding:0 16px;
            border-radius:999px;
            font-weight:800;
            font-size:14px;
            border:1px solid transparent;
            transition:.18s ease;
        }
        .btn{
            color:#0b140f;
            background:linear-gradient(180deg,#f1dab0 0%, #d4b06a 100%);
            box-shadow:0 10px 24px rgba(212,176,106,.22);
        }
        .btn:hover{transform:translateY(-1px)}
.btn-outline{
    color:#0f172a;
    border-color:#cbd5e1;
    background:#ffffff;
    box-shadow:0 4px 12px rgba(15,23,42,.08);
}
.btn-outline:hover{
    background:#f8fafc;
    border-color:#94a3b8;
}
        .btn-soft{
            color:#d5dfd7;
            background:rgba(255,255,255,.06);
            border-color:rgba(255,255,255,.08);
        }
        .hero{
            background:linear-gradient(135deg, rgba(255,255,255,.10), rgba(255,255,255,.05));
            border:1px solid rgba(255,255,255,.12);
            border-radius:28px;
            padding:26px;
            box-shadow:var(--shadow);
            backdrop-filter:blur(10px);
            margin-bottom:20px;
        }
        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#efd59c;
            text-transform:uppercase;
            letter-spacing:.12em;
            font-size:12px;
            font-weight:900;
            margin-bottom:10px;
        }
        .eyebrow:before{
            content:"";
            width:18px;
            height:2px;
            border-radius:999px;
            background:linear-gradient(90deg, transparent, #efd59c);
            display:inline-block;
        }
        .hero h1{
            margin:0 0 8px;
            font-size:clamp(34px,5vw,54px);
            line-height:1.04;
            letter-spacing:-.03em;
        }
        .hero p{
            margin:0;
            max-width:780px;
            color:#d3ddd6;
            line-height:1.75;
            font-size:15px;
        }
        .summary-grid{
            display:grid;
            grid-template-columns:repeat(5,minmax(0,1fr));
            gap:14px;
            margin:20px 0 24px;
        }
        .summary-card{
            background:rgba(255,255,255,.96);
            color:var(--ink);
            border-radius:20px;
            padding:18px;
            box-shadow:var(--shadow);
            border:1px solid rgba(255,255,255,.35);
        }
        .summary-label{
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.10em;
            font-weight:900;
            color:#64748b;
            margin-bottom:8px;
        }
        .summary-value{
            font-size:30px;
            font-weight:900;
            line-height:1;
        }
        .controls{
            display:grid;
            grid-template-columns:1.2fr .8fr;
            gap:16px;
            margin-bottom:18px;
        }
        .panel{
            background:rgba(255,255,255,.96);
            color:var(--ink);
            border-radius:24px;
            box-shadow:var(--shadow);
            border:1px solid rgba(255,255,255,.35);
            overflow:hidden;
        }
        .panel-body{padding:20px}
        .filters{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .filters a{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:40px;
            padding:0 14px;
            border-radius:999px;
            font-size:13px;
            font-weight:800;
            color:#334155;
            background:#f8fafc;
            border:1px solid #e2e8f0;
        }
        .filters a.active{
            color:#0b140f;
            background:#f2e1bb;
            border-color:#e2c788;
        }
        .search-form{
            display:flex;
            gap:10px;
            align-items:center;
        }
        .search-form input{
            flex:1;
            min-height:44px;
            border-radius:14px;
            border:1px solid #dbe2ea;
            padding:0 14px;
            font-size:14px;
            color:#0f172a;
            background:#fff;
        }
        .alert{
            margin:0 0 18px;
            padding:14px 16px;
            border-radius:16px;
            font-size:14px;
            font-weight:700;
        }
        .alert.success{
            background:#ecfdf3;
            color:#166534;
            border:1px solid #bbf7d0;
        }
        .alert.error{
            background:#fef2f2;
            color:#b91c1c;
            border:1px solid #fecaca;
        }
        .order-list{
            display:grid;
            gap:16px;
        }
        .order-card{
            background:#fff;
            color:var(--ink);
            border:1px solid #e7edf4;
            border-radius:22px;
            padding:18px;
        }
        .order-head{
            display:flex;
            justify-content:space-between;
            gap:18px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .order-code{
            font-size:22px;
            font-weight:900;
            line-height:1.1;
        }
        .order-meta{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            align-items:center;
            margin-top:8px;
        }
        .badge{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:30px;
            padding:0 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:900;
            letter-spacing:.02em;
        }
        .order-body{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:14px;
            margin-bottom:14px;
        }
        .meta-box{
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:16px;
            padding:14px;
        }
        .meta-box .label{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.10em;
            color:#64748b;
            font-weight:900;
            margin-bottom:7px;
        }
        .meta-box .value{
            font-size:15px;
            font-weight:800;
            color:#0f172a;
            word-break:break-word;
        }
        .order-footer{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
            padding-top:14px;
            border-top:1px solid #ecf0f4;
        }
        .footer-note{
            color:#64748b;
            font-size:13px;
            line-height:1.7;
        }
        .footer-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .empty{
            padding:34px 20px;
            text-align:center;
            color:#64748b;
        }
        .pagination{
            display:flex;
            justify-content:center;
            gap:10px;
            flex-wrap:wrap;
            margin-top:22px;
        }
        .pagination a,
        .pagination span{
            min-width:42px;
            height:42px;
            padding:0 14px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            background:rgba(255,255,255,.96);
            color:#0f172a;
            font-weight:900;
            border:1px solid rgba(255,255,255,.4);
        }
        .pagination .current{
            background:#f2e1bb;
            border-color:#e2c788;
        }
        @media (max-width: 1024px){
            .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .controls{grid-template-columns:1fr}
            .order-body{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
        @media (max-width: 640px){
            .summary-grid{grid-template-columns:1fr}
            .order-body{grid-template-columns:1fr}
            .hero{padding:22px}
            .order-code{font-size:18px}
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="crumbs">
                <a href="<?= bv_member_orders_h($dashboardUrl); ?>">Dashboard</a>
                <span> / </span>
                <span>My Orders</span>
            </div>
            <div class="actions">
                <a class="btn-outline" href="<?= bv_member_orders_h($dashboardUrl); ?>">Back to Dashboard</a>
            </div>
        </div>

        <section class="hero">
            <div class="eyebrow">Customer Orders</div>
            <h1>My Orders</h1>
            <p>Welcome back, <?= bv_member_orders_h($currentUserName); ?>. This page shows only your own orders. Other customers’ orders stay invisible—like a good vault, but less dramatic.</p>
        </section>

        <section class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">All Orders</div>
                <div class="summary-value"><?= (int) $summary['all']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Pending</div>
                <div class="summary-value"><?= (int) $summary['pending']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Paid / Processing</div>
                <div class="summary-value"><?= (int) $summary['paid']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Shipped</div>
                <div class="summary-value"><?= (int) $summary['shipped']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Cancel Requested</div>
                <div class="summary-value"><?= (int) $summary['cancel_requested']; ?></div>
            </div>
        </section>

        <?php if ($flashMessage !== ''): ?>
            <div class="alert <?= in_array($flashStatus, ['requested', 'success'], true) ? 'success' : 'error'; ?>">
                <?= bv_member_orders_h($flashMessage); ?>
            </div>
        <?php endif; ?>

        <section class="controls">
            <div class="panel">
                <div class="panel-body">
                    <div class="filters">
                        <?php
                        $filterMap = [
                            'all' => 'All',
                            'pending' => 'Pending',
                            'paid' => 'Paid / Processing',
                            'shipped' => 'Shipped',
                            'completed' => 'Completed',
                            'cancel_requested' => 'Cancel Requested',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ];
                        foreach ($filterMap as $key => $label):
                            $url = bv_member_orders_build_url('/member/order_view.php', [
                                'status' => $key,
                                'q' => $search !== '' ? $search : null,
                            ]);
                        ?>
                            <a class="<?= $statusFilter === $key ? 'active' : ''; ?>" href="<?= bv_member_orders_h($url); ?>"><?= bv_member_orders_h($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body">
                    <form class="search-form" method="get" action="/member/order_view.php">
                        <input type="hidden" name="status" value="<?= bv_member_orders_h($statusFilter); ?>">
                        <input type="text" name="q" value="<?= bv_member_orders_h($search); ?>" placeholder="Search order code, email, payment ref">
                        <button class="btn" type="submit">Search</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-body">
                <?php if (!$orders): ?>
                    <div class="empty">
                        <h2 style="margin:0 0 8px;color:#0f172a;">No orders found</h2>
                        <p style="margin:0;">Nothing here yet for this filter. Quiet page, which is great for stress levels, less great for sales drama.</p>
                    </div>
                <?php else: ?>
                    <div class="order-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderId = (int) ($order['id'] ?? 0);
                            $displayCode = (string) ($order['__display_code'] ?? ('#' . $orderId));
                            $currency = strtoupper(trim((string) ($order['currency'] ?? 'USD')));
                            $displayTotal = (float) ($order['__display_total'] ?? 0);
                            $status = strtolower(trim((string) ($order['status'] ?? '')));
                            $paymentStatus = strtolower(trim((string) (($order['payment_status'] ?? '') ?: ($order['payment_state'] ?? ''))));
                            $orderSource = strtolower(trim((string) ($order['order_source'] ?? 'shop')));
                            $createdAt = trim((string) ($order['created_at'] ?? ''));
                            $paidAt = trim((string) ($order['paid_at'] ?? ''));
                            $detailUrl = (string) ($order['__detail_url'] ?? bv_member_orders_find_detail_url($orderId));
                            $cancelRequest = $order['__cancel_request'] ?? null;
                            $canCancel = !empty($order['__can_cancel']);
                            [$statusLabel, $statusColor, $statusBg] = bv_member_orders_status_badge($status);
                            [$paymentLabel, $paymentColor, $paymentBg] = bv_member_orders_payment_badge($paymentStatus !== '' ? $paymentStatus : 'unpaid');
                            ?>
                            <?php if ($statusFilter === 'cancel_requested' && empty($cancelRequest)) { continue; } ?>

                            <article class="order-card">
                                <div class="order-head">
                                    <div>
                                        <div class="order-code"><?= bv_member_orders_h($displayCode); ?></div>
                                        <div class="order-meta">
                                            <span class="badge" style="color:<?= bv_member_orders_h($statusColor); ?>;background:<?= bv_member_orders_h($statusBg); ?>;"><?= bv_member_orders_h($statusLabel); ?></span>
                                            <span class="badge" style="color:<?= bv_member_orders_h($paymentColor); ?>;background:<?= bv_member_orders_h($paymentBg); ?>;"><?= bv_member_orders_h($paymentLabel); ?></span>
                                            <?php if ($orderSource !== ''): ?>
                                                <span class="badge" style="color:#334155;background:#e2e8f0;"><?= bv_member_orders_h(strtoupper($orderSource)); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($cancelRequest)): ?>
                                                <?php
                                                $cancelStatus = strtolower(trim((string) ($cancelRequest['status'] ?? 'requested')));
                                                [$cancelLabel, $cancelColor, $cancelBg] = bv_member_orders_status_badge($cancelStatus === 'approved' ? 'processing' : ($cancelStatus === 'requested' ? 'pending' : $cancelStatus));
                                                ?>
                                                <span class="badge" style="color:<?= bv_member_orders_h($cancelColor); ?>;background:<?= bv_member_orders_h($cancelBg); ?>;">Cancel <?= bv_member_orders_h(ucfirst($cancelStatus)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.10em;color:#64748b;font-weight:900;margin-bottom:6px;">Order Total</div>
                                        <div style="font-size:28px;font-weight:900;color:#0f172a;"><?= bv_member_orders_h(bv_member_orders_money($displayTotal, $currency)); ?></div>
                                    </div>
                                </div>

                                <div class="order-body">
                                    <div class="meta-box">
                                        <div class="label">Created At</div>
                                        <div class="value"><?= bv_member_orders_h($createdAt !== '' ? $createdAt : '-'); ?></div>
                                    </div>
                                    <div class="meta-box">
                                        <div class="label">Paid At</div>
                                        <div class="value"><?= bv_member_orders_h($paidAt !== '' ? $paidAt : '-'); ?></div>
                                    </div>
                                    <div class="meta-box">
                                        <div class="label">Payment Method</div>
                                        <div class="value"><?= bv_member_orders_h(trim((string) (($order['payment_method'] ?? '') ?: '-'))); ?></div>
                                    </div>
                                    <div class="meta-box">
                                        <div class="label">Order Source</div>
                                        <div class="value"><?= bv_member_orders_h($orderSource !== '' ? strtoupper($orderSource) : 'SHOP'); ?></div>
                                    </div>
                                </div>

                                <div class="order-footer">
                                    <div class="footer-note">
                                        <?php if (!empty($cancelRequest)): ?>
                                            Cancel request status: <strong><?= bv_member_orders_h((string) ($cancelRequest['status'] ?? 'requested')); ?></strong>
                                            <?php if (!empty($cancelRequest['requested_at'])): ?>
                                                • requested at <?= bv_member_orders_h((string) $cancelRequest['requested_at']); ?>
                                            <?php endif; ?>
                                        <?php elseif ($canCancel): ?>
                                            This order can still request cancellation from the next detail page.
                                        <?php else: ?>
                                            This order is currently not eligible for customer cancellation.
                                        <?php endif; ?>
                                    </div>
                                    <div class="footer-actions">
                                        <a class="btn-outline" href="<?= bv_member_orders_h($detailUrl); ?>">Order Detail</a>
                                        <?php if ($canCancel && empty($cancelRequest)): ?>
                                            <a class="btn-soft" href="<?= bv_member_orders_h($detailUrl); ?>#cancel-order">Request Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $prevPage = $page > 1 ? $page - 1 : null;
                            $nextPage = $page < $totalPages ? $page + 1 : null;

                            if ($prevPage !== null):
                            ?>
                                <a href="<?= bv_member_orders_h(bv_member_orders_build_url('/member/order_view.php', ['status' => $statusFilter, 'q' => $search !== '' ? $search : null, 'page' => $prevPage])); ?>">‹</a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <?php if ($i === $page): ?>
                                    <span class="current"><?= (int) $i; ?></span>
                                <?php else: ?>
                                    <a href="<?= bv_member_orders_h(bv_member_orders_build_url('/member/order_view.php', ['status' => $statusFilter, 'q' => $search !== '' ? $search : null, 'page' => $i])); ?>"><?= (int) $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($nextPage !== null): ?>
                                <a href="<?= bv_member_orders_h(bv_member_orders_build_url('/member/order_view.php', ['status' => $statusFilter, 'q' => $search !== '' ? $search : null, 'page' => $nextPage])); ?>">›</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</body>
</html>