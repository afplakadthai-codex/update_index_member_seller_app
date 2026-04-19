<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$root = dirname(__DIR__);

require_once $root . '/includes/listing_offers.php';

$headFile = $root . '/includes/head.php';
$menuFile = $root . '/includes/menu.php';

$guardCandidates = [
    __DIR__ . '/_guard.php',
    __DIR__ . '/guard.php',
    $root . '/includes/auth.php',
    $root . '/includes/auth_bootstrap.php',
];

foreach ($guardCandidates as $guardFile) {
    if (is_file($guardFile)) {
        require_once $guardFile;
    }
}

if (!function_exists('bv_seller_offers_h')) {
    function bv_seller_offers_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_seller_offers_csrf_token')) {
    function bv_seller_offers_csrf_token(string $scope = 'seller_offers_actions'): string
    {
        if (empty($_SESSION['_csrf_seller_offers'][$scope]) || !is_string($_SESSION['_csrf_seller_offers'][$scope])) {
            $_SESSION['_csrf_seller_offers'][$scope] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_seller_offers'][$scope];
    }
}

if (!function_exists('bv_seller_offers_verify_csrf')) {
    function bv_seller_offers_verify_csrf(?string $token, string $scope = 'seller_offers_actions'): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION['_csrf_seller_offers'][$scope] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('bv_seller_offers_flash_set')) {
    function bv_seller_offers_flash_set(string $status, string $message): void
    {
        $_SESSION['seller_offers_flash'] = [
            'status' => $status,
            'message' => $message,
        ];
    }
}

if (!function_exists('bv_seller_offers_flash_get')) {
    function bv_seller_offers_flash_get(): array
    {
        $flash = $_SESSION['seller_offers_flash'] ?? [];
        unset($_SESSION['seller_offers_flash']);

        return is_array($flash) ? $flash : [];
    }
}

if (!function_exists('bv_seller_offers_redirect')) {
    function bv_seller_offers_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('bv_seller_offers_url')) {
    function bv_seller_offers_url(array $extra = []): string
    {
        $base = '/seller/offers.php';
        if (empty($extra)) {
            return $base;
        }

        return $base . '?' . http_build_query($extra);
    }
}

if (!function_exists('bv_seller_offer_page_status_badge')) {
    function bv_seller_offer_page_status_badge(string $status): array
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'open':
                return ['Open', '#1d4ed8', '#dbeafe'];
            case 'seller_accepted':
                return ['Seller Accepted', '#7c3aed', '#ede9fe'];
            case 'buyer_checkout_ready':
                return ['Checkout Ready', '#166534', '#dcfce7'];
            case 'completed':
                return ['Completed', '#065f46', '#d1fae5'];
            case 'expired':
                return ['Expired', '#92400e', '#fef3c7'];
            case 'cancelled':
                return ['Cancelled', '#991b1b', '#fee2e2'];
            case 'rejected':
                return ['Rejected', '#b91c1c', '#fee2e2'];
            default:
                return [ucfirst($status !== '' ? $status : 'Unknown'), '#374151', '#e5e7eb'];
        }
    }
}

if (!function_exists('bv_seller_offer_format_money')) {
    function bv_seller_offer_format_money($amount, ?string $currency = null): string
    {
        $currency = strtoupper(trim((string) ($currency ?: 'USD')));

        if (!is_numeric($amount)) {
            return $currency . ' 0.00';
        }

        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('bv_seller_offer_time_ago')) {
    function bv_seller_offer_time_ago(?string $datetime): string
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return '-';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 2592000) {
            $d = (int) floor($diff / 86400);
            return $d . ' day' . ($d !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 31536000) {
            $mo = (int) floor($diff / 2592000);
            return $mo . ' month' . ($mo !== 1 ? 's' : '') . ' ago';
        }

        $y = (int) floor($diff / 31536000);
        return $y . ' year' . ($y !== 1 ? 's' : '') . ' ago';
    }
}

if (!function_exists('bv_seller_offer_listing_url')) {
    function bv_seller_offer_listing_url(array $listing): string
    {
        $slug = trim((string) ($listing['slug'] ?? ''));
        $id = (int) ($listing['id'] ?? 0);

        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }

        return '/listing.php?id=' . $id;
    }
}

if (!function_exists('bv_seller_offer_image_url')) {
    function bv_seller_offer_image_url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '/images/placeholder-fish.jpg';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        $normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');
        return preg_replace('~/+~', '/', $normalized) ?: '/images/placeholder-fish.jpg';
    }
}

