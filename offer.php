<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$root = __DIR__;

require_once $root . '/includes/listing_offers.php';
require_once $root . '/includes/offer_smart_engine.php';

$offerNotificationsFile = $root . '/includes/offer_notifications.php';
if (is_file($offerNotificationsFile)) {
    require_once $offerNotificationsFile;
}

if (!function_exists('bv_offer_page_h')) {
    function bv_offer_page_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_offer_page_csrf_token')) {
    function bv_offer_page_csrf_token(string $scope = 'offer_page_actions'): string
    {
        if (empty($_SESSION['_csrf_offer_page'][$scope]) || !is_string($_SESSION['_csrf_offer_page'][$scope])) {
            $_SESSION['_csrf_offer_page'][$scope] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_offer_page'][$scope];
    }
}

if (!function_exists('bv_offer_page_verify_csrf')) {
    function bv_offer_page_verify_csrf(?string $token, string $scope = 'offer_page_actions'): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION['_csrf_offer_page'][$scope] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('bv_offer_page_form_started_at')) {
    function bv_offer_page_form_started_at(string $scope = 'offer_page_actions'): int
    {
        $value = time();
        $_SESSION['_offer_page_started_at'][$scope] = $value;
        return $value;
    }
}

if (!function_exists('bv_offer_page_is_too_fast')) {
    function bv_offer_page_is_too_fast($startedAt, int $minSeconds = 2, string $scope = 'offer_page_actions'): bool
    {
        $sessionStartedAt = (int) ($_SESSION['_offer_page_started_at'][$scope] ?? 0);
        $postedStartedAt = is_numeric($startedAt) ? (int) $startedAt : 0;
        $effectiveStartedAt = max($sessionStartedAt, $postedStartedAt);

        if ($effectiveStartedAt <= 0) {
            return true;
        }

        return (time() - $effectiveStartedAt) < $minSeconds;
    }
}

if (!function_exists('bv_offer_page_flash_set')) {
    function bv_offer_page_flash_set(string $status, string $message, array $old = [], array $errors = []): void
    {
        $_SESSION['offer_page_flash'] = [
            'status' => $status,
            'message' => $message,
            'old' => $old,
            'errors' => $errors,
        ];
    }
}

if (!function_exists('bv_offer_page_flash_get')) {
    function bv_offer_page_flash_get(): array
    {
        $flash = $_SESSION['offer_page_flash'] ?? [];
        unset($_SESSION['offer_page_flash']);

        return is_array($flash) ? $flash : [];
    }
}

if (!function_exists('bv_offer_page_redirect')) {
    function bv_offer_page_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('bv_offer_page_offer_url')) {
    function bv_offer_page_offer_url(int $offerId, array $extra = []): string
    {
        return bv_offer_redirect_url('offer.php', array_merge(['id' => $offerId > 0 ? $offerId : null], $extra));
    }
}

if (!function_exists('bv_offer_page_login_url')) {
    function bv_offer_page_login_url(string $returnUrl): string
    {
        $candidates = [
            '/login.php',
            '/member/login.php',
            'login.php',
            'member/login.php',
        ];

        foreach ($candidates as $candidate) {
            $path = $candidate[0] === '/' ? ($GLOBALS['root'] ?? __DIR__) . $candidate : ($GLOBALS['root'] ?? __DIR__) . '/' . $candidate;
            if (is_file($path)) {
                return $candidate . '?redirect=' . rawurlencode($returnUrl);
            }
        }

        return '/login.php?redirect=' . rawurlencode($returnUrl);
    }
}

if (!function_exists('bv_offer_page_status_badge')) {
    function bv_offer_page_status_badge(string $status): array
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

if (!function_exists('bv_offer_page_message_bubble_class')) {
    function bv_offer_page_message_bubble_class(string $senderRole, int $senderUserId, int $currentUserId): string
    {
        $senderRole = strtolower(trim($senderRole));

        if ($currentUserId > 0 && $senderUserId === $currentUserId) {
            return 'is-self';
        }

        if ($senderRole === 'seller') {
            return 'is-seller';
        }

        if ($senderRole === 'buyer') {
            return 'is-buyer';
        }

        if ($senderRole === 'admin') {
            return 'is-admin';
        }

        return 'is-system';
    }
}

if (!function_exists('bv_offer_page_sender_label')) {
    function bv_offer_page_sender_label(array $message, array $offer, array $names = []): string
    {
        $senderRole = strtolower(trim((string) ($message['sender_role'] ?? '')));
        $senderUserId = (int) ($message['sender_user_id'] ?? 0);

        if ($senderRole === 'system') {
            return 'System';
        }

        if ($senderRole === 'admin') {
            return 'Admin';
        }

        if ($senderUserId > 0 && isset($names[$senderUserId]) && $names[$senderUserId] !== '') {
            return $names[$senderUserId];
        }

        if ($senderUserId > 0 && $senderUserId === (int) ($offer['buyer_user_id'] ?? 0)) {
            return 'Buyer';
        }

        if ($senderUserId > 0 && $senderUserId === (int) ($offer['seller_user_id'] ?? 0)) {
            return 'Seller';
        }

        return ucfirst($senderRole !== '' ? $senderRole : 'User');
    }
}

if (!function_exists('bv_offer_page_format_message_type')) {
    function bv_offer_page_format_message_type(string $type): string
    {
        $type = strtolower(trim($type));

        $map = [
            'offer' => 'Offer',
            'counter' => 'Counter',
            'message' => 'Message',
            'accept' => 'Accepted',
            'reject' => 'Rejected',
            'expire_notice' => 'Expired',
            'system' => 'System',
        ];

        return $map[$type] ?? ucfirst($type !== '' ? $type : 'Message');
    }
}

if (!function_exists('bv_offer_page_user_names')) {
    function bv_offer_page_user_names(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function ($id) {
            return $id > 0;
        })));

        if (empty($userIds)) {
            return [];
        }

        $db = bv_listing_offers_db();
        $names = [];

        if ($db instanceof mysqli) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)";
            $rows = bv_listing_offers_query_all($sql, $userIds);
        } else {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)";
            $rows = bv_listing_offers_query_all($sql, $userIds);
        }

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            if ($name === '') {
                $name = trim((string) ($row['email'] ?? ''));
            }
            if ($name === '') {
                $name = 'User #' . $id;
            }

            $names[$id] = $name;
        }

        return $names;
    }
}

