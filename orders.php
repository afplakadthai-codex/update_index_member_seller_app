<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$bootstrapCandidates = [
    __DIR__ . '/_listing_bootstrap.php',
    dirname(__DIR__) . '/member/_listing_bootstrap.php',
];

foreach ($bootstrapCandidates as $bootstrapPath) {
    if (is_file($bootstrapPath)) {
        require_once $bootstrapPath;
        break;
    }
}
if (!function_exists('bv_member_h')) {
    function bv_member_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('seller_orders_http_500')) {
    function seller_orders_http_500(string $message): void
    {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
}

if (!function_exists('seller_orders_db')) {
    function seller_orders_db()
    {
        $candidates = [
            $GLOBALS['pdo'] ?? null,
            $GLOBALS['PDO'] ?? null,
            $GLOBALS['conn'] ?? null,
            $GLOBALS['db'] ?? null,
            $GLOBALS['mysqli'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate instanceof PDO || $candidate instanceof mysqli) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('seller_orders_is_pdo')) {
    function seller_orders_is_pdo($db): bool
    {
        return $db instanceof PDO;
    }
}

if (!function_exists('seller_orders_is_mysqli')) {
    function seller_orders_is_mysqli($db): bool
    {
        return $db instanceof mysqli;
    }
}

if (!function_exists('seller_orders_mysqli_sql')) {
    function seller_orders_mysqli_sql(string $sql, array $params): array
    {
        if (!$params) {
            return [$sql, []];
        }

        $ordered = [];
        $parsedSql = preg_replace_callback('/:[a-zA-Z0-9_]+/', static function (array $m) use ($params, &$ordered): string {
            $name = $m[0];
            if (array_key_exists($name, $params)) {
                $ordered[] = $params[$name];
                return '?';
            }
            return $name;
        }, $sql);

        return [$parsedSql, $ordered];
    }
}

if (!function_exists('seller_orders_mysqli_bind_execute')) {
    function seller_orders_mysqli_bind_execute(mysqli_stmt $stmt, array $params): void
    {
        if ($params) {
            $types = '';
            $values = [];
            foreach ($params as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $value;
            }

            $bindArgs = [$types];
            foreach ($values as $i => $value) {
                $bindArgs[] = &$values[$i];
            }

            if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
                throw new RuntimeException('Failed to bind mysqli parameters.');
            }
        }

        if (!$stmt->execute()) {
            throw new RuntimeException((string)$stmt->error);
        }
    }
}

if (!function_exists('seller_orders_pdo_execute')) {
    function seller_orders_pdo_execute(PDOStatement $stmt, string $sql, array $params = []): void
    {
        $hasNamedPlaceholders = (bool) preg_match('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql);
        $hasPositionalPlaceholders = strpos($sql, '?') !== false;

        if ($hasNamedPlaceholders && $hasPositionalPlaceholders) {
            throw new RuntimeException('Mixed named and positional placeholders are not supported.');
        }

        if ($hasNamedPlaceholders) {
            $normalized = [];

            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $normalized[$key] = $value;
                    continue;
                }

                $key = (string) $key;
                if ($key !== '' && $key[0] !== ':') {
                    $key = ':' . $key;
                }

                $normalized[$key] = $value;
            }

            $stmt->execute($normalized);
            return;
        }

        $stmt->execute(array_values($params));
    }
}

if (!function_exists('seller_orders_query_all')) {
    function seller_orders_query_all($db, string $sql, array $params = []): array
    {
        if (seller_orders_is_pdo($db)) {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare PDO statement.');
            }
            seller_orders_pdo_execute($stmt, $sql, $params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        }

        if (seller_orders_is_mysqli($db)) {
            [$parsedSql, $orderedParams] = seller_orders_mysqli_sql($sql, $params);
            $stmt = $db->prepare($parsedSql);
            if (!$stmt) {
                throw new RuntimeException((string)$db->error);
            }
            seller_orders_mysqli_bind_execute($stmt, $orderedParams);
            $result = $stmt->get_result();
            if (!$result) {
                $stmt->close();
                return [];
            }
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            $stmt->close();
            return $rows;
        }

        throw new RuntimeException('Unsupported database connection type.');
    }
}

if (!function_exists('seller_orders_query_one')) {
    function seller_orders_query_one($db, string $sql, array $params = []): array
    {
        $rows = seller_orders_query_all($db, $sql, $params);
        return (array)($rows[0] ?? []);
    }
}

if (!function_exists('seller_orders_query_value')) {
    function seller_orders_query_value($db, string $sql, array $params = [])
    {
        $row = seller_orders_query_one($db, $sql, $params);
        if (!$row) {
            return null;
        }
        return reset($row);
    }
}

if (!function_exists('seller_orders_table_exists')) {
    function seller_orders_table_exists($db, string $table): bool
    {
        try {
            if (seller_orders_is_pdo($db)) {
                $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
                $stmt->execute([':table' => $table]);
                return ((int)$stmt->fetchColumn()) > 0;
            }

            if (seller_orders_is_mysqli($db)) {
                $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                if (!$stmt) {
                    return false;
                }
                $tableName = $table;
                $stmt->bind_param('s', $tableName);
                if (!$stmt->execute()) {
                    $stmt->close();
                    return false;
                }
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                if ($result) {
                    $result->free();
                }
                $stmt->close();
                return ((int)($row['cnt'] ?? 0)) > 0;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('seller_orders_table_columns')) {
    function seller_orders_table_columns($db, string $table): array
    {
        $safeTable = str_replace('`', '``', $table);
        try {
            $rows = seller_orders_query_all($db, "SHOW COLUMNS FROM `{$safeTable}`");
            $columns = [];
            foreach ($rows as $row) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = $row;
                }
            }
            return $columns;
        } catch (Throwable $e) {
            return [];
        }
    }
}

$db = seller_orders_db();
if (!$db) {
    seller_orders_http_500('Database connection not available.');
}

if (!seller_orders_table_exists($db, 'orders')) {
    seller_orders_http_500('Orders table not found.');
}
$user = [];
if (function_exists('bv_member_require_seller')) {
    try {
        if (seller_orders_is_pdo($db)) {
            $user = (array)bv_member_require_seller($db);
        } else {
            $user = (array)bv_member_require_seller();
        }
    } catch (ArgumentCountError $e) {
        try {
            $user = (array)bv_member_require_seller();
        } catch (Throwable $ignored) {
            $user = [];
        }
    } catch (TypeError $e) {
        try {
            $user = (array)bv_member_require_seller();
        } catch (Throwable $ignored) {
            $user = [];
        }
    } catch (Throwable $e) {
        $user = [];
    }
}

if (!$user && function_exists('bv_member_require_login')) {
    try {
        $user = (array)bv_member_require_login();
    } catch (Throwable $e) {
        $user = [];
    }
}

if (!$user) {
    $user = (array)($_SESSION['user'] ?? $_SESSION['member'] ?? []);
}

$role = strtolower(trim((string)($user['role'] ?? 'user')));
$sellerStatus = strtolower(trim((string)($user['seller_application_status'] ?? '')));
if ($role !== 'seller' && $sellerStatus !== 'approved') {
    http_response_code(403);
    exit('Seller access denied.');
}

$sellerId = (int)($user['id'] ?? 0);
if ($sellerId <= 0) {
    http_response_code(403);
    exit('Seller access denied.');
}

if (!function_exists('seller_orders_money')) {
    function seller_orders_money($amount, ?string $currency = null): string
    {
        $currency = strtoupper(trim((string)($currency ?: 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        if (function_exists('money') && is_numeric($amount)) {
            try {
                return (string)money((float)$amount, $currency);
            } catch (Throwable $e) {
                // fallback below
            }
        }
        return number_format((float)$amount, 2) . ' ' . $currency;
    }
}

if (!function_exists('seller_orders_status_badge')) {
    function seller_orders_status_badge(?string $status): array
    {
        $token = strtolower(trim((string)$status));
        $map = [
            'pending' => ['Pending', 'bg:#5b4426;color:#ffd39a;border:#9a6b2e'],
            'pending_payment' => ['Pending Payment', 'bg:#5b4426;color:#ffd39a;border:#9a6b2e'],
            'reserved' => ['Reserved', 'bg:#4b2a5f;color:#e8c9ff;border:#7d3ea8'],
            'unpaid' => ['Unpaid', 'bg:#5b4426;color:#ffd39a;border:#9a6b2e'],
            'paid' => ['Paid', 'bg:#1f4f3f;color:#b7ffd8;border:#2c8b69'],
            'paid-awaiting-verify' => ['Paid Awaiting Verify', 'bg:#164b5f;color:#c6f1ff;border:#2d8ca8'],
            'processing' => ['Processing', 'bg:#1a406f;color:#cce2ff;border:#2e69b0'],
            'confirmed' => ['Confirmed', 'bg:#194d5e;color:#bdefff;border:#2d7f98'],
            'packing' => ['Packing', 'bg:#1f3e68;color:#c7dcff;border:#436fb3'],
            'shipped' => ['Shipped', 'bg:#163f64;color:#b9e0ff;border:#2d78b0'],
            'completed' => ['Completed', 'bg:#1e5a2d;color:#cbffd6;border:#339655'],
            'cancelled' => ['Cancelled', 'bg:#5f2222;color:#ffc6c6;border:#aa3d3d'],
            'refunded' => ['Refunded', 'bg:#4f304f;color:#f2d3f2;border:#8f5d8f'],
            'failed' => ['Failed', 'bg:#5f2222;color:#ffc6c6;border:#aa3d3d'],
        ];

        if (isset($map[$token])) {
            return ['label' => $map[$token][0], 'style' => $map[$token][1]];
        }

        return [
            'label' => $token === '' ? 'Unknown' : ucwords(str_replace(['_', '-'], ' ', $token)),
            'style' => 'bg:#2a324b;color:#d2dbf5;border:#475274',
        ];
    }
}

if (!function_exists('seller_orders_period_clause')) {
    function seller_orders_period_clause(string $period, string $dateExpr, array &$params): string
    {
        switch ($period) {
            case 'last_7_days':
                $params[':period_from'] = (new DateTimeImmutable('today -6 days'))->format('Y-m-d 00:00:00');
                return "{$dateExpr} >= :period_from";
            case 'last_30_days':
                $params[':period_from'] = (new DateTimeImmutable('today -29 days'))->format('Y-m-d 00:00:00');
                return "{$dateExpr} >= :period_from";
            case 'last_90_days':
                $params[':period_from'] = (new DateTimeImmutable('today -89 days'))->format('Y-m-d 00:00:00');
                return "{$dateExpr} >= :period_from";
            case 'this_month':
                $params[':period_from'] = (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
                return "{$dateExpr} >= :period_from";
            case 'all_time':
            default:
                return '1=1';
        }
    }
}

if (!function_exists('seller_orders_existing_page')) {
    function seller_orders_existing_page(array $candidates): string
    {
        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2)), '/');
        foreach ($candidates as $candidate) {
            $path = '/' . ltrim($candidate, '/');
            if (is_file($root . $path) || is_file(dirname(__DIR__, 2) . $path)) {
                return $path;
            }
        }
        return '/' . ltrim((string)($candidates[0] ?? '/seller/order_detail.php'), '/');
    }
}

$orderCols = seller_orders_table_columns($db, 'orders');
$hasOrderItemsTable = seller_orders_table_exists($db, 'order_items');
$hasListingsTable = seller_orders_table_exists($db, 'listings');
$orderItemCols = $hasOrderItemsTable ? seller_orders_table_columns($db, 'order_items') : [];
$listingCols = $hasListingsTable ? seller_orders_table_columns($db, 'listings') : [];
$hasUsersTable = seller_orders_table_exists($db, 'users');
$userCols = $hasUsersTable ? seller_orders_table_columns($db, 'users') : [];


$statusOptions = [
    'all' => 'All Statuses',
    'awaiting_payment' => 'Awaiting Payment',
    'paid' => 'Paid',
    'processing' => 'Processing',
    'confirmed' => 'Confirmed',
    'packing' => 'Packing',
    'shipped' => 'Shipped',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
];

$periodOptions = [
    'last_7_days' => 'Last 7 days',
    'last_30_days' => 'Last 30 days',
    'last_90_days' => 'Last 90 days',
    'this_month' => 'This month',
    'all_time' => 'All time',
];

$searchByOptions = [
    'order_code' => 'Order Code',
    'buyer' => 'Buyer',
    'listing_title' => 'Listing Title',
];

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$periodFilter = strtolower(trim((string)($_GET['period'] ?? 'last_30_days')));
$searchBy = strtolower(trim((string)($_GET['search_by'] ?? 'order_code')));
$keyword = trim((string)($_GET['keyword'] ?? ''));

if (!isset($statusOptions[$statusFilter])) {
    $statusFilter = 'all';
}
if (!isset($periodOptions[$periodFilter])) {
    $periodFilter = 'last_30_days';
}
if (!isset($searchByOptions[$searchBy])) {
    $searchBy = 'order_code';
}

$orderStatusExpr = isset($orderCols['status']) ? 'LOWER(COALESCE(o.`status`, \'\'))' : "''";
$paymentStatusExpr = isset($orderCols['payment_status']) ? 'LOWER(COALESCE(o.`payment_status`, \'\'))' : "''";
$shippingStatusExpr = isset($orderCols['shipping_status']) ? 'LOWER(COALESCE(o.`shipping_status`, \'\'))' : "''";
$dateSoldExpr = isset($orderCols['created_at']) ? 'o.`created_at`' : 'NULL';

$buyerNameChain = [];
foreach (['buyer_name', 'customer_name', 'ship_name'] as $col) {
    if (isset($orderCols[$col])) {
        $buyerNameChain[] = "NULLIF(TRIM(o.`{$col}`), '')";
    }
}
if ($hasUsersTable && isset($userCols['first_name']) && isset($userCols['last_name'])) {
    $buyerNameChain[] = "NULLIF(TRIM(CONCAT(COALESCE(u.`first_name`, ''), ' ', COALESCE(u.`last_name`, ''))), '')";
}
if ($hasUsersTable && isset($userCols['email'])) {
    $buyerNameChain[] = "NULLIF(TRIM(u.`email`), '')";
}
foreach (['buyer_email', 'ship_email'] as $col) {
    if (isset($orderCols[$col])) {
        $buyerNameChain[] = "NULLIF(TRIM(o.`{$col}`), '')";
    }
}
$buyerExpr = 'COALESCE(' . implode(', ', array_merge($buyerNameChain, ["'Customer'"])) . ')';

$itemTitleChain = [];
if (isset($orderItemCols['item_title'])) {
    $itemTitleChain[] = "NULLIF(TRIM(oi.`item_title`), '')";
}
if (isset($orderItemCols['title_snapshot'])) {
    $itemTitleChain[] = "NULLIF(TRIM(oi.`title_snapshot`), '')";
}
if (isset($listingCols['title'])) {
    $itemTitleChain[] = "NULLIF(TRIM(l.`title`), '')";
}
$itemTitleExpr = 'COALESCE(' . implode(', ', array_merge($itemTitleChain, ["'—'"])) . ')';
$qtyExpr = isset($orderItemCols['qty']) ? 'COALESCE(oi.`qty`, 0)' : (isset($orderItemCols['quantity']) ? 'COALESCE(oi.`quantity`, 0)' : '0');
$lineTotalExpr = isset($orderItemCols['line_total']) ? 'COALESCE(oi.`line_total`, 0)' : ((isset($orderItemCols['unit_price']) && (isset($orderItemCols['qty']) || isset($orderItemCols['quantity']))) ? 'COALESCE(oi.`unit_price`, 0) * ' . $qtyExpr : '0');
$params = [];

$sellerOwnershipParts = [];
if (isset($orderItemCols['seller_id'])) {
    $sellerOwnershipParts[] = 'oi.`seller_id` = :seller_id_oi';
    $params[':seller_id_oi'] = $sellerId;
}
if (isset($listingCols['seller_id'])) {
    $sellerOwnershipParts[] = 'l.`seller_id` = :seller_id_l';
    $params[':seller_id_l'] = $sellerId;
}

if (!$sellerOwnershipParts) {
    $sellerOwnershipParts[] = '1=0';
}

$where = [
    '(' . implode(' OR ', $sellerOwnershipParts) . ')',
];

$buyerJoinKey = null;
foreach (['buyer_user_id', 'member_id', 'user_id'] as $candidateCol) {
    if (isset($orderCols[$candidateCol])) {
        $buyerJoinKey = $candidateCol;
        break;
    }
}
$buyerJoinSql = ($buyerJoinKey !== null && $hasUsersTable)
    ? "LEFT JOIN `users` u ON u.`id` = o.`{$buyerJoinKey}`"
    : '';
$orderItemsJoinSql = $hasOrderItemsTable ? "LEFT JOIN `order_items` oi ON oi.`order_id` = o.`id`" : '';
$listingsJoinSql = $hasListingsTable ? "LEFT JOIN `listings` l ON l.`id` = oi.`listing_id`" : '';

if ($statusFilter !== 'all') {
    $params[':status_filter'] = $statusFilter;
    $where[] = "{$orderStatusExpr} = :status_filter";
}

$where[] = seller_orders_period_clause($periodFilter, $dateSoldExpr, $params);

if ($keyword !== '') {
    $params[':keyword_like'] = '%' . $keyword . '%';
    if ($searchBy === 'buyer') {
        $where[] = "{$buyerExpr} LIKE :keyword_like";
    } elseif ($searchBy === 'listing_title') {
        $where[] = "{$itemTitleExpr} LIKE :keyword_like";
    } else {
        if (isset($orderCols['order_code'])) {
            $where[] = 'o.`order_code` LIKE :keyword_like';
        } else {
            $where[] = 'CAST(o.`id` AS CHAR) LIKE :keyword_like';
        }
    }
}

$whereSql = implode(' AND ', $where);

$summarySql = "
    SELECT
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} IN ('pending','pending_payment','reserved','unpaid','awaiting_payment') THEN o.`id` END) AS awaiting_payment_count,
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} IN ('paid','processing','confirmed','packing') THEN o.`id` END) AS paid_to_process_count,
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} IN ('paid','processing','confirmed','packing') THEN o.`id` END) AS awaiting_shipment_count,
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} = 'shipped' THEN o.`id` END) AS shipped_count,
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} = 'completed' THEN o.`id` END) AS completed_count,
        COUNT(DISTINCT CASE WHEN {$orderStatusExpr} IN ('cancelled','refunded') THEN o.`id` END) AS cancelled_refunded_count
    FROM `orders` o
    {$orderItemsJoinSql}
    {$listingsJoinSql}
     {$buyerJoinSql}
    WHERE {$whereSql}