if (!function_exists('bv_seller_offer_guess_listing_title')) {
    function bv_seller_offer_guess_listing_title(array $row): string
    {
        $title = trim((string) ($row['listing_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'Listing #' . (int) ($row['listing_id'] ?? 0);
    }
}

if (!function_exists('bv_seller_offer_is_new_activity')) {
    function bv_seller_offer_is_new_activity(?string $datetime, int $minutes = 60): bool
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return false;
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return false;
        }

        return $ts >= (time() - ($minutes * 60));
    }
}

if (!function_exists('bv_seller_offer_current_seller_id')) {
    function bv_seller_offer_current_seller_id(): int
    {
        return bv_offer_current_user_id();
    }
}

if (!function_exists('bv_seller_offer_current_role')) {
    function bv_seller_offer_current_role(): string
    {
        return bv_offer_current_user_role();
    }
}

if (!function_exists('bv_seller_offer_can_access_page')) {
    function bv_seller_offer_can_access_page(): bool
    {
        return bv_offer_is_seller_role(bv_seller_offer_current_role());
    }
}

if (!function_exists('bv_seller_offer_build_filters')) {
    function bv_seller_offer_build_filters(): array
    {
        $allowedStatuses = array_merge(['all'], bv_offer_allowed_statuses());
        $status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = strtolower(trim((string) ($_GET['sort'] ?? 'last_message_desc')));
        $allowedSorts = ['last_message_desc', 'created_desc', 'price_desc', 'price_asc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'last_message_desc';
        }

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 12;

        return [
            'status' => $status,
            'q' => $q,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}

if (!function_exists('bv_seller_offer_sort_sql')) {
    function bv_seller_offer_sort_sql(string $sort): string
    {
        switch ($sort) {
            case 'created_desc':
                return 'o.created_at DESC, o.id DESC';
            case 'price_desc':
                return 'COALESCE(o.agreed_price, o.latest_offer_price, 0) DESC, o.last_message_at DESC, o.id DESC';
            case 'price_asc':
                return 'COALESCE(o.agreed_price, o.latest_offer_price, 0) ASC, o.last_message_at DESC, o.id DESC';
            case 'last_message_desc':
            default:
                return 'o.last_message_at DESC, o.id DESC';
        }
    }
}

if (!function_exists('bv_seller_offer_fetch_rows')) {
    function bv_seller_offer_fetch_rows(int $sellerUserId, array $filters): array
    {
        $params = [$sellerUserId];
        $where = ['o.seller_user_id = ?'];

        if ($filters['status'] !== 'all') {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }

        if ($filters['q'] !== '') {
            $where[] = '('
                . 'l.title LIKE ? OR '
                . 'buyer.first_name LIKE ? OR '
                . 'buyer.last_name LIKE ? OR '
                . 'buyer.email LIKE ? OR '
                . 'CAST(o.id AS CHAR) LIKE ?'
                . ')';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $orderSql = bv_seller_offer_sort_sql($filters['sort']);
        $offset = ($filters['page'] - 1) * $filters['per_page'];

        $sql = "
            SELECT
                o.*,
                l.id AS listing_real_id,
                l.slug AS listing_slug,
                l.title AS listing_title,
                l.cover_image AS listing_cover_image,
                l.price AS listing_price,
                l.currency AS listing_currency,
                l.status AS listing_status,
                l.sale_status AS listing_sale_status,
                buyer.first_name AS buyer_first_name,
                buyer.last_name AS buyer_last_name,
                buyer.email AS buyer_email
            FROM listing_offers o
            LEFT JOIN listings l ON l.id = o.listing_id
            LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderSql}
            LIMIT " . (int) $filters['per_page'] . " OFFSET " . (int) $offset;

        return bv_listing_offers_query_all($sql, $params);
    }
}

if (!function_exists('bv_seller_offer_fetch_count')) {
    function bv_seller_offer_fetch_count(int $sellerUserId, array $filters): int
    {
        $params = [$sellerUserId];
        $where = ['o.seller_user_id = ?'];

        if ($filters['status'] !== 'all') {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        }

        if ($filters['q'] !== '') {
            $where[] = '('
                . 'l.title LIKE ? OR '
                . 'buyer.first_name LIKE ? OR '
                . 'buyer.last_name LIKE ? OR '
                . 'buyer.email LIKE ? OR '
                . 'CAST(o.id AS CHAR) LIKE ?'
                . ')';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "
            SELECT COUNT(*) AS total_count
            FROM listing_offers o
            LEFT JOIN listings l ON l.id = o.listing_id
            LEFT JOIN users buyer ON buyer.id = o.buyer_user_id
            WHERE " . implode(' AND ', $where);

        $row = bv_listing_offers_query_one($sql, $params);

        return (int) ($row['total_count'] ?? 0);
    }
}

if (!function_exists('bv_seller_offer_fetch_status_counts')) {
    function bv_seller_offer_fetch_status_counts(int $sellerUserId): array
    {
        $rows = bv_listing_offers_query_all(
            "SELECT status, COUNT(*) AS total_count
             FROM listing_offers
             WHERE seller_user_id = ?
             GROUP BY status",
            [$sellerUserId]
        );

        $counts = ['all' => 0];
        foreach (bv_offer_allowed_statuses() as $status) {
            $counts[$status] = 0;
        }

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $count = (int) ($row['total_count'] ?? 0);

            if ($status !== '') {
                $counts[$status] = $count;
                $counts['all'] += $count;
            }
        }

        return $counts;
    }
}

if (!function_exists('bv_seller_offer_buyer_name')) {
    function bv_seller_offer_buyer_name(array $row): string
    {
        $name = trim((string) (($row['buyer_first_name'] ?? '') . ' ' . ($row['buyer_last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($row['buyer_email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'Buyer #' . (int) ($row['buyer_user_id'] ?? 0);
    }
}

if (!function_exists('bv_seller_offer_build_pagination_url')) {
    function bv_seller_offer_build_pagination_url(array $filters, int $page): string
    {
        $params = [
            'status' => $filters['status'],
            'q' => $filters['q'],
            'sort' => $filters['sort'],
            'page' => max(1, $page),
        ];

        if ($params['status'] === 'all') {
            unset($params['status']);
        }
        if ($params['q'] === '') {
            unset($params['q']);
        }
        if ($params['sort'] === 'last_message_desc') {
            unset($params['sort']);
        }
        if ($params['page'] === 1) {
            unset($params['page']);
        }

        return bv_seller_offers_url($params);
    }
}

if (!function_exists('bv_seller_offer_filter_url')) {
    function bv_seller_offer_filter_url(array $filters, array $changes = []): string
    {
        $params = [
            'status' => $filters['status'],
            'q' => $filters['q'],
            'sort' => $filters['sort'],
            'page' => 1,
        ];

        foreach ($changes as $k => $v) {
            $params[$k] = $v;
        }

        if (($params['status'] ?? 'all') === 'all') {
            unset($params['status']);
        }
        if (($params['q'] ?? '') === '') {
            unset($params['q']);
        }
        if (($params['sort'] ?? 'last_message_desc') === 'last_message_desc') {
            unset($params['sort']);
        }
        if (($params['page'] ?? 1) === 1) {
            unset($params['page']);
        }

        return bv_seller_offers_url($params);
    }
}

if (!function_exists('bv_seller_offer_fetch_latest_open_message')) {
    function bv_seller_offer_fetch_latest_open_message(int $offerId): ?array
    {
        return bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offer_messages
             WHERE offer_id = ?
               AND message_type IN ('offer', 'counter')
               AND offer_price IS NOT NULL
               AND offer_price > 0
             ORDER BY id DESC
             LIMIT 1",
            [$offerId]
        );
    }
}

if (!function_exists('bv_seller_offer_resolve_accept_price')) {
    function bv_seller_offer_resolve_accept_price(array $offer): float
    {
        $latest = bv_seller_offer_fetch_latest_open_message((int) ($offer['id'] ?? 0));
        if (!empty($latest['offer_price']) && is_numeric($latest['offer_price'])) {
            return round((float) $latest['offer_price'], 2);
        }

        if (!empty($offer['latest_offer_price']) && is_numeric($offer['latest_offer_price'])) {
            return round((float) $offer['latest_offer_price'], 2);
        }

        return 0.0;
    }
}

if (!bv_listing_offers_require_tables()) {
    http_response_code(500);
    echo 'Offer tables are not ready yet.';
    exit;
}

$currentUserId = bv_seller_offer_current_seller_id();
$currentUserRole = bv_seller_offer_current_role();

if ($currentUserId <= 0) {
    bv_seller_offers_flash_set('error', 'Please log in to continue.');
    bv_seller_offers_redirect('/login.php?redirect=' . rawurlencode('/seller/offers.php'));
}

if (!bv_seller_offer_can_access_page()) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));
    $offerId = isset($_POST['offer_id']) && is_numeric($_POST['offer_id']) ? (int) $_POST['offer_id'] : 0;
    $returnUrl = trim((string) ($_POST['return_url'] ?? '/seller/offers.php'));

    if ($returnUrl === '' || preg_match('/^https?:\/\//i', $returnUrl) || stripos($returnUrl, 'javascript:') === 0) {
        $returnUrl = '/seller/offers.php';
    }

    if (!bv_seller_offers_verify_csrf($csrfToken, 'seller_offers_actions')) {
        bv_seller_offers_flash_set('error', 'Security token mismatch. Please refresh and try again.');
        bv_seller_offers_redirect($returnUrl);
    }

    if ($offerId <= 0) {
        bv_seller_offers_flash_set('error', 'Invalid offer ID.');
        bv_seller_offers_redirect($returnUrl);
    }

    $offer = bv_offer_get_by_id($offerId);
    if (!$offer) {
        bv_seller_offers_flash_set('error', 'Offer not found.');
        bv_seller_offers_redirect($returnUrl);
    }

    if (!bv_offer_current_user_can_seller_manage($offer, $currentUserId, $currentUserRole)) {
        bv_seller_offers_flash_set('error', 'You do not have permission to manage this offer.');
        bv_seller_offers_redirect($returnUrl);
    }

    try {
        if ($action === 'quick_accept') {
            if (strtolower(trim((string) ($offer['status'] ?? ''))) !== 'open') {
                throw new RuntimeException('Only open offers can be accepted.');
            }

            $agreedPrice = bv_seller_offer_resolve_accept_price($offer);
            if ($agreedPrice <= 0) {
                throw new RuntimeException('No valid offer price found to accept.');
            }

            $approvedMessageId = null;
            $latestMsg = bv_seller_offer_fetch_latest_open_message((int) $offer['id']);
            if ($latestMsg && !empty($latestMsg['id'])) {
                $approvedMessageId = (int) $latestMsg['id'];
            }

            $result = bv_offer_accept(
                (int) $offer['id'],
                $agreedPrice,
                $approvedMessageId,
                $currentUserId,
                $currentUserRole,
                'Offer accepted from seller dashboard quick action.'
            );

            if (!$result || empty($result['offer'])) {
                throw new RuntimeException('Unable to accept offer.');
            }

            bv_seller_offers_flash_set('success', 'Offer accepted successfully. Checkout token has been prepared for the buyer.');
            bv_seller_offers_redirect($returnUrl);
        }

        if ($action === 'quick_reject') {
            if (strtolower(trim((string) ($offer['status'] ?? ''))) !== 'open') {
                throw new RuntimeException('Only open offers can be rejected.');
            }

            $ok = bv_offer_reject(
                (int) $offer['id'],
                $currentUserId,
                $currentUserRole,
                'Offer rejected from seller dashboard quick action.'
            );

            if (!$ok) {
                throw new RuntimeException('Unable to reject offer.');
            }

            bv_seller_offers_flash_set('success', 'Offer rejected successfully.');
            bv_seller_offers_redirect($returnUrl);
        }

        throw new RuntimeException('Invalid action.');
    } catch (Throwable $e) {
        bv_seller_offers_flash_set('error', 'Action failed: ' . $e->getMessage());
        bv_seller_offers_redirect($returnUrl);
    }
}

$filters = bv_seller_offer_build_filters();
$statusCounts = bv_seller_offer_fetch_status_counts($currentUserId);
$totalOffers = (int) ($statusCounts['all'] ?? 0);
$rows = bv_seller_offer_fetch_rows($currentUserId, $filters);
$totalFiltered = bv_seller_offer_fetch_count($currentUserId, $filters);

$totalPages = max(1, (int) ceil($totalFiltered / max(1, $filters['per_page'])));
if ($filters['page'] > $totalPages) {
    $filters['page'] = $totalPages;
    $rows = bv_seller_offer_fetch_rows($currentUserId, $filters);
}

$flash = bv_seller_offers_flash_get();
$flashStatus = (string) ($flash['status'] ?? '');
$flashMessage = (string) ($flash['message'] ?? '');
$csrfToken = bv_seller_offers_csrf_token('seller_offers_actions');

$openCount = (int) ($statusCounts['open'] ?? 0);
$acceptedCount = (int) (($statusCounts['seller_accepted'] ?? 0) + ($statusCounts['buyer_checkout_ready'] ?? 0));
$completedCount = (int) ($statusCounts['completed'] ?? 0);
$closedCount = (int) (($statusCounts['expired'] ?? 0) + ($statusCounts['cancelled'] ?? 0) + ($statusCounts['rejected'] ?? 0));

$pageTitle = 'Seller Offers | Bettavaro';
$metaDescription = 'Manage buyer offers, pricing conversations, and deal status from your Bettavaro seller dashboard.';
$canonicalUrl = '';
$ogImage = '';
$bodyClass = 'seller-offers-page';

if (is_file($headFile)) {
    include $headFile;
}
?>
<style>
:root{
    --so-bg:#05100c;
    --so-bg-2:#081712;
    --so-card:rgba(255,255,255,.96);
    --so-ink:#0f172a;
    --so-line:#dbe4ea;
    --so-gold:#d7bc6b;
    --so-green:#166534;
    --so-green-soft:#ecfdf3;
    --so-blue:#1d4ed8;
    --so-blue-soft:#dbeafe;
    --so-red:#b91c1c;
    --so-red-soft:#fee2e2;
    --so-slate:#64748b;
    --so-shadow:0 24px 72px rgba(0,0,0,.18);
}
body{
    background:
        radial-gradient(circle at top, rgba(18,71,47,.26), transparent 30%),
        linear-gradient(180deg,#05100b 0%, #06120d 45%, #040906 100%);
}
.so-wrap{
    max-width:1380px;
    margin:0 auto;
    padding:28px 16px 90px;
}
.so-topbar{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:16px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
    color:#d6e4dc;
}
.so-shell{
    display:grid;
    gap:20px;
}
.so-hero{
    background:linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.96));
    border-radius:26px;
    border:1px solid rgba(255,255,255,.4);
    box-shadow:var(--so-shadow);
    overflow:hidden;
    color:var(--so-ink);
}
.so-hero-body{
    padding:26px;
}
.so-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin:0 0 12px;
    color:#9a6b00;
    font-size:12px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}
.so-eyebrow::before{
    content:"";
    display:block;
    width:18px;
    height:2px;
    border-radius:999px;
    background:linear-gradient(90deg, transparent, var(--so-gold));
}
.so-title{
    margin:0;
    font-size:46px;
    line-height:1.02;
    letter-spacing:-.035em;
    color:#0f172a;
}
.so-subtitle{
    max-width:820px;
    margin:12px 0 0;
    color:#475569;
    font-size:16px;
    line-height:1.75;
}
.so-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-top:22px;
}
.so-kpi{
    border:1px solid var(--so-line);
    border-radius:18px;
    padding:16px 16px 15px;
    background:linear-gradient(180deg,#fff,#f8fafc);
}
.so-kpi strong{
    display:block;
    font-size:28px;
    line-height:1;
    color:#0f172a;
}
.so-kpi span{
    display:block;
    margin-top:8px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#64748b;
    font-weight:900;
}
.so-flash{
    padding:14px 16px;
    border-radius:16px;
    font-weight:800;
}
.so-flash-success{
    background:#dcfce7;
    color:#166534;
}
.so-flash-error{
    background:#fee2e2;
    color:#991b1b;
}
.so-panel{
    background:rgba(255,255,255,.97);
    border-radius:24px;
    border:1px solid rgba(255,255,255,.35);
    box-shadow:var(--so-shadow);
    overflow:hidden;
    color:var(--so-ink);
}
.so-panel-body{
    padding:22px;
}
.so-filter-grid{
    display:grid;
    grid-template-columns:1.2fr .72fr .56fr auto;
    gap:12px;
    align-items:end;
}
.so-field{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.so-field label{
    font-size:13px;
    font-weight:800;
    color:#334155;
}
.so-field input,
.so-field select{
    width:100%;
    min-height:48px;
    border:1px solid #cfd8e3;
    border-radius:14px;
    padding:12px 14px;
    font:inherit;
    background:#fff;
    color:#0f172a;
}
.so-btn,
.so-btn-ghost,
.so-btn-danger{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:48px;
    padding:0 16px;
    border-radius:14px;
    border:none;
    cursor:pointer;
    text-decoration:none;
    font-weight:900;
    transition:transform .18s ease, box-shadow .18s ease, opacity .18s ease;
}
.so-btn:hover,
.so-btn-ghost:hover,
.so-btn-danger:hover{
    transform:translateY(-1px);
}
.so-btn{
    background:#253726;
    color:#fff;
    box-shadow:0 10px 24px rgba(37,55,38,.18);
}
.so-btn-ghost{
    background:#fff;
    color:#253726;
    border:1px solid #253726;
}
.so-btn-danger{
    background:#fff1f2;
    color:#b91c1c;
    border:1px solid #fecdd3;
}
.so-tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.so-tab{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:999px;
    text-decoration:none;
    border:1px solid #d8e0e8;
    background:#fff;
    color:#334155;
    font-weight:900;
    font-size:13px;
}
.so-tab.is-active{
    background:#253726;
    color:#fff;
    border-color:#253726;
}
.so-tab-count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:22px;
    height:22px;
    padding:0 8px;
    border-radius:999px;
    background:rgba(15,23,42,.08);
    font-size:12px;
}
.so-tab.is-active .so-tab-count{
    background:rgba(255,255,255,.14);
}
.so-list{
    display:grid;
    gap:16px;
}
.so-empty{
    border:1px dashed #cbd5e1;
    border-radius:18px;
    padding:26px;
    background:#fff;
    color:#64748b;
    line-height:1.8;
}
.so-card{
    display:grid;
    grid-template-columns:148px minmax(0,1fr);
    gap:18px;
    border:1px solid var(--so-line);
    border-radius:22px;
    padding:16px;
    background:linear-gradient(180deg,#fff,#f8fafc);
}
.so-thumb{
    position:relative;
    overflow:hidden;
    border-radius:18px;
    background:#e5e7eb;
    aspect-ratio:1/1;
}
.so-thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}
.so-new-badge{
    position:absolute;
    left:10px;
    top:10px;
    display:inline-flex;
    align-items:center;
    padding:7px 10px;
    border-radius:999px;
    background:#dcfce7;
    color:#166534;
    font-size:11px;
    font-weight:900;
    letter-spacing:.05em;
    text-transform:uppercase;
}
.so-card-body{
    display:grid;
    gap:14px;
}
.so-card-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
}
.so-card-title{
    margin:0;
    font-size:26px;
    line-height:1.08;
    letter-spacing:-.025em;
    color:#0f172a;
}
.so-card-title a{
    color:inherit;
    text-decoration:none;
}
.so-card-title a:hover{
    text-decoration:underline;
}
.so-card-sub{
    margin-top:7px;
    color:#64748b;
    font-size:14px;
}
.so-status{
    display:inline-flex;
    align-items:center;
    padding:9px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    letter-spacing:.04em;
    white-space:nowrap;
}
.so-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.so-stat{
    border:1px solid #dbe4ea;
    border-radius:16px;
    padding:14px;
    background:#fff;
}
.so-stat-label{
    display:block;
    margin-bottom:7px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#64748b;
    font-weight:900;
}
.so-stat-value{
    display:block;
    font-size:18px;
    font-weight:900;
    color:#0f172a;
}
.so-stat-muted{
    margin-top:6px;
    display:block;
    font-size:12px;
    color:#64748b;
}
.so-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}
.so-inline-form{
    display:inline;
}
.so-open-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:0 16px;
    border-radius:14px;
    background:#253726;
    color:#fff;
    font-weight:900;
    text-decoration:none;
    box-shadow:0 10px 24px rgba(37,55,38,.18);
}
.so-quick-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:0 14px;
    border-radius:14px;
    font-weight:900;
    border:none;
    cursor:pointer;
}
.so-quick-accept{
    background:#ecfdf3;
    color:#166534;
    border:1px solid #86efac;
}
.so-quick-reject{
    background:#fff1f2;
    color:#b91c1c;
    border:1px solid #fecdd3;
}
.so-note{
    color:#64748b;
    font-size:13px;
    line-height:1.75;
}
.so-pagination{
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:6px;
}
.so-page{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:44px;
    height:44px;
    padding:0 12px;
    border-radius:12px;
    border:1px solid #d8e0e8;
    background:#fff;
    color:#334155;
    font-weight:900;
    text-decoration:none;
}
.so-page.is-current{
    background:#253726;
    color:#fff;
    border-color:#253726;
}
@media (max-width: 1100px){
    .so-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .so-filter-grid{grid-template-columns:1fr 1fr}
    .so-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 820px){
    .so-title{font-size:36px}
    .so-card{grid-template-columns:1fr}
    .so-thumb{aspect-ratio:16/10}
    .so-filter-grid{grid-template-columns:1fr}
}
@media (max-width: 640px){
    .so-wrap{padding:20px 12px 84px}
    .so-kpis{grid-template-columns:1fr}
    .so-grid{grid-template-columns:1fr}
    .so-card-title{font-size:22px}
}
</style>
<?php if (is_file($menuFile)) { include $menuFile; } ?>