if (!function_exists('bv_offer_page_listing_url')) {
    function bv_offer_page_listing_url(array $listing): string
    {
        $slug = trim((string) ($listing['slug'] ?? ''));
        $id = (int) ($listing['id'] ?? 0);

        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }

        return '/listing.php?id=' . $id;
    }
}

if (!function_exists('bv_offer_page_listing_image')) {
    function bv_offer_page_listing_image(array $listing): string
    {
        $path = trim((string) ($listing['cover_image'] ?? ''));
        if ($path === '') {
            return '/images/placeholder-fish.jpg';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        $normalized = '/' . ltrim(str_replace('\\', '/', $path), '/');
        return preg_replace('~/+~', '/', $normalized);
    }
}

if (!function_exists('bv_offer_page_detect_listing_exists')) {
    function bv_offer_page_detect_listing_exists(int $listingId): bool
    {
        if ($listingId <= 0) {
            return false;
        }

        try {
            $row = bv_listing_offers_query_one(
                "SELECT id
                 FROM listings
                 WHERE id = ?
                 LIMIT 1",
                [$listingId]
            );

            return !empty($row);
        } catch (Throwable $e) {
            return true;
        }
    }
}

if (!function_exists('bv_offer_page_normalize_message_type')) {
    function bv_offer_page_normalize_message_type(string $type, bool $isSeller): string
    {
        $type = strtolower(trim($type));
        $allowed = bv_offer_allowed_message_types();

        if (!in_array($type, $allowed, true)) {
            return $isSeller ? 'counter' : 'offer';
        }

        if (in_array($type, ['accept', 'reject', 'expire_notice', 'system'], true)) {
            return $isSeller ? 'counter' : 'offer';
        }

        return $type;
    }
}

if (!function_exists('bv_offer_page_guess_title')) {
    function bv_offer_page_guess_title(array $listing): string
    {
        $title = trim((string) ($listing['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $title = trim((string) ($listing['name'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'Listing #' . (int) ($listing['id'] ?? 0);
    }
}

$flash = bv_offer_page_flash_get();

$offerId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$currentUserId = bv_offer_current_user_id();
$currentUserRole = bv_offer_current_user_role();
$returnUrl = bv_offer_page_offer_url($offerId);



if (!bv_listing_offers_require_tables()) {
    http_response_code(500);
    echo 'Offer tables are not ready yet.';
    exit;
}

if ($offerId <= 0) {
    http_response_code(404);
    echo 'Offer not found.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postOfferId = isset($_POST['offer_id']) && is_numeric($_POST['offer_id']) ? (int) $_POST['offer_id'] : 0;
    $postReturnUrl = trim((string) ($_POST['return_url'] ?? ''));
    if ($postReturnUrl === '' || preg_match('/^https?:\/\//i', $postReturnUrl) || stripos($postReturnUrl, 'javascript:') === 0) {
        $postReturnUrl = bv_offer_page_offer_url($postOfferId > 0 ? $postOfferId : $offerId);
    }

    if ($currentUserId <= 0) {
        bv_offer_page_flash_set('error', 'Please log in before using the offer chat.');
        bv_offer_page_redirect(bv_offer_page_login_url($postReturnUrl));
    }

    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!bv_offer_page_verify_csrf($csrfToken, 'offer_page_actions')) {
        bv_offer_page_flash_set('error', 'Security token mismatch. Please refresh the page and try again.');
        bv_offer_page_redirect($postReturnUrl);
    }

    $honeypot = trim((string) ($_POST['website'] ?? ($_POST['bot_honeypot'] ?? '')));
    if ($honeypot !== '') {
        bv_offer_page_flash_set('error', 'Request blocked.');
        bv_offer_page_redirect($postReturnUrl);
    }

    $startedAt = $_POST['form_started_at'] ?? '';
    if (bv_offer_page_is_too_fast($startedAt, 2, 'offer_page_actions')) {
        bv_offer_page_flash_set('error', 'Submission was too fast. Please try again.');
        bv_offer_page_redirect($postReturnUrl);
    }

    $offer = bv_offer_get_by_id($postOfferId);
    if (!$offer) {
        bv_offer_page_flash_set('error', 'Offer not found.');
        bv_offer_page_redirect(bv_offer_page_offer_url($offerId));
    }

	

if (!bv_offer_current_user_can_view($offer, $currentUserId, $currentUserRole)) {
    if ($currentUserId <= 0) {
        bv_offer_page_flash_set('error', 'Please log in to view this offer.');
        bv_offer_page_redirect(bv_offer_page_login_url($returnUrl));
    }

    http_response_code(403);
    echo '403 Forbidden';
    exit;
}
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        if ($action === 'send_message') {
            if (bv_offer_is_terminal_status((string) ($offer['status'] ?? ''))) {
                bv_offer_page_flash_set('error', 'This offer is already closed.');
                bv_offer_page_redirect($postReturnUrl);
            }

            $isBuyer = bv_offer_current_user_can_buyer_reply($offer, $currentUserId, $currentUserRole);
            $isSeller = bv_offer_current_user_can_seller_manage($offer, $currentUserId, $currentUserRole);

            if (!$isBuyer && !$isSeller) {
                throw new RuntimeException('Permission denied.');
            }

            $messageText = trim((string) ($_POST['message_text'] ?? ''));
            $priceInput = trim((string) ($_POST['offer_price'] ?? ''));
            $messageTypeInput = trim((string) ($_POST['message_type'] ?? 'message'));

            $offerPrice = 0.0;
            if ($priceInput !== '') {
                $offerPrice = bv_offer_validate_price($priceInput);
                if ($offerPrice <= 0) {
                    bv_offer_page_flash_set(
                        'error',
                        'Offer price must be a number greater than 0.',
                        [
                            'message_text' => $messageText,
                            'offer_price' => $priceInput,
                            'message_type' => $messageTypeInput,
                        ]
                    );
                    bv_offer_page_redirect($postReturnUrl);
                }
            }

            if ($messageText === '' && $offerPrice <= 0) {
                bv_offer_page_flash_set(
                    'error',
                    'Please enter a message or an offer price.',
                    [
                        'message_text' => $messageText,
                        'offer_price' => $priceInput,
                        'message_type' => $messageTypeInput,
                    ]
                );
                bv_offer_page_redirect($postReturnUrl);
            }

            $senderRole = $isSeller
                ? (bv_offer_is_admin_role($currentUserRole) ? 'admin' : 'seller')
                : 'buyer';

            $messageType = bv_offer_page_normalize_message_type(
                $messageTypeInput !== '' ? $messageTypeInput : ($isSeller ? 'counter' : 'offer'),
                $isSeller
            );

            if ($offerPrice <= 0 && $messageType !== 'message') {
                $messageType = 'message';
            }

            $newMessageId = bv_offer_insert_message([
                'offer_id' => (int) $offer['id'],
                'sender_user_id' => $currentUserId,
                'sender_role' => $senderRole,
                'message_type' => $messageType,
                'offer_price' => $offerPrice,
                'message_text' => $messageText,
            ]);

            if ($senderRole === 'buyer' && function_exists('bv_offer_notify_seller_new_message')) {
                try {
                    bv_offer_notify_seller_new_message((int) $offer['id'], (int) $newMessageId);
                } catch (Throwable $notifyError) {
                }
            }

            bv_offer_page_flash_set('success', 'Message sent successfully.');
            unset($_SESSION['_offer_page_started_at']['offer_page_actions']);
            bv_offer_page_redirect($postReturnUrl);
        }

        if ($action === 'accept_offer') {
            $agreedPrice = bv_offer_validate_price($_POST['agreed_price'] ?? 0);
            $approvedMessageId = isset($_POST['approved_message_id']) && is_numeric($_POST['approved_message_id'])
                ? (int) $_POST['approved_message_id']
                : null;

            if ($agreedPrice <= 0) {
                bv_offer_page_flash_set('error', 'Agreed price must be greater than 0.');
                bv_offer_page_redirect($postReturnUrl);
            }

            $result = bv_offer_accept(
                (int) $offer['id'],
                $agreedPrice,
                $approvedMessageId,
                $currentUserId,
                $currentUserRole,
                'Offer accepted by seller. Buyer can checkout within 24 hours.'
            );

            if (!$result || empty($result['offer'])) {
                throw new RuntimeException('Unable to accept offer.');
            }

            if (function_exists('bv_offer_notify_buyer_checkout_ready')) {
                try {
                    $notifyToken = null;

                    if (!empty($result['token']) && is_array($result['token'])) {
                        $notifyToken = $result['token'];
                    } elseif (function_exists('bv_offer_get_active_checkout_token')) {
                        $notifyToken = bv_offer_get_active_checkout_token((int) $offer['id']);
                    }

                    bv_offer_notify_buyer_checkout_ready((int) $offer['id'], $notifyToken);
                } catch (Throwable $notifyError) {
                }
            }

            bv_offer_page_flash_set('success', 'Offer accepted. Checkout token created for buyer.');
            unset($_SESSION['_offer_page_started_at']['offer_page_actions']);
            bv_offer_page_redirect($postReturnUrl);
        }

        if ($action === 'cancel_offer') {
            $isBuyer = ((int) ($offer['buyer_user_id'] ?? 0) === $currentUserId);
            $isSeller = ((int) ($offer['seller_user_id'] ?? 0) === $currentUserId);
            $isAdmin = bv_offer_is_admin_role($currentUserRole);

            if (!$isBuyer && !$isSeller && !$isAdmin) {
                throw new RuntimeException('Permission denied.');
            }

            if (bv_offer_is_terminal_status((string) ($offer['status'] ?? ''))) {
                bv_offer_page_flash_set('error', 'This offer is already closed.');
                bv_offer_page_redirect($postReturnUrl);
            }

            try {
                bv_listing_offers_begin_transaction();

                bv_offer_cancel_active_checkout_tokens(
                    (int) $offer['id'],
                    $currentUserId,
                    $currentUserRole,
                    'Offer cancelled from offer thread.'
                );

                bv_offer_update_status(
                    (int) $offer['id'],
                    'cancelled',
                    $currentUserId,
                    $currentUserRole,
                    $isBuyer ? 'Offer cancelled by buyer.' : ($isSeller ? 'Offer cancelled by seller.' : 'Offer cancelled by admin.')
                );

                bv_offer_insert_message([
                    'offer_id' => (int) $offer['id'],
                    'sender_user_id' => $currentUserId,
                    'sender_role' => $isAdmin ? 'admin' : ($isSeller ? 'seller' : 'buyer'),
                    'message_type' => 'system',
                    'message_text' => $isBuyer ? 'Buyer cancelled this offer.' : ($isSeller ? 'Seller cancelled this offer.' : 'Admin cancelled this offer.'),
                ]);

                bv_listing_offers_commit();
            } catch (Throwable $e) {
                bv_listing_offers_rollback();
                throw $e;
            }

            bv_offer_page_flash_set('success', 'Offer cancelled successfully.');
            unset($_SESSION['_offer_page_started_at']['offer_page_actions']);
            bv_offer_page_redirect($postReturnUrl);
        }

        bv_offer_page_flash_set('error', 'Invalid action.');
        bv_offer_page_redirect($postReturnUrl);
    } catch (Throwable $e) {
        bv_offer_page_flash_set('error', 'Action failed: ' . $e->getMessage(), [
            'message_text' => (string) ($_POST['message_text'] ?? ''),
            'offer_price' => (string) ($_POST['offer_price'] ?? ''),
            'message_type' => (string) ($_POST['message_type'] ?? ''),
        ]);
        bv_offer_page_redirect($postReturnUrl);
    }
}

$offer = bv_offer_get_by_id($offerId);
if (!$offer) {
    http_response_code(404);
    echo 'Offer not found.';
    exit;
}

if (!bv_offer_current_user_can_view($offer, $currentUserId, $currentUserRole)) {
    if ($currentUserId <= 0) {
        bv_offer_page_flash_set('error', 'Please log in to view this offer.');
        bv_offer_page_redirect(bv_offer_page_login_url($returnUrl));
    }

    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$listingId = (int) ($offer['listing_id'] ?? 0);
$listing = $listingId > 0 ? bv_offer_get_listing_by_id($listingId) : null;

if (!$listing && $listingId > 0 && !bv_offer_page_detect_listing_exists($listingId)) {
    http_response_code(404);
    echo 'Listing not found.';
    exit;
}

$offerStats = [
    'avg_agreed_price' => 0,
];

try {
    $offerStatsRow = bv_listing_offers_query_one(
        "SELECT AVG(agreed_price) AS avg_agreed_price
         FROM listing_offers
         WHERE listing_id = ?
           AND agreed_price IS NOT NULL
           AND agreed_price > 0
           AND status = 'completed'",
        [$listingId]
    );

    if ($offerStatsRow && isset($offerStatsRow['avg_agreed_price'])) {
        $offerStats['avg_agreed_price'] = (float) $offerStatsRow['avg_agreed_price'];
    }
} catch (Throwable $e) {
    $offerStats['avg_agreed_price'] = 0;
}

$smart = bv_offer_smart_decision(
    $offer,
    $listing ?: [],
    [
        'avg_agreed_price' => $offerStats['avg_agreed_price'] ?? 0
    ]
);

$messages = bv_offer_get_messages($offerId, 500);
$activeToken = bv_offer_get_active_checkout_token($offerId);

$offerAcceptCheckoutUrl = '';
if ($activeToken && !empty($activeToken['token'])) {
    $offerAcceptCheckoutUrl = '/offer_accept_checkout.php?token=' . rawurlencode((string) $activeToken['token']) . '&offer_id=' . (int) $offerId;
}

$userNames = bv_offer_page_user_names([
    (int) ($offer['buyer_user_id'] ?? 0),
    (int) ($offer['seller_user_id'] ?? 0),
]);

$buyerName = $userNames[(int) ($offer['buyer_user_id'] ?? 0)] ?? 'Buyer';
$sellerName = $userNames[(int) ($offer['seller_user_id'] ?? 0)] ?? 'Seller';

$canBuyerReply = bv_offer_current_user_can_buyer_reply($offer, $currentUserId, $currentUserRole);
$canSellerManage = bv_offer_current_user_can_seller_manage($offer, $currentUserId, $currentUserRole);
$canCheckout = bv_offer_can_checkout($offer, $currentUserId);
$isTerminal = bv_offer_is_terminal_status((string) ($offer['status'] ?? ''));

$csrfToken = bv_offer_page_csrf_token('offer_page_actions');
$formStartedAt = bv_offer_page_form_started_at('offer_page_actions');

$flashStatus = (string) ($flash['status'] ?? '');
$flashMessage = (string) ($flash['message'] ?? '');
$flashOld = isset($flash['old']) && is_array($flash['old']) ? $flash['old'] : [];
$flashErrors = isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [];

$oldMessageText = (string) ($flashOld['message_text'] ?? '');
$oldOfferPrice = (string) ($flashOld['offer_price'] ?? '');
$oldMessageType = (string) ($flashOld['message_type'] ?? '');

[$statusLabel, $statusColor, $statusBg] = bv_offer_page_status_badge((string) ($offer['status'] ?? ''));
$listingTitle = $listing ? bv_offer_page_guess_title($listing) : ('Listing #' . $listingId);
$listingUrl = $listing ? bv_offer_page_listing_url($listing) : '#';
$listingImage = $listing ? bv_offer_page_listing_image($listing) : '/images/placeholder-fish.jpg';

$pageTitle = 'Offer #' . $offerId . ' | Bettavaro';
$metaDescription = 'Direct buyer and seller offer thread for ' . $listingTitle . '.';
$canonicalUrl = '';
$ogImage = $listingImage;
$bodyClass = 'offer-thread-page';

include $root . '/includes/head.php';
?>
<style>
:root{
    --offer-bg:#07120d;
    --offer-ink:#0f172a;
    --offer-card:#ffffff;
    --offer-line:#dbe2ea;
    --offer-gold:#d7bc6b;
    --offer-green:#166534;
    --offer-green-soft:#ecfdf3;
    --offer-blue:#1d4ed8;
    --offer-blue-soft:#dbeafe;
    --offer-slate:#475569;
    --offer-shadow:0 22px 70px rgba(0,0,0,.18);
}
body{
    background:
        radial-gradient(circle at top, rgba(20,55,37,.28), transparent 34%),
        linear-gradient(180deg,#05100b 0%, #06110c 44%, #040a07 100%);
}
.offer-wrap{
    max-width:1280px;
    margin:0 auto;
    padding:28px 16px 90px;
}
.offer-topnote{
    margin:0 0 18px;
    padding:14px 16px;
    border-radius:16px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
    color:#d7e4dc;
}
.offer-grid{
    display:grid;
    grid-template-columns:360px minmax(0,1fr);
    gap:22px;
    align-items:start;
}
.offer-card{
    background:rgba(255,255,255,.97);
    border-radius:24px;
    border:1px solid rgba(255,255,255,.36);
    box-shadow:var(--offer-shadow);
    overflow:hidden;
    color:var(--offer-ink);
}
.offer-card-body{
    padding:22px;
}
.offer-side-sticky{
    position:sticky;
    top:18px;
}
.offer-listing-image{
    width:100%;
    aspect-ratio:4/3;
    object-fit:cover;
    display:block;
    background:#f1f5f9;
}
.offer-badge{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    letter-spacing:.03em;
}
.offer-title{
    margin:0 0 8px;
    font-size:34px;
    line-height:1.05;
    letter-spacing:-.03em;
    color:#0f172a;
}
.offer-price{
    margin-top:6px;
    font-size:28px;
    font-weight:900;
    color:#1f3d2b;
}
.offer-muted{
    color:#64748b;
    line-height:1.7;
}
.offer-side-grid{
    display:grid;
    gap:12px;
    margin-top:18px;
}
.offer-kv{
    border:1px solid var(--offer-line);
    border-radius:16px;
    padding:14px;
    background:linear-gradient(180deg,#fff,#f8fafc);
}
.offer-kv-label{
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#64748b;
    margin-bottom:6px;
}
.offer-kv-value{
    font-size:15px;
    font-weight:800;
    color:#0f172a;
}
.offer-section-title{
    margin:0 0 12px;
    font-size:20px;
    font-weight:900;
    color:#0f172a;
}
.offer-flash{
    margin:0 0 16px;
    padding:13px 14px;
    border-radius:14px;
    font-weight:800;
}
.offer-flash-success{
    background:#dcfce7;
    color:#166534;
}
.offer-flash-error{
    background:#fee2e2;
    color:#991b1b;
}
.offer-thread-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.offer-thread-meta{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-bottom:18px;
}
.offer-thread-stat{
    border:1px solid var(--offer-line);
    border-radius:16px;
    padding:14px;
    background:linear-gradient(180deg,#fff,#f8fafc);
}
.offer-thread-stat strong{
    display:block;
    font-size:20px;
    line-height:1.1;
    color:#0f172a;
}
.offer-thread-stat span{
    display:block;
    margin-top:8px;
    color:#64748b;
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
}
.offer-chat{
    border:1px solid var(--offer-line);
    border-radius:20px;
    background:linear-gradient(180deg,#f8fafc,#ffffff);
    padding:16px;
}
.offer-messages{
    display:grid;
    gap:14px;
    max-height:760px;
    overflow:auto;
    padding-right:4px;
}
.offer-empty{
    border:1px dashed #cbd5e1;
    border-radius:18px;
    padding:18px;
    color:#64748b;
    background:#fff;
}
.offer-message{
    display:flex;
    flex-direction:column;
    gap:8px;
    max-width:82%;
}
.offer-message.is-self{
    margin-left:auto;
    align-items:flex-end;
}
.offer-message.is-seller:not(.is-self),
.offer-message.is-admin:not(.is-self){
    margin-right:auto;
    align-items:flex-start;
}
.offer-message.is-buyer:not(.is-self){
    margin-right:auto;
    align-items:flex-start;
}
.offer-message.is-system{
    margin:0 auto;
    align-items:center;
    max-width:92%;
}
.offer-message-head{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    font-size:12px;
    color:#64748b;
    font-weight:800;
}
.offer-message-chip{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    background:#eef2ff;
    color:#3730a3;
    font-size:11px;
    font-weight:900;
    letter-spacing:.03em;
}
.offer-bubble{
    border-radius:18px;
    padding:14px 15px;
    line-height:1.7;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
    border:1px solid transparent;
    white-space:pre-line;
    word-break:break-word;
}
.offer-message.is-self .offer-bubble{
    background:#dcfce7;
    color:#14532d;
    border-color:#86efac;
}
.offer-message.is-seller .offer-bubble{
    background:#ede9fe;
    color:#4c1d95;
    border-color:#c4b5fd;
}
.offer-message.is-buyer .offer-bubble{
    background:#dbeafe;
    color:#1e3a8a;
    border-color:#93c5fd;
}
.offer-message.is-admin .offer-bubble{
    background:#fef3c7;
    color:#92400e;
    border-color:#fcd34d;
}
.offer-message.is-system .offer-bubble{
    background:#f8fafc;
    color:#334155;
    border-color:#cbd5e1;
    text-align:center;
}
.offer-bubble-price{
    margin-top:10px;
    display:inline-flex;
    align-items:center;
    padding:7px 10px;
    border-radius:999px;
    background:rgba(255,255,255,.65);
    font-weight:900;
}
.offer-actions{
    margin-top:18px;
    display:grid;
    gap:16px;
}
.offer-form{
    border:1px solid var(--offer-line);
    border-radius:20px;
    padding:18px;
    background:linear-gradient(180deg,#fff,#fafafa);
}
.offer-form-grid{
    display:grid;
    grid-template-columns:1fr 220px;
    gap:14px;
}
.offer-field{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.offer-field label{
    font-size:14px;
    font-weight:900;
    color:#111827;
}
.offer-field input,
.offer-field textarea,
.offer-field select{
    width:100%;
    border:1px solid #d1d5db;
    border-radius:14px;
    padding:12px 13px;
    font:inherit;
    color:#111827;
    background:#fff;
}
.offer-field textarea{
    min-height:130px;
    resize:vertical;
}
.offer-form-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-top:14px;
}
.offer-note{
    font-size:13px;
    color:#64748b;
    line-height:1.7;
}
.offer-btn,
.offer-btn-secondary,
.offer-btn-danger{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:48px;
    border-radius:14px;
    padding:0 16px;
    border:none;
    cursor:pointer;
    text-decoration:none;
    font-weight:900;
    transition:transform .18s ease, box-shadow .18s ease;
}
.offer-btn:hover,
.offer-btn-secondary:hover,
.offer-btn-danger:hover{
    transform:translateY(-1px);
}
.offer-btn{
    background:#243c2b;
    color:#fff;
    box-shadow:0 10px 26px rgba(36,60,43,.18);
}
.offer-btn-secondary{
    background:#fff;
    color:#243c2b;
    border:1px solid #243c2b;
}
.offer-btn-danger{
    background:#991b1b;
    color:#fff;
}
.offer-action-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.offer-hidden-field{
    position:absolute !important;
    left:-9999px !important;
    top:auto !important;
    width:1px !important;
    height:1px !important;
    overflow:hidden !important;
}
.offer-token-box{
    border:1px solid #bbf7d0;
    border-radius:18px;
    padding:16px;
    background:#f0fdf4;
    color:#14532d;
}
.offer-token-box a{
    color:#166534;
    text-decoration:underline;
    text-underline-offset:3px;
}
.offer-error-list{
    margin:10px 0 0;
    padding-left:18px;
    color:#b91c1c;
    font-size:13px;
    font-weight:800;
}
@media (max-width:1080px){
    .offer-grid{
        grid-template-columns:1fr;
    }
    .offer-side-sticky{
        position:relative;
        top:auto;
    }
    .offer-thread-meta{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media (max-width:720px){
    .offer-wrap{
        padding:18px 12px 90px;
    }
    .offer-title{
        font-size:28px;
    }
    .offer-thread-meta,
    .offer-form-grid{
        grid-template-columns:1fr;
    }
    .offer-message{
        max-width:100%;
    }
}
</style>
<?php include $root . '/includes/header.php'; ?>

<main class="offer-wrap">
    <div class="offer-topnote">
        Direct buyer ↔ seller negotiation thread for this listing. Keep price and message history in one room so nobody has to hunt through chat archaeology later 😄
    </div>

    <?php if ($flashMessage !== ''): ?>
        <div class="offer-flash <?= $flashStatus === 'success' ? 'offer-flash-success' : 'offer-flash-error'; ?>">
            <?= bv_offer_page_h($flashMessage); ?>
        </div>
    <?php endif; ?>

    <div class="offer-grid">
        <aside class="offer-side-sticky">
            <div class="offer-card">
                <img src="<?= bv_offer_page_h($listingImage); ?>" alt="<?= bv_offer_page_h($listingTitle); ?>" class="offer-listing-image">
                <div class="offer-card-body">
                    <div class="offer-badge" style="background:<?= bv_offer_page_h($statusBg); ?>;color:<?= bv_offer_page_h($statusColor); ?>;">
                        <?= bv_offer_page_h($statusLabel); ?>
                    </div>

                    <h1 class="offer-title" style="margin-top:14px;"><?= bv_offer_page_h($listingTitle); ?></h1>

                    <?php if ($listing): ?>
                        <div class="offer-price">
                            <?= bv_offer_page_h(bv_offer_format_money($listing['price'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?>
                        </div>
                    <?php endif; ?>
					
					<?php if (!empty($smart['action'])): ?>
    <div style="margin-top:12px;padding:12px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;">
        <?php if ($smart['action'] === 'accept'): ?>
            <strong style="color:#16a34a;">🔥 Smart Suggestion: ACCEPT</strong>

        <?php elseif ($smart['action'] === 'counter'): ?>
            <strong style="color:#ca8a04;">⚡ Smart Suggestion: COUNTER</strong><br>
            Suggested Price:
            <strong>
                <?= bv_offer_page_h(bv_offer_format_money($smart['counter_price'] ?? 0, (string) ($offer['currency'] ?? 'USD'))); ?>
            </strong>

        <?php elseif ($smart['action'] === 'reject'): ?>
            <strong style="color:#dc2626;">❌ Smart Suggestion: REJECT</strong>
        <?php endif; ?>

        <div style="font-size:12px;color:#64748b;margin-top:4px;">
            Confidence: <?= bv_offer_page_h((string) ($smart['confidence'] ?? 0)); ?>%
        </div>
    </div>
<?php endif; ?>

                    <p class="offer-muted" style="margin:12px 0 0;">
                        Listing offer room for buyer and seller to negotiate directly, then move cleanly into checkout when both sides agree.
                    </p>

                    <div class="offer-side-grid">
                        <div class="offer-kv">
                            <div class="offer-kv-label">Offer ID</div>
                            <div class="offer-kv-value">#<?= (int) $offer['id']; ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Buyer</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h($buyerName); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Seller</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h($sellerName); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Original Listing Price</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h(bv_offer_format_money($offer['listing_price_snapshot'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Latest Offer</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h(bv_offer_format_money($offer['latest_offer_price'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Agreed Price</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h(bv_offer_format_money($offer['agreed_price'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Created</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h((string) ($offer['created_at'] ?? '')); ?></div>
                        </div>

                        <div class="offer-kv">
                            <div class="offer-kv-label">Last Message</div>
                            <div class="offer-kv-value"><?= bv_offer_page_h((string) ($offer['last_message_at'] ?? '—')); ?></div>
                        </div>

                        <?php if (trim((string) ($offer['expires_at'] ?? '')) !== ''): ?>
                            <div class="offer-kv">
                                <div class="offer-kv-label">Checkout / Offer Expiry</div>
                                <div class="offer-kv-value"><?= bv_offer_page_h((string) $offer['expires_at']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if ($listing): ?>
                            <a class="offer-btn-secondary" href="<?= bv_offer_page_h($listingUrl); ?>">Back to Listing</a>
                        <?php endif; ?>
<?php if ($canCheckout && $activeToken && !empty($activeToken['token'])): ?>
    <a class="offer-btn" href="<?= bv_offer_page_h($offerAcceptCheckoutUrl); ?>">Accept & Checkout</a>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>

        <section>
            <div class="offer-card">
                <div class="offer-card-body">
                    <div class="offer-thread-head">
                        <div>
                            <h2 class="offer-section-title" style="margin-bottom:6px;">Offer Thread</h2>
                            <div class="offer-muted">
                                Only buyer, seller, and admin can view this room.
                            </div>
                        </div>
                        <div class="offer-badge" style="background:<?= bv_offer_page_h($statusBg); ?>;color:<?= bv_offer_page_h($statusColor); ?>;">
                            <?= bv_offer_page_h($statusLabel); ?>
                        </div>
                    </div>

                    <div class="offer-thread-meta">
                        <div class="offer-thread-stat">
                            <strong><?= count($messages); ?></strong>
                            <span>Messages</span>
                        </div>
                        <div class="offer-thread-stat">
                            <strong><?= bv_offer_page_h(bv_offer_format_money($offer['latest_offer_price'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?></strong>
                            <span>Latest</span>
                        </div>
                        <div class="offer-thread-stat">
                            <strong><?= bv_offer_page_h(bv_offer_format_money($offer['agreed_price'] ?? null, (string) ($offer['currency'] ?? 'USD'))); ?></strong>
                            <span>Agreed</span>
                        </div>
                        <div class="offer-thread-stat">
                            <strong><?= $activeToken && !empty($activeToken['token']) ? 'Yes' : 'No'; ?></strong>
                            <span>Active Token</span>
                        </div>
                    </div>

3) เปลี่ยนกล่อง token กลางหน้า จากของเดิมนี้:
<?php if ($canCheckout && $activeToken && !empty($activeToken['token'])): ?>
    <div class="offer-token-box" style="margin-bottom:18px;">
        Seller has approved this offer. Buyer can now continue to checkout using the secured offer token.
        <div style="margin-top:10px;">
            <a href="/offer_checkout.php?token=<?= rawurlencode((string) $activeToken['token']); ?>">
                Proceed to offer checkout
            </a>
        </div>
        <?php if (!empty($activeToken['expires_at'])): ?>
            <div style="margin-top:8px;font-weight:800;">
                Expires at: <?= bv_offer_page_h((string) $activeToken['expires_at']); ?>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($activeToken && !empty($activeToken['token'])): ?>
    <div class="offer-token-box" style="margin-bottom:18px;">
        Checkout token exists for this offer.
        <?php if (!empty($activeToken['expires_at'])): ?>
            <div style="margin-top:8px;font-weight:800;">
                Expires at: <?= bv_offer_page_h((string) $activeToken['expires_at']); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

เป็น:

<?php if ($canCheckout && $activeToken && !empty($activeToken['token'])): ?>
    <div class="offer-token-box" style="margin-bottom:18px;">
        Seller has approved this offer. Buyer can now continue using the secured checkout route.
        <div style="margin-top:10px;">
            <a href="<?= bv_offer_page_h($offerAcceptCheckoutUrl); ?>">
                Proceed to accept & checkout
            </a>
        </div>
        <?php if (!empty($activeToken['expires_at'])): ?>
            <div style="margin-top:8px;font-weight:800;">
                Expires at: <?= bv_offer_page_h((string) $activeToken['expires_at']); ?>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($activeToken && !empty($activeToken['token'])): ?>
    <div class="offer-token-box" style="margin-bottom:18px;">
        Checkout token exists for this offer.
        <div style="margin-top:10px;">
            <a href="<?= bv_offer_page_h($offerAcceptCheckoutUrl); ?>">
                Open secured checkout handoff
            </a>
        </div>
        <?php if (!empty($activeToken['expires_at'])): ?>
            <div style="margin-top:8px;font-weight:800;">
                Expires at: <?= bv_offer_page_h((string) $activeToken['expires_at']); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

                    <div class="offer-chat">
                        <div class="offer-messages">
                            <?php if (empty($messages)): ?>
                                <div class="offer-empty">
                                    No messages yet. The room exists, but it is currently quieter than a betta staring contest.
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php
                                    $senderUserId = (int) ($message['sender_user_id'] ?? 0);
                                    $senderRole = (string) ($message['sender_role'] ?? 'system');
                                    $messageClass = bv_offer_page_message_bubble_class($senderRole, $senderUserId, $currentUserId);
                                    $senderLabel = bv_offer_page_sender_label($message, $offer, $userNames);
                                    $messageTypeLabel = bv_offer_page_format_message_type((string) ($message['message_type'] ?? 'message'));
                                    $messageDate = (string) ($message['created_at'] ?? '');
                                    $offerPrice = $message['offer_price'] ?? null;
                                    $messageText = trim((string) ($message['message_text'] ?? ''));
                                    ?>
                                    <article class="offer-message <?= bv_offer_page_h($messageClass); ?>">
                                        <div class="offer-message-head">
                                            <span><?= bv_offer_page_h($senderLabel); ?></span>
                                            <span>•</span>
                                            <span><?= bv_offer_page_h($messageDate); ?></span>
                                            <span class="offer-message-chip"><?= bv_offer_page_h($messageTypeLabel); ?></span>
                                        </div>
                                        <div class="offer-bubble">
                                            <?php if ($messageText !== ''): ?>
                                                <?= nl2br(bv_offer_page_h($messageText)); ?>
                                            <?php else: ?>
                                                <em>No text message.</em>
                                            <?php endif; ?>

                                            <?php if (is_numeric($offerPrice) && (float) $offerPrice > 0): ?>
                                                <div class="offer-bubble-price">
                                                    <?= bv_offer_page_h(bv_offer_format_money($offerPrice, (string) ($offer['currency'] ?? 'USD'))); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="offer-actions">
                        <?php if (!$isTerminal && ($canBuyerReply || $canSellerManage || bv_offer_is_admin_role($currentUserRole))): ?>
                            <form method="post" action="<?= bv_offer_page_h($returnUrl); ?>" class="offer-form">
                                <input type="hidden" name="csrf_token" value="<?= bv_offer_page_h($csrfToken); ?>">
                                <input type="hidden" name="form_started_at" value="<?= (int) $formStartedAt; ?>">
                                <input type="hidden" name="offer_id" value="<?= (int) $offerId; ?>">
                                <input type="hidden" name="return_url" value="<?= bv_offer_page_h($returnUrl); ?>">
                                <input type="hidden" name="action" value="send_message">

                                <div class="offer-hidden-field" aria-hidden="true">
                                    <label for="offer-website">Website</label>
                                    <input type="text" id="offer-website" name="website" value="">
                                </div>

                                <h3 class="offer-section-title" style="font-size:18px;">Send a reply</h3>

                                <div class="offer-form-grid">
                                    <div class="offer-field">
                                        <label for="message_text">Message</label>
                                        <textarea id="message_text" name="message_text" placeholder="Write your message here..."><?= bv_offer_page_h($oldMessageText); ?></textarea>
                                    </div>

                                    <div style="display:grid;gap:14px;">
                                        <div class="offer-field">
                                            <label for="offer_price">Offer Price (optional)</label>
                                            <input type="text" id="offer_price" name="offer_price" inputmode="decimal" placeholder="e.g. 120.00" value="<?= bv_offer_page_h($oldOfferPrice); ?>">
                                        </div>

                                        <div class="offer-field">
                                            <label for="message_type">Message Type</label>
                                            <select id="message_type" name="message_type">
                                                <?php
                                                $defaultMessageType = $oldMessageType !== ''
                                                    ? $oldMessageType
                                                    : ($canSellerManage ? 'counter' : 'offer');
                                                $messageTypeOptions = $canSellerManage
                                                    ? ['counter' => 'Counter Offer', 'message' => 'Message']
                                                    : ['offer' => 'New Offer', 'message' => 'Message'];
                                                foreach ($messageTypeOptions as $value => $label):
                                                ?>
                                                    <option value="<?= bv_offer_page_h($value); ?>" <?= $defaultMessageType === $value ? 'selected' : ''; ?>>
                                                        <?= bv_offer_page_h($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="offer-note">
                                            Buyer typically sends <strong>offer</strong>. Seller typically sends <strong>counter</strong>.  
                                            Plain message works too when the negotiation needs words before numbers.
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($flashErrors)): ?>
                                    <ul class="offer-error-list">
                                        <?php foreach ($flashErrors as $error): ?>
                                            <li><?= bv_offer_page_h((string) $error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="offer-form-row">
                                    <div class="offer-note">
                                        This room stays attached to one listing and one buyer/seller pair. Nice and clean. No wandering offers in the wilderness.
                                    </div>
                                    <button type="submit" class="offer-btn">Send Reply</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if (!$isTerminal && ($canSellerManage || bv_offer_is_admin_role($currentUserRole))): ?>
                            <form method="post" action="<?= bv_offer_page_h($returnUrl); ?>" class="offer-form">
                                <input type="hidden" name="csrf_token" value="<?= bv_offer_page_h($csrfToken); ?>">
                                <input type="hidden" name="form_started_at" value="<?= (int) $formStartedAt; ?>">
                                <input type="hidden" name="offer_id" value="<?= (int) $offerId; ?>">
                                <input type="hidden" name="return_url" value="<?= bv_offer_page_h($returnUrl); ?>">
                                <input type="hidden" name="action" value="accept_offer">

                                <div class="offer-hidden-field" aria-hidden="true">
                                    <label for="offer-website-accept">Website</label>
                                    <input type="text" id="offer-website-accept" name="website" value="">
                                </div>

                                <h3 class="offer-section-title" style="font-size:18px;">Seller Actions</h3>

                                <div class="offer-form-grid">
                                    <div class="offer-field">
                                        <label for="agreed_price">Agreed Price</label>
                                        <input
                                            type="text"
                                            id="agreed_price"
                                            name="agreed_price"
                                            inputmode="decimal"
                                            value="<?= bv_offer_page_h((string) ($offer['latest_offer_price'] ?? $offer['agreed_price'] ?? '')); ?>"
                                            placeholder="e.g. 120.00"
                                        >
                                    </div>

                                    <div class="offer-field">
                                        <label for="approved_message_id">Approved Message ID (optional)</label>
                                        <input
                                            type="text"
                                            id="approved_message_id"
                                            name="approved_message_id"
                                            inputmode="numeric"
                                            value=""
                                            placeholder="Leave blank if not needed"
                                        >
                                    </div>
                                </div>

                                <div class="offer-form-row">
                                    <div class="offer-note">
                                        Accept will generate a 24-hour checkout token for the buyer and move this offer into checkout-ready state.
                                    </div>
                                    <div class="offer-action-row">
                                        <button type="submit" class="offer-btn">Accept Offer</button>
                                    </div>
                                </div>
                            </form>

                            <div class="offer-action-row">
                                <form method="post" action="<?= bv_offer_page_h($returnUrl); ?>" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= bv_offer_page_h($csrfToken); ?>">
                                    <input type="hidden" name="form_started_at" value="<?= (int) $formStartedAt; ?>">
                                    <input type="hidden" name="offer_id" value="<?= (int) $offerId; ?>">
                                    <input type="hidden" name="return_url" value="<?= bv_offer_page_h($returnUrl); ?>">
                                    <input type="hidden" name="action" value="reject_offer">
                                    <button type="submit" class="offer-btn-danger">Reject Offer</button>
                                </form>

                                <form method="post" action="<?= bv_offer_page_h($returnUrl); ?>" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= bv_offer_page_h($csrfToken); ?>">
                                    <input type="hidden" name="form_started_at" value="<?= (int) $formStartedAt; ?>">
                                    <input type="hidden" name="offer_id" value="<?= (int) $offerId; ?>">
                                    <input type="hidden" name="return_url" value="<?= bv_offer_page_h($returnUrl); ?>">
                                    <input type="hidden" name="action" value="cancel_offer">
                                    <button type="submit" class="offer-btn-secondary">Cancel Offer</button>
                                </form>
                            </div>
                        <?php elseif (!$isTerminal && ($canBuyerReply || bv_offer_is_admin_role($currentUserRole))): ?>
                            <div class="offer-action-row">
                                <form method="post" action="<?= bv_offer_page_h($returnUrl); ?>" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= bv_offer_page_h($csrfToken); ?>">
                                    <input type="hidden" name="form_started_at" value="<?= (int) $formStartedAt; ?>">
                                    <input type="hidden" name="offer_id" value="<?= (int) $offerId; ?>">
                                    <input type="hidden" name="return_url" value="<?= bv_offer_page_h($returnUrl); ?>">
                                    <input type="hidden" name="action" value="cancel_offer">
                                    <button type="submit" class="offer-btn-secondary">Cancel Offer</button>
                                </form>

<?php if ($canCheckout && $activeToken && !empty($activeToken['token'])): ?>
    <a class="offer-btn" href="<?= bv_offer_page_h($offerAcceptCheckoutUrl); ?>">
        Accept & Checkout
    </a>
<?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="offer-form">
                                <h3 class="offer-section-title" style="font-size:18px;">Offer Closed</h3>
                                <div class="offer-note">
                                    This offer is in a terminal state and no more replies or status changes are allowed from this page.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>