";

$summary = seller_orders_query_one($db, $summarySql, $params);

$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countSql = "
    SELECT COUNT(*)
    FROM (
        SELECT o.`id`
        FROM `orders` o
        {$orderItemsJoinSql}
        {$listingsJoinSql}
        {$buyerJoinSql}
        WHERE {$whereSql}
        GROUP BY o.`id`
    ) x
";
$totalRows = (int)seller_orders_query_value($db, $countSql, $params);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$orderCodeExpr = isset($orderCols['order_code']) ? "NULLIF(TRIM(o.`order_code`), '')" : 'NULL';
$currencyExpr = isset($orderCols['currency']) ? 'o.`currency`' : "'USD'";
$subtotalExpr = isset($orderCols['subtotal']) ? 'o.`subtotal`' : 'SUM(' . $lineTotalExpr . ')';
$totalExpr = isset($orderCols['total']) ? 'o.`total`' : 'SUM(' . $lineTotalExpr . ')';
$paidAtExpr = isset($orderCols['paid_at']) ? 'o.`paid_at`' : 'NULL';

$listSql = "
    SELECT
        o.`id` AS order_id,
        COALESCE({$orderCodeExpr}, CAST(o.`id` AS CHAR)) AS order_code,
        {$buyerExpr} AS buyer_display,
        MAX({$itemTitleExpr}) AS item_title,
        SUM({$qtyExpr}) AS qty_total,
        {$subtotalExpr} AS subtotal_amount,
        {$totalExpr} AS total_amount,
        {$currencyExpr} AS currency_code,
        MAX({$dateSoldExpr}) AS date_sold,
        MAX({$paidAtExpr}) AS paid_at,
        {$orderStatusExpr} AS order_status,
        {$paymentStatusExpr} AS payment_status,
        {$shippingStatusExpr} AS shipping_status
    FROM `orders` o
    {$orderItemsJoinSql}
    {$listingsJoinSql}
    {$buyerJoinSql}
    WHERE {$whereSql}
    GROUP BY o.`id`, order_code, buyer_display, subtotal_amount, total_amount, currency_code, order_status, payment_status, shipping_status
    ORDER BY MAX({$dateSoldExpr}) DESC, o.`id` DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$rows = seller_orders_query_all($db, $listSql, $params);