<div class="so-wrap">
    <div class="so-topbar">Seller dashboard / Offer inbox / Production mode</div>

    <div class="so-shell">
        <section class="so-hero">
            <div class="so-hero-body">
                <div class="so-eyebrow">Seller Offers</div>
                <h1 class="so-title">Manage every negotiation from one clean desk.</h1>
                <p class="so-subtitle">
                    Review incoming offers, see buyer activity fast, and move winning deals straight into checkout without digging through a fish market of tabs.
                </p>

                <div class="so-kpis">
                    <div class="so-kpi">
                        <strong><?= number_format($totalOffers) ?></strong>
                        <span>Total Offer Threads</span>
                    </div>
                    <div class="so-kpi">
                        <strong><?= number_format($openCount) ?></strong>
                        <span>Open Negotiations</span>
                    </div>
                    <div class="so-kpi">
                        <strong><?= number_format($acceptedCount) ?></strong>
                        <span>Accepted / Checkout Ready</span>
                    </div>
                    <div class="so-kpi">
                        <strong><?= number_format($completedCount) ?></strong>
                        <span>Completed Deals</span>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($flashMessage !== ''): ?>
            <div class="so-flash <?= $flashStatus === 'success' ? 'so-flash-success' : 'so-flash-error' ?>">
                <?= bv_seller_offers_h($flashMessage) ?>
            </div>
        <?php endif; ?>

        <section class="so-panel">
            <div class="so-panel-body">
                <form method="get" action="/seller/offers.php" class="so-filter-grid">
                    <div class="so-field">
                        <label for="q">Search</label>
                        <input type="text" id="q" name="q" value="<?= bv_seller_offers_h($filters['q']) ?>" placeholder="Listing, buyer, email, or offer ID">
                    </div>

                    <div class="so-field">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort">
                            <option value="last_message_desc"<?= $filters['sort'] === 'last_message_desc' ? ' selected' : '' ?>>Latest activity</option>
                            <option value="created_desc"<?= $filters['sort'] === 'created_desc' ? ' selected' : '' ?>>Newest thread</option>
                            <option value="price_desc"<?= $filters['sort'] === 'price_desc' ? ' selected' : '' ?>>Highest offer price</option>
                            <option value="price_asc"<?= $filters['sort'] === 'price_asc' ? ' selected' : '' ?>>Lowest offer price</option>
                        </select>
                    </div>

                    <div class="so-field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all"<?= $filters['status'] === 'all' ? ' selected' : '' ?>>All statuses</option>
                            <?php foreach (bv_offer_allowed_statuses() as $statusOption): ?>
                                <?php [$labelTmp] = bv_seller_offer_page_status_badge($statusOption); ?>
                                <option value="<?= bv_seller_offers_h($statusOption) ?>"<?= $filters['status'] === $statusOption ? ' selected' : '' ?>>
                                    <?= bv_seller_offers_h($labelTmp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="so-actions">
                        <button type="submit" class="so-btn">Apply Filters</button>
                        <a href="/seller/offers.php" class="so-btn-ghost">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="so-panel">
            <div class="so-panel-body">
                <div class="so-tabs">
                    <?php
                    $tabOrder = [
                        'all' => 'All',
                        'open' => 'Open',
                        'seller_accepted' => 'Seller Accepted',
                        'buyer_checkout_ready' => 'Checkout Ready',
                        'completed' => 'Completed',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ];
                    ?>
                    <?php foreach ($tabOrder as $tabKey => $tabLabel): ?>
                        <a
                            href="<?= bv_seller_offers_h(bv_seller_offer_filter_url($filters, ['status' => $tabKey])) ?>"
                            class="so-tab<?= $filters['status'] === $tabKey ? ' is-active' : '' ?>"
                        >
                            <span><?= bv_seller_offers_h($tabLabel) ?></span>
                            <span class="so-tab-count"><?= number_format((int) ($statusCounts[$tabKey] ?? 0)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="so-panel">
            <div class="so-panel-body">
                <?php if (empty($rows)): ?>
                    <div class="so-empty">
                        <strong style="display:block;margin-bottom:8px;color:#0f172a;">No offers found.</strong>
                        There are no matching offer threads for the current filters yet. That is either peaceful... or suspiciously quiet.
                    </div>
                <?php else: ?>
                    <div class="so-list">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $offerId = (int) ($row['id'] ?? 0);
                            $listingTitle = bv_seller_offer_guess_listing_title($row);
                            $listingImage = bv_seller_offer_image_url($row['listing_cover_image'] ?? '');
                            $listingCurrency = (string) ($row['currency'] ?? $row['listing_currency'] ?? 'USD');
                            $listingPrice = $row['listing_price'] ?? $row['listing_price_snapshot'] ?? 0;
                            $latestOfferPrice = $row['latest_offer_price'] ?? 0;
                            $agreedPrice = $row['agreed_price'] ?? 0;
                            $buyerName = bv_seller_offer_buyer_name($row);
                            $status = (string) ($row['status'] ?? '');
                            [$statusLabel, $statusColor, $statusBg] = bv_seller_offer_page_status_badge($status);
                            $isTerminal = bv_offer_is_terminal_status($status);
                            $isOpen = strtolower(trim($status)) === 'open';
                            $hasFresh = bv_seller_offer_is_new_activity((string) ($row['last_message_at'] ?? ''), 60);
                            $listingUrl = '#';
                            if (!empty($row['listing_real_id'])) {
                                $listingUrl = bv_seller_offer_listing_url([
                                    'id' => (int) $row['listing_real_id'],
                                    'slug' => (string) ($row['listing_slug'] ?? ''),
                                ]);
                            }
                            $threadUrl = '/offer.php?id=' . $offerId;
                            $lastMessageAt = (string) ($row['last_message_at'] ?? '');
                            $createdAt = (string) ($row['created_at'] ?? '');
                            $activeToken = bv_offer_get_active_checkout_token($offerId);
                            $tokenExpiresAt = trim((string) ($activeToken['expires_at'] ?? ''));
                            $canQuickAccept = $isOpen && (float) $latestOfferPrice > 0;
                            $canQuickReject = $isOpen;
                            ?>
                            <article class="so-card">
                                <div class="so-thumb">
                                    <img src="<?= bv_seller_offers_h($listingImage) ?>" alt="<?= bv_seller_offers_h($listingTitle) ?>">
                                    <?php if ($hasFresh): ?>
                                        <span class="so-new-badge">Fresh activity</span>
                                    <?php endif; ?>
                                </div>

                                <div class="so-card-body">
                                    <div class="so-card-top">
                                        <div>
                                            <h2 class="so-card-title">
                                                <a href="<?= bv_seller_offers_h($threadUrl) ?>">
                                                    <?= bv_seller_offers_h($listingTitle) ?>
                                                </a>
                                            </h2>
                                            <div class="so-card-sub">
                                                Offer #<?= number_format($offerId) ?>
                                                · Buyer: <?= bv_seller_offers_h($buyerName) ?>
                                                <?php if ($listingUrl !== '#'): ?>
                                                    · <a href="<?= bv_seller_offers_h($listingUrl) ?>" style="color:#1d4ed8;text-decoration:none;">View listing</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <span class="so-status" style="color:<?= bv_seller_offers_h($statusColor) ?>;background:<?= bv_seller_offers_h($statusBg) ?>;">
                                            <?= bv_seller_offers_h($statusLabel) ?>
                                        </span>
                                    </div>

                                    <div class="so-grid">
                                        <div class="so-stat">
                                            <span class="so-stat-label">Listed Price</span>
                                            <span class="so-stat-value"><?= bv_seller_offers_h(bv_seller_offer_format_money($listingPrice, $listingCurrency)) ?></span>
                                            <span class="so-stat-muted">Snapshot from listing</span>
                                        </div>

                                        <div class="so-stat">
                                            <span class="so-stat-label">Latest Offer</span>
                                            <span class="so-stat-value"><?= bv_seller_offers_h(bv_seller_offer_format_money($latestOfferPrice, $listingCurrency)) ?></span>
                                            <span class="so-stat-muted">Current negotiation number</span>
                                        </div>

                                        <div class="so-stat">
                                            <span class="so-stat-label">Agreed Price</span>
                                            <span class="so-stat-value"><?= bv_seller_offers_h(bv_seller_offer_format_money($agreedPrice, $listingCurrency)) ?></span>
                                            <span class="so-stat-muted">
                                                <?= $tokenExpiresAt !== '' ? 'Checkout token expires ' . bv_seller_offers_h($tokenExpiresAt) : 'Waiting for acceptance' ?>
                                            </span>
                                        </div>

                                        <div class="so-stat">
                                            <span class="so-stat-label">Latest Activity</span>
                                            <span class="so-stat-value"><?= bv_seller_offers_h(bv_seller_offer_time_ago($lastMessageAt !== '' ? $lastMessageAt : $createdAt)) ?></span>
                                            <span class="so-stat-muted">
                                                Started <?= bv_seller_offers_h(date('M j, Y H:i', strtotime($createdAt ?: 'now'))) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="so-actions">
                                        <a href="<?= bv_seller_offers_h($threadUrl) ?>" class="so-open-link">Open Chat</a>

                                        <?php if ($canQuickAccept): ?>
                                            <form method="post" action="/seller/offers.php" class="so-inline-form" onsubmit="return confirm('Accept this offer and prepare checkout for the buyer?');">
                                                <input type="hidden" name="csrf_token" value="<?= bv_seller_offers_h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="quick_accept">
                                                <input type="hidden" name="offer_id" value="<?= $offerId ?>">
                                                <input type="hidden" name="return_url" value="<?= bv_seller_offers_h($_SERVER['REQUEST_URI'] ?? '/seller/offers.php') ?>">
                                                <button type="submit" class="so-quick-btn so-quick-accept">Quick Accept</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($canQuickReject): ?>
                                            <form method="post" action="/seller/offers.php" class="so-inline-form" onsubmit="return confirm('Reject this offer?');">
                                                <input type="hidden" name="csrf_token" value="<?= bv_seller_offers_h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="quick_reject">
                                                <input type="hidden" name="offer_id" value="<?= $offerId ?>">
                                                <input type="hidden" name="return_url" value="<?= bv_seller_offers_h($_SERVER['REQUEST_URI'] ?? '/seller/offers.php') ?>">
                                                <button type="submit" class="so-quick-btn so-quick-reject">Quick Reject</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$canQuickAccept && !$canQuickReject && !$isTerminal): ?>
                                            <span class="so-note">This deal is already moving toward checkout. Open the thread to manage details.</span>
                                        <?php endif; ?>

                                        <?php if ($isTerminal): ?>
                                            <span class="so-note">This thread is closed. Open the chat to review its final timeline.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="so-pagination" aria-label="Offers pagination">
                            <?php
                            $start = max(1, $filters['page'] - 2);
                            $end = min($totalPages, $filters['page'] + 2);
                            ?>
                            <?php if ($filters['page'] > 1): ?>
                                <a class="so-page" href="<?= bv_seller_offers_h(bv_seller_offer_build_pagination_url($filters, $filters['page'] - 1)) ?>">&laquo;</a>
                            <?php endif; ?>

                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <a class="so-page<?= $p === $filters['page'] ? ' is-current' : '' ?>" href="<?= bv_seller_offers_h(bv_seller_offer_build_pagination_url($filters, $p)) ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($filters['page'] < $totalPages): ?>
                                <a class="so-page" href="<?= bv_seller_offers_h(bv_seller_offer_build_pagination_url($filters, $filters['page'] + 1)) ?>">&raquo;</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>