$viewBase = seller_orders_existing_page([
    '/seller/order_detail.php',
    '/seller/order_view.php',
    '/member/order_detail.php',
]);
$manageBase = $viewBase;

$queryBase = [
    'status' => $statusFilter,
    'period' => $periodFilter,
    'search_by' => $searchBy,
    'keyword' => $keyword,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Orders</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0b1020;
            --panel: #131b32;
            --panel2: #17213b;
            --line: #2e3b62;
            --text: #e7ecff;
            --muted: #94a2c9;
            --accent: #6f8cff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--text);
            background: radial-gradient(1000px 520px at 18% -8%, #1b2751 0%, var(--bg) 58%);
        }
        .wrap { max-width: 1320px; margin: 26px auto; padding: 0 16px 30px; }
        .header { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 28px; }
        .sub { color: var(--muted); font-size: 13px; }
        .top-actions a {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: #dfe7ff; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px;
            background: linear-gradient(180deg, #1a2544, #141d36);
        }
        .panel {
            background: linear-gradient(180deg, var(--panel), var(--panel2));
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .filters {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }
        .filters .field { display: flex; flex-direction: column; gap: 6px; }
        .filters label { font-size: 12px; color: var(--muted); }
        .filters select, .filters input {
            width: 100%; border: 1px solid #31406b; background: #0f1730; color: var(--text);
            border-radius: 10px; padding: 10px 11px; outline: none;
        }
        .filters .btns { display: flex; gap: 8px; align-items: end; }
        .btn {
            border: 1px solid #3f5185; background: linear-gradient(180deg, #23325c, #1b284a);
            color: #e4ecff; border-radius: 10px; padding: 10px 12px; text-decoration: none; cursor: pointer;
        }
        .btn.secondary { border-color: #3a4568; background: #18213b; color: #ced9ff; }
        .cards {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .card {
            background: linear-gradient(180deg, #18213a, #141c32);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
        }
        .card .label { font-size: 12px; color: var(--muted); margin-bottom: 5px; }
        .card .value { font-size: 24px; font-weight: 700; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1180px; }
        th, td { padding: 11px 10px; border-bottom: 1px solid #2a3558; text-align: left; vertical-align: top; }
        th { font-size: 12px; color: #a8b6df; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; }
        td { color: #e4ebff; font-size: 14px; }
        .muted { color: var(--muted); font-size: 12px; }
        .badge {
            display: inline-flex; align-items: center; border: 1px solid transparent; border-radius: 999px;
            padding: 3px 8px; font-size: 11px; font-weight: 700;
        }
        .actions { display: inline-flex; gap: 8px; }
        .actions a {
            font-size: 12px; text-decoration: none; color: #d2ddff; padding: 6px 10px;
            border: 1px solid #3b4a79; border-radius: 8px; background: #1a2441;
        }
        .empty { text-align: center; padding: 28px 14px; color: var(--muted); }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 12px; color: var(--muted); font-size: 13px; }
        .pagination .pages { display: flex; gap: 8px; }
        .pagination a { text-decoration: none; color: #d8e2ff; border: 1px solid #3a4a79; padding: 7px 10px; border-radius: 8px; background: #17213d; }
        @media (max-width: 1200px) {
            .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1>Seller Order Management</h1>
            <div class="sub">Manage orders you received and keep fulfillment on track.</div>
        </div>
        <div class="top-actions">
            <a href="/seller/apply.php">Seller Hub</a>
        </div>
    </div>

    <form class="panel filters" method="get" action="">
        <div class="field">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= bv_member_h($value) ?>"<?= $statusFilter === $value ? ' selected' : '' ?>><?= bv_member_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="period">Period</label>
            <select name="period" id="period">
                <?php foreach ($periodOptions as $value => $label): ?>
                    <option value="<?= bv_member_h($value) ?>"<?= $periodFilter === $value ? ' selected' : '' ?>><?= bv_member_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="search_by">Search by</label>
            <select name="search_by" id="search_by">
                <?php foreach ($searchByOptions as $value => $label): ?>
                    <option value="<?= bv_member_h($value) ?>"<?= $searchBy === $value ? ' selected' : '' ?>><?= bv_member_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="keyword">Keyword</label>
            <input type="text" id="keyword" name="keyword" value="<?= bv_member_h($keyword) ?>" placeholder="Type to search...">
        </div>
        <div class="btns">
            <button class="btn" type="submit">Apply Filters</button>
            <a class="btn secondary" href="/seller/orders.php">Reset</a>
        </div>
    </form>

    <div class="cards">
        <div class="card"><div class="label">Awaiting Payment</div><div class="value"><?= (int)($summary['awaiting_payment_count'] ?? 0) ?></div></div>
        <div class="card"><div class="label">Paid / To Process</div><div class="value"><?= (int)($summary['paid_to_process_count'] ?? 0) ?></div></div>
        <div class="card"><div class="label">Awaiting Shipment</div><div class="value"><?= (int)($summary['awaiting_shipment_count'] ?? 0) ?></div></div>
        <div class="card"><div class="label">Shipped</div><div class="value"><?= (int)($summary['shipped_count'] ?? 0) ?></div></div>
        <div class="card"><div class="label">Completed</div><div class="value"><?= (int)($summary['completed_count'] ?? 0) ?></div></div>
        <div class="card"><div class="label">Cancelled / Refunded</div><div class="value"><?= (int)($summary['cancelled_refunded_count'] ?? 0) ?></div></div>
    </div>

    <div class="panel table-wrap">
        <table>
            <thead>
            <tr>
                <th>Order</th>
                <th>Buyer</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Subtotal</th>
                <th>Total</th>
                <th>Date Sold</th>
                <th>Paid At</th>
                <th>Order Status</th>
                <th>Payment Status</th>
                <th>Shipping Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="12" class="empty">No orders matched your current filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $orderId = (int)($row['order_id'] ?? 0);
                    $currency = (string)($row['currency_code'] ?? 'USD');
                    $orderBadge = seller_orders_status_badge((string)($row['order_status'] ?? ''));
                    $paymentBadge = seller_orders_status_badge((string)($row['payment_status'] ?? ''));
                    $shippingBadge = seller_orders_status_badge((string)($row['shipping_status'] ?? ''));
                    $viewUrl = $viewBase . '?' . http_build_query(['id' => $orderId]);
                    $manageUrl = $manageBase . '?' . http_build_query(['id' => $orderId, 'mode' => 'manage']);
                    ?>
                    <tr>
                        <td>
                            <div><strong>#<?= bv_member_h((string)($row['order_code'] ?? $orderId)) ?></strong></div>
                            <div class="muted">ID: <?= $orderId ?></div>
                        </td>
                        <td><?= bv_member_h((string)($row['buyer_display'] ?? 'Customer')) ?></td>
                        <td><?= bv_member_h((string)($row['item_title'] ?? '—')) ?></td>
                        <td><?= (int)($row['qty_total'] ?? 0) ?></td>
                        <td><?= bv_member_h(seller_orders_money((float)($row['subtotal_amount'] ?? 0), $currency)) ?></td>
                        <td><?= bv_member_h(seller_orders_money((float)($row['total_amount'] ?? 0), $currency)) ?></td>
                       <td>
                            <?php if (!empty($row['date_sold'])): ?>
                                <?php try { ?>
                                    <?= bv_member_h((new DateTimeImmutable((string)$row['date_sold']))->format('Y-m-d H:i')) ?>
                                <?php } catch (Throwable $e) { ?>
                                    <span class="muted">—</span>
                                <?php } ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['paid_at'])): ?>
                                <?php try { ?>
                                    <?= bv_member_h((new DateTimeImmutable((string)$row['paid_at']))->format('Y-m-d H:i')) ?>
                                <?php } catch (Throwable $e) { ?>
                                    <span class="muted">—</span>
                                <?php } ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?> 
                        </td>
                        <td><span class="badge" style="<?= bv_member_h(str_replace(';', ';', str_replace(':', ':', str_replace(',', ';', $orderBadge['style'])))) ?>"><?= bv_member_h($orderBadge['label']) ?></span></td>
                        <td><span class="badge" style="<?= bv_member_h(str_replace(';', ';', str_replace(':', ':', str_replace(',', ';', $paymentBadge['style'])))) ?>"><?= bv_member_h($paymentBadge['label']) ?></span></td>
                        <td><span class="badge" style="<?= bv_member_h(str_replace(';', ';', str_replace(':', ':', str_replace(',', ';', $shippingBadge['style'])))) ?>"><?= bv_member_h($shippingBadge['label']) ?></span></td>
                        <td>
                            <div class="actions">
                                <a href="<?= bv_member_h($viewUrl) ?>">View</a>
                                <a href="<?= bv_member_h($manageUrl) ?>">Manage</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <div>Showing <?= count($rows) ?> of <?= $totalRows ?> order(s).</div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?<?= bv_member_h(http_build_query(array_merge($queryBase, ['page' => $page - 1]))) ?>">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= bv_member_h(http_build_query(array_merge($queryBase, ['page' => $page + 1]))) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>