<?php
declare(strict_types=1);

require_once __DIR__ . '/_listing_bootstrap.php';

if (!function_exists('bv_member_generic_table_columns')) {
    function bv_member_generic_table_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        $key = strtolower($table);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $cols = [];
            if ($stmt) {
                foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                    $field = (string)($row['Field'] ?? '');
                    if ($field !== '') {
                        $cols[$field] = $field;
                    }
                }
            }
            $cache[$key] = $cols;
            return $cols;
        } catch (Throwable $e) {
            $cache[$key] = [];
            return [];
        }
    }
}

if (!function_exists('bv_member_discount_money')) {
    function bv_member_discount_money(float $amount, string $currency = 'USD'): string
    {
        if (function_exists('money')) {
            try {
                return (string) money($amount, $currency);
            } catch (Throwable $e) {
                // fall through
            }
        }
        return number_format($amount, 2) . ' ' . strtoupper(trim($currency) !== '' ? $currency : 'USD');
    }
}

if (!function_exists('bv_member_offer_money')) {
    function bv_member_offer_money(float $amount, string $currency = 'USD'): string
    {
        if (function_exists('money')) {
            try {
                return (string) money($amount, $currency);
            } catch (Throwable $e) {
                // fall through
            }
        }
        return number_format($amount, 2) . ' ' . strtoupper(trim($currency) !== '' ? $currency : 'USD');
    }
}

if (!function_exists('bv_member_offer_status_meta')) {
    function bv_member_offer_status_meta(string $status): array
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'open':
                return ['Open', 'blue'];
            case 'seller_accepted':
                return ['Seller Accepted', 'gold'];
            case 'buyer_checkout_ready':
                return ['Checkout Ready', 'green'];
            case 'completed':
                return ['Completed', 'green'];
            case 'expired':
                return ['Expired', 'gray'];
            case 'cancelled':
                return ['Cancelled', 'gray'];
            case 'rejected':
                return ['Rejected', 'gray'];
            default:
                return [ucfirst($status !== '' ? str_replace('_', ' ', $status) : 'Unknown'), 'gray'];
        }
    }
}

if (!function_exists('bv_member_offer_listing_url')) {
    function bv_member_offer_listing_url(array $row): string
    {
        $slug = trim((string)($row['slug'] ?? ''));
        $listingId = (int)($row['listing_id'] ?? 0);

        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }

        return '/listing.php?id=' . $listingId;
    }
}



if (!function_exists('bv_member_sd_e')) {
    function bv_member_sd_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_member_sd_pdo')) {
    function bv_member_sd_pdo(): PDO
    {
        return bv_member_pdo();
    }
}

if (!function_exists('bv_member_sd_seller_id')) {
    function bv_member_sd_seller_id(array $user): int
    {
        return (int)($user['id'] ?? 0);
    }
}

if (!function_exists('bv_member_sd_display_name')) {
    function bv_member_sd_display_name(array $user): string
    {
        $name = trim((string)($user['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return bv_member_user_display_name($user);
    }
}

if (!function_exists('bv_member_sd_is_seller_approved')) {
    function bv_member_sd_is_seller_approved(PDO $pdo, array $user): bool
    {
        return bv_member_is_seller_approved($pdo, $user);
    }
}

if (!function_exists('bv_member_sd_table_exists')) {
    function bv_member_sd_table_exists(PDO $pdo, string $table): bool
    {
        return bv_member_table_exists($pdo, $table);
    }
}

if (!function_exists('bv_member_sd_columns')) {
    function bv_member_sd_columns(PDO $pdo, string $table): array
    {
        return bv_member_generic_table_columns($pdo, $table);
    }
}

if (!function_exists('bv_member_sd_url')) {
    function bv_member_sd_url(string $path, array $params = []): string
    {
        $path = '/' . ltrim($path, '/');
        if (!$params) {
            return $path;
        }
        return $path . '?' . http_build_query($params);
    }
}

if (!function_exists('bv_member_sd_existing_page')) {
    function bv_member_sd_existing_page(array $candidates): string
    {
        static $cache = [];
        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 1)), '/');

        foreach ($candidates as $candidate) {
            $path = '/' . ltrim((string)$candidate, '/');
            if (isset($cache[$path])) {
                if ($cache[$path]) {
                    return $path;
                }
                continue;
            }
            $exists = false;
            $full = $root . $path;
            if (is_file($full)) {
                $exists = true;
            } else {
                $alt = dirname(__DIR__, 1) . $path;
                if (is_file($alt)) {
                    $exists = true;
                }
            }
            $cache[$path] = $exists;
            if ($exists) {
                return $path;
            }
        }

        return '/' . ltrim((string)($candidates[0] ?? ''), '/');
    }
}

if (!function_exists('bv_member_sd_money')) {
    function bv_member_sd_money(float $amount, string $currency = 'USD'): string
    {
        return bv_member_offer_money($amount, $currency);
    }
}

if (!function_exists('bv_member_sd_pick_expr')) {
    function bv_member_sd_pick_expr(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }
        return $fallback;
    }
}

if (!function_exists('bv_member_sd_status_badge')) {
    function bv_member_sd_status_badge(string $status): array
    {
        $status = strtolower(trim($status));
        $label = ucfirst($status !== '' ? str_replace('_', ' ', $status) : 'Unknown');
        $class = 'gray';

        if (in_array($status, ['paid', 'processing', 'confirmed', 'packing', 'shipped', 'completed', 'success'], true)) {
            $class = 'green';
        } elseif (in_array($status, ['pending', 'new', 'awaiting_payment', 'open', 'requested', 'submitted'], true)) {
            $class = 'gold';
        } elseif (in_array($status, ['refunded', 'approved', 'accepted', 'handled'], true)) {
            $class = 'blue';
        }

        return [$label, $class];
    }
}

$pdo = bv_member_pdo();
$user = bv_member_require_login();
$flash = bv_member_flash_get();
$displayName = bv_member_user_display_name($user);
$role = strtolower(trim((string)($user['role'] ?? 'user')));
$sellerStatus = bv_member_resolve_seller_status($pdo, $user);
$isSellerApproved = bv_member_is_seller_approved($pdo, $user);
$listingCols = bv_member_table_exists($pdo, 'listings') ? bv_member_listing_columns($pdo) : [];
$listingStats = ['total' => 0, 'active' => 0, 'draft' => 0, 'sold' => 0];
$recentListings = [];

$discountStats = [
    'active_discounts' => 0,
    'discounted_orders' => 0,
    'discount_total' => 0.00,
    'discounted_subtotal' => 0.00,
    'currency' => 'USD',
];

$recentDiscountOrders = [];
$discountFeatureAvailable = false;
$orderDiscountLogExists = false;
$sellerDiscountsExists = false;
$sellerDiscountCols = [];
$orderDiscountLogCols = [];

$offerStats = [
    'buyer_total' => 0,
    'buyer_open' => 0,
    'buyer_checkout_ready' => 0,
    'buyer_completed' => 0,
    'seller_total' => 0,
    'seller_open' => 0,
    'seller_checkout_ready' => 0,
    'seller_completed' => 0,
    'active_tokens' => 0,
];

$offerFeatureAvailable = false;
$recentBuyerOffers = [];
$recentSellerOffers = [];
$offerError = '';

$sellerDashboardStats = [
    'new_orders' => 0,
    'paid_orders' => 0,
    'refund_pending' => 0,
    'refund_processed' => 0,
    'open_offers' => 0,
    'this_month_sales' => 0.0,
    'currency' => 'USD',
];
$sellerRecentOrders = [];
$sellerRecentRefunds = [];
$sellerRecentOfferThreads = [];
$sellerSalesMonthly = [];

$userId = (int)($user['id'] ?? 0);
$buyerProfile = null;

if ($userId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                first_name,
                last_name,
                email,
                role,
                account_status,
                phone,
                whatsapp,
                address_line1,
                address_line2,
                road,
                subdistrict,
                district,
                province,
                postal_code,
                country,
                created_at,
                updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $buyerProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('member index buyer profile load failed: ' . $e->getMessage());
    }
}

if ($isSellerApproved && !empty($listingCols['seller_id'])) {
    $listingStats = bv_member_listing_counts($pdo, $listingCols, (int)$user['id']);

    $select = [
        '`' . $listingCols['id'] . '` AS id',
        '`' . $listingCols['title'] . '` AS title',
        !empty($listingCols['slug']) ? '`' . $listingCols['slug'] . '` AS slug' : 'NULL AS slug',
        !empty($listingCols['status']) ? '`' . $listingCols['status'] . '` AS status' : 'NULL AS status',
        !empty($listingCols['sale_status']) ? '`' . $listingCols['sale_status'] . '` AS sale_status' : 'NULL AS sale_status',
        !empty($listingCols['updated_at']) ? '`' . $listingCols['updated_at'] . '` AS updated_at' : 'NULL AS updated_at',
    ];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM `listings` WHERE `' . $listingCols['seller_id'] . '` = :seller_id';
    $sql .= !empty($listingCols['updated_at']) ? ' ORDER BY `' . $listingCols['updated_at'] . '` DESC' : ' ORDER BY `' . $listingCols['id'] . '` DESC';
    $sql .= ' LIMIT 5';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':seller_id' => (int)$user['id']]);
    $recentListings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($isSellerApproved && $userId > 0) {
    $sellerDiscountsExists = bv_member_table_exists($pdo, 'seller_discounts');
    $orderDiscountLogExists = bv_member_table_exists($pdo, 'order_discount_log');

    if ($sellerDiscountsExists) {
        $sellerDiscountCols = bv_member_generic_table_columns($pdo, 'seller_discounts');
    }

    if ($orderDiscountLogExists) {
        $orderDiscountLogCols = bv_member_generic_table_columns($pdo, 'order_discount_log');
    }

    $discountFeatureAvailable = $sellerDiscountsExists || $orderDiscountLogExists;

    if ($sellerDiscountsExists && !empty($sellerDiscountCols['seller_id'])) {
        try {
            $where = [];
            $params = [':seller_id' => $userId];

            $where[] = '`seller_id` = :seller_id';

            if (!empty($sellerDiscountCols['status'])) {
                $where[] = "`status` = 'active'";
            } elseif (!empty($sellerDiscountCols['is_active'])) {
                $where[] = '`is_active` = 1';
            }

            if (!empty($sellerDiscountCols['starts_at'])) {
                $where[] = '(`starts_at` IS NULL OR `starts_at` = "0000-00-00 00:00:00" OR `starts_at` <= NOW())';
            }

            if (!empty($sellerDiscountCols['ends_at'])) {
                $where[] = '(`ends_at` IS NULL OR `ends_at` = "0000-00-00 00:00:00" OR `ends_at` >= NOW())';
            }

            $sql = 'SELECT COUNT(*) FROM `seller_discounts`';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $discountStats['active_discounts'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('member index active discounts load failed: ' . $e->getMessage());
        }
    }

    if ($orderDiscountLogExists) {
        try {
            $where = [];
            $params = [];
            $sellerScoped = false;

            if (!empty($orderDiscountLogCols['seller_id'])) {
                $where[] = '`seller_id` = :seller_id';
                $params[':seller_id'] = $userId;
                $sellerScoped = true;
            } elseif (!empty($orderDiscountLogCols['listing_seller_id'])) {
                $where[] = '`listing_seller_id` = :seller_id';
                $params[':seller_id'] = $userId;
                $sellerScoped = true;
            }

            if ($sellerScoped) {
                $discountCountExpr = !empty($orderDiscountLogCols['order_id']) ? 'COUNT(DISTINCT `order_id`)' : 'COUNT(*)';

                $discountSumExpr = '0';
                if (!empty($orderDiscountLogCols['discount_amount'])) {
                    $discountSumExpr = 'COALESCE(SUM(`discount_amount`), 0)';
                } elseif (!empty($orderDiscountLogCols['seller_discount_total'])) {
                    $discountSumExpr = 'COALESCE(SUM(`seller_discount_total`), 0)';
                }

                $subtotalSumExpr = '0';
                if (!empty($orderDiscountLogCols['subtotal_before_discount'])) {
                    $subtotalSumExpr = 'COALESCE(SUM(`subtotal_before_discount`), 0)';
                } elseif (!empty($orderDiscountLogCols['subtotal'])) {
                    $subtotalSumExpr = 'COALESCE(SUM(`subtotal`), 0)';
                } elseif (!empty($orderDiscountLogCols['order_subtotal'])) {
                    $subtotalSumExpr = 'COALESCE(SUM(`order_subtotal`), 0)';
                }

                $sql = "
                    SELECT
                        {$discountCountExpr} AS discounted_orders,
                        {$discountSumExpr} AS discount_total,
                        {$subtotalSumExpr} AS discounted_subtotal
                    FROM `order_discount_log`
                ";

                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $discountStats['discounted_orders'] = (int)($row['discounted_orders'] ?? 0);
                $discountStats['discount_total'] = (float)($row['discount_total'] ?? 0);
                $discountStats['discounted_subtotal'] = (float)($row['discounted_subtotal'] ?? 0);
            }

            if (!empty($orderDiscountLogCols['currency'])) {
                $whereCurrency = $where;
                $paramsCurrency = $params;
                $sqlCurrency = 'SELECT `currency` FROM `order_discount_log`';
                if ($whereCurrency) {
                    $sqlCurrency .= ' WHERE ' . implode(' AND ', $whereCurrency);
                }
                $orderByCurrency = [];
                if (!empty($orderDiscountLogCols['created_at'])) {
                    $orderByCurrency[] = '`created_at` DESC';
                }
                if (!empty($orderDiscountLogCols['id'])) {
                    $orderByCurrency[] = '`id` DESC';
                }
                if ($orderByCurrency) {
                    $sqlCurrency .= ' ORDER BY ' . implode(', ', $orderByCurrency);
                }
                $sqlCurrency .= ' LIMIT 1';

                $stmtCurrency = $pdo->prepare($sqlCurrency);
                $stmtCurrency->execute($paramsCurrency);
                $foundCurrency = trim((string)$stmtCurrency->fetchColumn());
                if ($foundCurrency !== '') {
                    $discountStats['currency'] = strtoupper($foundCurrency);
                }
            }
        } catch (Throwable $e) {
            error_log('member index discount stats load failed: ' . $e->getMessage());
        }

        try {
            $where = [];
            $params = [];
            $sellerScoped = false;

            if (!empty($orderDiscountLogCols['seller_id'])) {
                $where[] = '`seller_id` = :seller_id';
                $params[':seller_id'] = $userId;
                $sellerScoped = true;
            } elseif (!empty($orderDiscountLogCols['listing_seller_id'])) {
                $where[] = '`listing_seller_id` = :seller_id';
                $params[':seller_id'] = $userId;
                $sellerScoped = true;
            }

            if ($sellerScoped) {
                $selectParts = [];

                $selectParts[] = !empty($orderDiscountLogCols['id']) ? '`id` AS id' : '0 AS id';
                $selectParts[] = !empty($orderDiscountLogCols['order_id']) ? '`order_id` AS order_id' : 'NULL AS order_id';
                $selectParts[] = !empty($orderDiscountLogCols['order_code']) ? '`order_code` AS order_code' : 'NULL AS order_code';

                if (!empty($orderDiscountLogCols['buyer_name'])) {
                    $selectParts[] = '`buyer_name` AS buyer_name';
                } elseif (!empty($orderDiscountLogCols['customer_name'])) {
                    $selectParts[] = '`customer_name` AS buyer_name';
                } elseif (!empty($orderDiscountLogCols['buyer_email'])) {
                    $selectParts[] = '`buyer_email` AS buyer_name';
                } else {
                    $selectParts[] = 'NULL AS buyer_name';
                }

                if (!empty($orderDiscountLogCols['subtotal_before_discount'])) {
                    $selectParts[] = '`subtotal_before_discount` AS subtotal_before_discount';
                } elseif (!empty($orderDiscountLogCols['subtotal'])) {
                    $selectParts[] = '`subtotal` AS subtotal_before_discount';
                } elseif (!empty($orderDiscountLogCols['order_subtotal'])) {
                    $selectParts[] = '`order_subtotal` AS subtotal_before_discount';
                } else {
                    $selectParts[] = '0 AS subtotal_before_discount';
                }

                if (!empty($orderDiscountLogCols['discount_amount'])) {
                    $selectParts[] = '`discount_amount` AS discount_amount';
                } elseif (!empty($orderDiscountLogCols['seller_discount_total'])) {
                    $selectParts[] = '`seller_discount_total` AS discount_amount';
                } else {
                    $selectParts[] = '0 AS discount_amount';
                }

                if (!empty($orderDiscountLogCols['subtotal_after_discount'])) {
                    $selectParts[] = '`subtotal_after_discount` AS subtotal_after_discount';
                } elseif (!empty($orderDiscountLogCols['net_subtotal'])) {
                    $selectParts[] = '`net_subtotal` AS subtotal_after_discount';
                } elseif (!empty($orderDiscountLogCols['subtotal_before_discount']) && !empty($orderDiscountLogCols['discount_amount'])) {
                    $selectParts[] = '(`subtotal_before_discount` - `discount_amount`) AS subtotal_after_discount';
                } else {
                    $selectParts[] = '0 AS subtotal_after_discount';
                }

                $selectParts[] = !empty($orderDiscountLogCols['currency']) ? '`currency` AS currency' : '\'' . str_replace("'", "\\'", $discountStats['currency']) . '\' AS currency';
                $selectParts[] = !empty($orderDiscountLogCols['created_at']) ? '`created_at` AS created_at' : 'NULL AS created_at';

                $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM `order_discount_log`';
                if ($where) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }

                $orderBy = [];
                if (!empty($orderDiscountLogCols['created_at'])) {
                    $orderBy[] = '`created_at` DESC';
                }
                if (!empty($orderDiscountLogCols['id'])) {
                    $orderBy[] = '`id` DESC';
                }
                if (!$orderBy && !empty($orderDiscountLogCols['order_id'])) {
                    $orderBy[] = '`order_id` DESC';
                }
                if ($orderBy) {
                    $sql .= ' ORDER BY ' . implode(', ', $orderBy);
                }
                $sql .= ' LIMIT 5';

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $recentDiscountOrders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {
            error_log('member index recent discount orders load failed: ' . $e->getMessage());
        }
    }
}

if ($userId > 0) {
    $listingOffersExists = bv_member_table_exists($pdo, 'listing_offers');
    $offerTokensExists = bv_member_table_exists($pdo, 'listing_offer_checkout_tokens');

    $offerFeatureAvailable = $listingOffersExists;

    if ($listingOffersExists) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS buyer_total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS buyer_open,
                    SUM(CASE WHEN status IN ('seller_accepted', 'buyer_checkout_ready') THEN 1 ELSE 0 END) AS buyer_checkout_ready,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS buyer_completed
                FROM `listing_offers`
                WHERE `buyer_user_id` = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $offerStats['buyer_total'] = (int)($row['buyer_total'] ?? 0);
            $offerStats['buyer_open'] = (int)($row['buyer_open'] ?? 0);
            $offerStats['buyer_checkout_ready'] = (int)($row['buyer_checkout_ready'] ?? 0);
            $offerStats['buyer_completed'] = (int)($row['buyer_completed'] ?? 0);
        } catch (Throwable $e) {
            error_log('member index buyer offer stats load failed: ' . $e->getMessage());
            $offerError = $e->getMessage();
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    o.id,
                    o.listing_id,
                    o.status,
                    o.currency,
                    o.latest_offer_price,
                    o.agreed_price,
                    o.updated_at,
                    l.title,
                    l.slug
                FROM `listing_offers` o
                LEFT JOIN `listings` l ON l.id = o.listing_id
                WHERE o.buyer_user_id = :user_id
                ORDER BY o.updated_at DESC, o.id DESC
                LIMIT 5
            ");
            $stmt->execute([':user_id' => $userId]);
            $recentBuyerOffers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('member index recent buyer offers load failed: ' . $e->getMessage());
        }

        if ($isSellerApproved) {
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS seller_total,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS seller_open,
                        SUM(CASE WHEN status IN ('seller_accepted', 'buyer_checkout_ready') THEN 1 ELSE 0 END) AS seller_checkout_ready,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS seller_completed
                    FROM `listing_offers`
                    WHERE `seller_user_id` = :user_id
                ");
                $stmt->execute([':user_id' => $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $offerStats['seller_total'] = (int)($row['seller_total'] ?? 0);
                $offerStats['seller_open'] = (int)($row['seller_open'] ?? 0);
                $offerStats['seller_checkout_ready'] = (int)($row['seller_checkout_ready'] ?? 0);
                $offerStats['seller_completed'] = (int)($row['seller_completed'] ?? 0);
            } catch (Throwable $e) {
                error_log('member index seller offer stats load failed: ' . $e->getMessage());
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT
                        o.id,
                        o.listing_id,
                        o.status,
                        o.currency,
                        o.latest_offer_price,
                        o.agreed_price,
                        o.updated_at,
                        l.title,
                        l.slug
                    FROM `listing_offers` o
                    LEFT JOIN `listings` l ON l.id = o.listing_id
                    WHERE o.seller_user_id = :user_id
                    ORDER BY o.updated_at DESC, o.id DESC
                    LIMIT 5
                ");
                $stmt->execute([':user_id' => $userId]);
                $recentSellerOffers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log('member index recent seller offers load failed: ' . $e->getMessage());
            }
        }
    }

    if ($listingOffersExists && $offerTokensExists) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM `listing_offer_checkout_tokens` t
                INNER JOIN `listing_offers` o ON o.id = t.offer_id
                WHERE o.buyer_user_id = :user_id
                  AND t.status = 'active'
            ");
            $stmt->execute([':user_id' => $userId]);
            $offerStats['active_tokens'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('member index active offer tokens load failed: ' . $e->getMessage());
        }
    }
}


if ($isSellerApproved && $userId > 0) {
    $sellerId = bv_member_sd_seller_id($user);

    $orderPage = bv_member_sd_existing_page(['/seller/order_detail.php', '/member/order_detail.php', '/member/order_view.php', '/seller/orders.php']);
    $refundPage = bv_member_sd_existing_page(['/seller/refund_view.php', '/seller/refunds.php']);
    $offerPage = bv_member_sd_existing_page(['/offer.php', '/seller/offers.php']);

    try {
        if (bv_member_sd_table_exists($pdo, 'orders') && bv_member_sd_table_exists($pdo, 'order_items') && bv_member_sd_table_exists($pdo, 'listings')) {
            $orderCols = bv_member_sd_columns($pdo, 'orders');
            $orderItemCols = bv_member_sd_columns($pdo, 'order_items');
            $listingColsForOrders = bv_member_sd_columns($pdo, 'listings');
            $userCols = bv_member_sd_table_exists($pdo, 'users') ? bv_member_sd_columns($pdo, 'users') : [];

            $orderCodeExpr = !empty($orderCols['order_code']) ? 'o.order_code' : ( !empty($orderCols['id']) ? "CONCAT('ORD-', o.id)" : "'-'");
            $buyerNameCandidates = [];
            if (!empty($orderCols['buyer_name'])) $buyerNameCandidates[] = 'o.buyer_name';
            if (!empty($orderCols['customer_name'])) $buyerNameCandidates[] = 'o.customer_name';
            if (!empty($userCols['display_name'])) $buyerNameCandidates[] = 'u.display_name';
            if (!empty($userCols['first_name'])) $buyerNameCandidates[] = 'u.first_name';
            if (!empty($userCols['email'])) $buyerNameCandidates[] = 'u.email';
            $buyerNameExpr = bv_member_sd_pick_expr($buyerNameCandidates, "'Buyer'");

            $qtyCandidates = [];
            if (!empty($orderItemCols['quantity'])) $qtyCandidates[] = 'oi.quantity';
            if (!empty($orderItemCols['qty'])) $qtyCandidates[] = 'oi.qty';
            $qtyExpr = bv_member_sd_pick_expr($qtyCandidates, '1');

            $lineTotalCandidates = [];
            if (!empty($orderItemCols['total_price'])) $lineTotalCandidates[] = 'oi.total_price';
            if (!empty($orderItemCols['line_total'])) $lineTotalCandidates[] = 'oi.line_total';
            if (!empty($orderItemCols['subtotal'])) $lineTotalCandidates[] = 'oi.subtotal';
            $unitCandidates = [];
            if (!empty($orderItemCols['price'])) $unitCandidates[] = 'oi.price';
            if (!empty($orderItemCols['unit_price'])) $unitCandidates[] = 'oi.unit_price';
            if ($unitCandidates) {
                $lineTotalCandidates[] = '(COALESCE(' . implode(', ', $unitCandidates) . ', 0) * COALESCE(' . $qtyExpr . ', 1))';
            }
            $lineTotalExpr = bv_member_sd_pick_expr($lineTotalCandidates, '0');

            $orderStatusExpr = !empty($orderCols['status']) ? 'o.status' : "'pending'";
            $paymentStatusExpr = !empty($orderCols['payment_status']) ? 'o.payment_status' : ( !empty($orderCols['payment_state']) ? 'o.payment_state' : "'pending'");
            $currencyExpr = !empty($orderCols['currency']) ? 'o.currency' : ( !empty($orderItemCols['currency']) ? 'oi.currency' : "'USD'");
            $createdAtExpr = !empty($orderCols['created_at']) ? 'o.created_at' : ( !empty($orderCols['updated_at']) ? 'o.updated_at' : 'NOW()');
            $listingTitleExpr = (!empty($listingColsForOrders['title']) ? 'l.title' : null);
            if ($listingTitleExpr === null) {
                $listingTitleExpr = (!empty($orderItemCols['title_snapshot']) ? 'oi.title_snapshot' : "CONCAT('Listing #', oi.listing_id)");
            }

            $buyerJoinExpr = 'o.user_id';
            if (!empty($orderCols['buyer_user_id'])) {
                $buyerJoinExpr = 'o.buyer_user_id';
            } elseif (!empty($orderCols['member_id'])) {
                $buyerJoinExpr = 'o.member_id';
            }

            $sellerOwnerWhere = [];
            if (!empty($listingColsForOrders['seller_id'])) {
                $sellerOwnerWhere[] = 'l.seller_id = :seller_id';
            }
            if (!empty($orderItemCols['seller_id'])) {
                $sellerOwnerWhere[] = 'oi.seller_id = :seller_id';
            }
            if (!$sellerOwnerWhere) {
                $sellerOwnerWhere[] = 'l.seller_id = :seller_id';
            }

            $sql = "
                SELECT
                    o.id AS order_id,
                    MAX(" . $orderCodeExpr . ") AS order_code,
                    MAX(COALESCE(" . $buyerNameExpr . ", 'Buyer')) AS buyer_name,
                    MAX(COALESCE(" . $listingTitleExpr . ", CONCAT('Listing #', oi.listing_id))) AS listing_title,
                    SUM(COALESCE(" . $qtyExpr . ", 1)) AS qty,
                    SUM(COALESCE(" . $lineTotalExpr . ", 0)) AS total,
                    MAX(COALESCE(" . $orderStatusExpr . ", 'pending')) AS order_status,
                    MAX(COALESCE(" . $paymentStatusExpr . ", 'pending')) AS payment_status,
                    MAX(COALESCE(" . $currencyExpr . ", 'USD')) AS currency,
                    MAX(" . $createdAtExpr . ") AS created_at
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                INNER JOIN listings l ON l.id = oi.listing_id
                 LEFT JOIN users u ON u.id = " . $buyerJoinExpr . "
                WHERE (" . implode(' OR ', $sellerOwnerWhere) . ")
                GROUP BY o.id
               ORDER BY MAX(" . $createdAtExpr . ") DESC, o.id DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':seller_id' => $sellerId]);
            $sellerRecentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($sellerRecentOrders as $row) {
                $oStatus = strtolower(trim((string)($row['order_status'] ?? '')));
                $payStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
				if (in_array($oStatus, ['pending', 'pending_payment', 'reserved', 'paid-awaiting-verify'], true)) {
                    $sellerDashboardStats['new_orders']++;
                }
                if (in_array($payStatus, ['paid', 'captured', 'completed', 'success'], true)) {
                    $sellerDashboardStats['paid_orders']++;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('member index seller recent orders load failed: ' . $e->getMessage());
    }

    try {
        if (bv_member_sd_table_exists($pdo, 'order_refunds') && bv_member_sd_table_exists($pdo, 'order_refund_items') && bv_member_sd_table_exists($pdo, 'order_items') && bv_member_sd_table_exists($pdo, 'listings')) {
             $refundCols = bv_member_sd_columns($pdo, 'order_refunds');
            $refundItemCols = bv_member_sd_columns($pdo, 'order_refund_items');
            $orderItemCols = bv_member_sd_columns($pdo, 'order_items');
            $orderCols = bv_member_sd_columns($pdo, 'orders');
            $listingColsForRefunds = bv_member_sd_columns($pdo, 'listings');

            $refundCodeExpr = !empty($refundCols['refund_code']) ? 'r.refund_code' : "CONCAT('RF-', r.id)";
            $orderCodeExpr = !empty($orderCols['order_code']) ? 'o.order_code' : "CONCAT('ORD-', o.id)";
            $requestedAtExpr = !empty($refundCols['requested_at']) ? 'r.requested_at' : ( !empty($refundCols['created_at']) ? 'r.created_at' : 'NOW()');
            $approvedAtExpr = !empty($refundCols['approved_at']) ? 'r.approved_at' : ( !empty($refundCols['processing_started_at']) ? 'r.processing_started_at' : ( !empty($refundCols['updated_at']) ? 'r.updated_at' : 'NULL'));
            $refundStatusExpr = !empty($refundCols['status']) ? 'r.status' : "'pending'";
            $requestedAmountExpr = !empty($refundCols['requested_refund_amount']) ? 'r.requested_refund_amount' : ( !empty($refundCols['requested_amount']) ? 'r.requested_amount' : '0');
            $approvedAmountExpr = !empty($refundCols['approved_refund_amount']) ? 'r.approved_refund_amount' : ( !empty($refundCols['approved_amount']) ? 'r.approved_amount' : '0');
            $refundCurrencyExpr = !empty($refundCols['currency']) ? 'r.currency' : ( !empty($orderCols['currency']) ? 'o.currency' : "'USD'");

            $refundOwnershipWhere = [];
            if (!empty($orderItemCols['seller_id'])) {
                $refundOwnershipWhere[] = 'oi.seller_id = :seller_id';
            }
            if (!empty($listingColsForRefunds['seller_id'])) {
                $refundOwnershipWhere[] = 'l.seller_id = :seller_id';
            }
            if (!$refundOwnershipWhere) {
                $refundOwnershipWhere[] = 'l.seller_id = :seller_id';
            }
           
			$sql = "
                SELECT
                    r.id AS refund_id,
                     MAX(" . $refundCodeExpr . ") AS refund_code,
                    MAX(COALESCE(r.order_id, o.id)) AS order_id,
                    MAX(" . $orderCodeExpr . ") AS order_code,
                    MAX(" . $requestedAtExpr . ") AS requested_at,
                    MAX(" . $approvedAtExpr . ") AS approved_at,
                    MAX(COALESCE(" . $refundStatusExpr . ", 'pending')) AS refund_status,
                    MAX(COALESCE(" . $requestedAmountExpr . ", 0)) AS requested_refund_amount,
                    MAX(COALESCE(" . $approvedAmountExpr . ", 0)) AS approved_refund_amount,
                    MAX(COALESCE(" . $refundCurrencyExpr . ", 'USD')) AS currency                  
                FROM order_refunds r
                INNER JOIN order_refund_items ri ON ri.refund_id = r.id
                 LEFT JOIN order_items oi ON oi.id = ri.order_item_id
                LEFT JOIN listings l ON l.id = COALESCE(ri.listing_id, oi.listing_id)
                LEFT JOIN orders o ON o.id = oi.order_id
                 WHERE (" . implode(' OR ', $refundOwnershipWhere) . ")
                GROUP BY r.id
              ORDER BY MAX(" . $requestedAtExpr . ") DESC, r.id DESC 
                LIMIT 10
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':seller_id' => $sellerId]);
            $sellerRecentRefunds = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($sellerRecentRefunds as $row) {
                $rStatus = strtolower(trim((string)($row['refund_status'] ?? '')));
                if (in_array($rStatus, ['pending_approval', 'approved', 'processing'], true)) {
                    $sellerDashboardStats['refund_pending']++;
                } elseif (in_array($rStatus, ['refunded', 'rejected', 'failed', 'cancelled'], true)) {	
                    $sellerDashboardStats['refund_processed']++;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('member index seller refunds load failed: ' . $e->getMessage());
    }

    try {
        if (bv_member_sd_table_exists($pdo, 'listing_offers')) {
            $offerCols = bv_member_sd_columns($pdo, 'listing_offers');
            $listingColsForOffers = bv_member_sd_columns($pdo, 'listings');
            $userCols = bv_member_sd_table_exists($pdo, 'users') ? bv_member_sd_columns($pdo, 'users') : [];
            $offerOwnerWhere = [];
            if (!empty($offerCols['seller_user_id'])) {
                $offerOwnerWhere[] = 'o.seller_user_id = :seller_id';
            }
            if (!empty($listingColsForOffers['seller_id'])) {
                $offerOwnerWhere[] = 'l.seller_id = :seller_id';
            }
            if (!$offerOwnerWhere) {
                $offerOwnerWhere[] = 'o.seller_user_id = :seller_id';
            }
            $offerCodeExpr = !empty($offerCols['offer_code']) ? 'o.offer_code' : "CONCAT('OFF-', o.id)";
            $offerPriceExpr = !empty($offerCols['latest_offer_price']) ? 'o.latest_offer_price' : ( !empty($offerCols['offer_price']) ? 'o.offer_price' : ( !empty($offerCols['agreed_price']) ? 'o.agreed_price' : '0'));
            $offerCurrencyExpr = !empty($offerCols['currency']) ? 'o.currency' : "'USD'";
            $offerStatusExpr = !empty($offerCols['status']) ? 'o.status' : "'open'";
            $offerUpdatedExpr = !empty($offerCols['updated_at']) ? 'o.updated_at' : ( !empty($offerCols['created_at']) ? 'o.created_at' : 'NOW()');

            $buyerNameCandidates = [];
            if (!empty($userCols['display_name'])) $buyerNameCandidates[] = 'u.display_name';
            if (!empty($userCols['first_name'])) $buyerNameCandidates[] = 'u.first_name';
            if (!empty($userCols['email'])) $buyerNameCandidates[] = 'u.email';
            $buyerNameExpr = bv_member_sd_pick_expr($buyerNameCandidates, "'Buyer'");
            $listingTitleExpr = !empty($listingColsForOffers['title']) ? 'l.title' : "CONCAT('Listing #', o.listing_id)";			
            $sql = "
                SELECT
                    o.id AS offer_id,
                    o.listing_id,
                    MAX(" . $offerCodeExpr . ") AS offer_code,
                    MAX(COALESCE(" . $offerPriceExpr . ", 0)) AS offer_price,
                    MAX(COALESCE(" . $offerCurrencyExpr . ", 'USD')) AS currency,
                    MAX(COALESCE(" . $offerStatusExpr . ", 'open')) AS offer_status,
                    MAX(COALESCE(" . $buyerNameExpr . ", 'Buyer')) AS buyer_name,
                    MAX(COALESCE(" . $listingTitleExpr . ", CONCAT('Listing #', o.listing_id))) AS listing_title,
                    MAX(" . $offerUpdatedExpr . ") AS updated_at 
                FROM listing_offers o
                LEFT JOIN listings l ON l.id = o.listing_id
                LEFT JOIN users u ON u.id = o.buyer_user_id
               WHERE (" . implode(' OR ', $offerOwnerWhere) . ")
                GROUP BY o.id, o.listing_id
              ORDER BY MAX(" . $offerUpdatedExpr . ") DESC, o.id DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':seller_id' => $sellerId]);
            $sellerRecentOfferThreads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($sellerRecentOfferThreads as $row) {
                $status = strtolower(trim((string)($row['offer_status'] ?? '')));
                if (in_array($status, ['open', 'seller_accepted', 'buyer_checkout_ready'], true)) {
                    $sellerDashboardStats['open_offers']++;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('member index seller offers dashboard load failed: ' . $e->getMessage());
    }

    try {
        if (bv_member_sd_table_exists($pdo, 'orders') && bv_member_sd_table_exists($pdo, 'order_items') && bv_member_sd_table_exists($pdo, 'listings')) {
            $orderCols = bv_member_sd_columns($pdo, 'orders');
            $orderItemCols = bv_member_sd_columns($pdo, 'order_items');
            $listingColsForSales = bv_member_sd_columns($pdo, 'listings');

            $salesMonthExpr = !empty($orderCols['paid_at']) ? 'o.paid_at' : ( !empty($orderCols['updated_at']) ? 'o.updated_at' : 'o.created_at');
            $qtyCandidates = [];
            if (!empty($orderItemCols['quantity'])) $qtyCandidates[] = 'oi.quantity';
            if (!empty($orderItemCols['qty'])) $qtyCandidates[] = 'oi.qty';
            $qtyExpr = bv_member_sd_pick_expr($qtyCandidates, '1');
            $lineTotalCandidates = [];
            if (!empty($orderItemCols['total_price'])) $lineTotalCandidates[] = 'oi.total_price';
            if (!empty($orderItemCols['line_total'])) $lineTotalCandidates[] = 'oi.line_total';
            if (!empty($orderItemCols['subtotal'])) $lineTotalCandidates[] = 'oi.subtotal';
            $unitCandidates = [];
            if (!empty($orderItemCols['price'])) $unitCandidates[] = 'oi.price';
            if (!empty($orderItemCols['unit_price'])) $unitCandidates[] = 'oi.unit_price';
            if ($unitCandidates) {
                $lineTotalCandidates[] = '(COALESCE(' . implode(', ', $unitCandidates) . ', 0) * COALESCE(' . $qtyExpr . ', 1))';
            }
            $lineTotalExpr = bv_member_sd_pick_expr($lineTotalCandidates, '0');
            $salesCurrencyExpr = !empty($orderCols['currency']) ? 'o.currency' : ( !empty($orderItemCols['currency']) ? 'oi.currency' : "'USD'");

            $salesOwnerWhere = [];
            if (!empty($listingColsForSales['seller_id'])) {
                $salesOwnerWhere[] = 'l.seller_id = :seller_id';
            }
            if (!empty($orderItemCols['seller_id'])) {
                $salesOwnerWhere[] = 'oi.seller_id = :seller_id';
            }
            if (!$salesOwnerWhere) {
                $salesOwnerWhere[] = 'l.seller_id = :seller_id';
            }
			
            $sql = "
                SELECT
                    DATE_FORMAT(" . $salesMonthExpr . ", '%Y-%m') AS sales_month,
                    COUNT(DISTINCT o.id) AS orders_count,
                   SUM(COALESCE(" . $lineTotalExpr . ", 0)) AS gross_sales,
                    MAX(COALESCE(" . $salesCurrencyExpr . ", 'USD')) AS currency
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                INNER JOIN listings l ON l.id = oi.listing_id
                WHERE (" . implode(' OR ', $salesOwnerWhere) . ")
                  AND LOWER(COALESCE(o.status, '')) IN ('paid','processing','confirmed','packing','shipped','completed','refunded')
               GROUP BY DATE_FORMAT(" . $salesMonthExpr . ", '%Y-%m')
                ORDER BY sales_month DESC
                LIMIT 6
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':seller_id' => $sellerId]);
            $sellerSalesMonthly = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($sellerSalesMonthly) {
                $first = $sellerSalesMonthly[0];
                $sellerDashboardStats['this_month_sales'] = (float)($first['gross_sales'] ?? 0);
                $sellerDashboardStats['currency'] = (string)($first['currency'] ?? 'USD');
            }
        }
    } catch (Throwable $e) {
        error_log('member index seller sales monthly load failed: ' . $e->getMessage());
    }
}

$profileRequiredFields = [
    'phone',
    'address_line1',
    'subdistrict',
    'district',
    'province',
    'postal_code',
    'country',
];

$profileFilled = 0;
$profileTotal = count($profileRequiredFields);

foreach ($profileRequiredFields as $field) {
    if (!empty(trim((string)($buyerProfile[$field] ?? '')))) {
        $profileFilled++;
    }
}

$profileIsComplete = ($profileFilled === $profileTotal);

$buyerFullAddressParts = array_filter([
    trim((string)($buyerProfile['address_line1'] ?? '')),
    trim((string)($buyerProfile['address_line2'] ?? '')),
    trim((string)($buyerProfile['road'] ?? '')),
    trim((string)($buyerProfile['subdistrict'] ?? '')),
    trim((string)($buyerProfile['district'] ?? '')),
    trim((string)($buyerProfile['province'] ?? '')),
    trim((string)($buyerProfile['postal_code'] ?? '')),
    trim((string)($buyerProfile['country'] ?? '')),
], static function ($value): bool {
    return $value !== '';
});

$buyerFullAddress = $buyerFullAddressParts ? implode(', ', $buyerFullAddressParts) : 'No address added yet';

bv_member_page_begin('My Account | Bettavaro', 'Bettavaro member and seller dashboard.');
?>
<style>
.member-wrap{max-width:1240px;margin:0 auto;padding:34px 16px 52px}.member-shell{display:grid;gap:18px}.panel{border:1px solid rgba(229,201,138,.14);border-radius:24px;background:linear-gradient(180deg,rgba(12,28,22,.96),rgba(10,20,16,.98));box-shadow:0 16px 44px rgba(0,0,0,.24)}.panel-body{padding:22px}.hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.hero h1{margin:0 0 8px;font-size:36px;line-height:1.08;color:#f3efe6}.hero p{margin:0;color:#a8b6ae;max-width:780px;line-height:1.75}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn,.btn-outline,.btn-soft{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:14px;font-weight:800;text-decoration:none;transition:.18s ease;border:1px solid transparent}.btn{background:#d8b56b;color:#182018}.btn-outline{background:transparent;color:#e7ddca;border-color:rgba(229,201,138,.34)}.btn-soft{background:rgba(255,255,255,.05);color:#e7ddca;border-color:rgba(255,255,255,.08)}.btn:hover,.btn-outline:hover,.btn-soft:hover{transform:translateY(-1px)}.grid-2{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}.flash{padding:14px 16px;border-radius:16px;font-size:14px;line-height:1.6}.flash-success{background:rgba(64,166,103,.16);border:1px solid rgba(64,166,103,.28);color:#c7f0d5}.flash-error{background:rgba(214,92,92,.16);border:1px solid rgba(214,92,92,.28);color:#ffd5d5}.eyebrow{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;background:rgba(216,181,107,.14);color:#f7dfae;border:1px solid rgba(216,181,107,.26);margin-bottom:12px}.stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.stat-card{padding:18px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)}.stat-card strong{display:block;font-size:30px;color:#f5ebd9;margin-bottom:6px}.stat-card span{display:block;color:#9fb0a7;font-size:13px}.card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.mini-card{padding:18px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)}.mini-card h3{margin:0 0 10px;font-size:20px;color:#f3efe6}.mini-card p{margin:0;color:#a8b6ae;line-height:1.7}.status-pill{display:inline-flex;align-items:center;padding:7px 11px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.08);color:#fff}.status-pill.green{background:rgba(72,187,120,.16);color:#c9f5d7;border-color:rgba(72,187,120,.26)}.status-pill.gold{background:rgba(216,181,107,.16);color:#f8dfaa;border-color:rgba(216,181,107,.25)}.status-pill.blue{background:rgba(93,129,232,.18);color:#d6e1ff;border-color:rgba(93,129,232,.24)}.status-pill.gray{background:rgba(255,255,255,.08);color:#dbe3df}.list{display:grid;gap:10px}.list-item{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.08)}.list-item:last-child{border-bottom:none;padding-bottom:0}.list-item:first-child{padding-top:0}.muted{color:#98a89f}.empty{padding:18px;border-radius:18px;border:1px dashed rgba(229,201,138,.24);background:rgba(255,255,255,.02);color:#aab5af}.meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.meta span{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);font-size:12px;color:#dbe2da}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.detail-box{padding:16px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)}.detail-box strong{display:block;color:#f3efe6;font-size:13px;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}.detail-box div{color:#dce5de;line-height:1.7;word-break:break-word}.progress-line{height:10px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:12px}.progress-fill{height:100%;background:linear-gradient(90deg,#d8b56b,#f0d89f)}.offer-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.offer-card{padding:18px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)}.offer-card strong{display:block;font-size:30px;color:#f5ebd9;margin-bottom:6px}.offer-card span{display:block;color:#9fb0a7;font-size:13px}.table-wrap{width:100%;overflow:auto;border:1px solid rgba(255,255,255,.08);border-radius:16px}.data-table{width:100%;border-collapse:collapse;min-width:760px}.data-table th,.data-table td{padding:12px 12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;font-size:13px;color:#dce5de;vertical-align:top}.data-table th{color:#f3efe6;font-size:12px;text-transform:uppercase;letter-spacing:.06em;background:rgba(255,255,255,.04)}.data-table tr:last-child td{border-bottom:none}.seller-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:14px}@media (max-width:1200px){.seller-kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media (max-width:980px){.grid-2,.card-grid,.stat-grid,.detail-grid,.offer-grid,.seller-kpi-grid{grid-template-columns:1fr 1fr}}@media (max-width:720px){.member-wrap{padding:24px 14px 42px}.hero h1{font-size:30px}.grid-2,.card-grid,.stat-grid,.detail-grid,.offer-grid,.seller-kpi-grid{grid-template-columns:1fr}}
</style>

<div class="member-wrap">
  <div class="member-shell">
    <section class="panel">
      <div class="panel-body">
        <div class="hero">
          <div>
            <span class="eyebrow"><?= $isSellerApproved ? 'Seller Dashboard' : 'Member Dashboard' ?></span>
            <h1>Welcome back, <?= bv_member_e($displayName) ?></h1>
            <p>
              You are currently signed in as <strong><?= bv_member_e($role !== '' ? $role : 'member') ?></strong>
              <?php if ($sellerStatus !== ''): ?> and your seller application status is <strong><?= bv_member_e($sellerStatus) ?></strong><?php endif; ?>.
              This page serves as the main dashboard for both buyers and sellers, so you can always see what your account can do next.
            </p>
          </div>
          <div class="actions">
            <a class="btn-outline" href="/member/profile.php">Edit Profile</a>
            <?php if ($isSellerApproved): ?>
              <a class="btn" href="/member/listings.php">Manage Listings</a>
              <a class="btn-outline" href="/member/listing_form.php">Create Listing</a>
            <?php else: ?>
              <a class="btn" href="/seller/apply.php">Apply for Seller</a>
            <?php endif; ?>
            <a class="btn-soft" href="/member/change-password.php">Change Password</a>
            <a class="btn-soft" href="/logout.php">Logout</a>
          </div>
        </div>
      </div>
    </section>

    <?php if ($flash): ?>
      <div class="flash <?= ($flash['type'] ?? '') === 'success' ? 'flash-success' : 'flash-error' ?>">
        <?= bv_member_e((string)($flash['message'] ?? '')) ?>
      </div>
    <?php endif; ?>

    <div class="grid-2">
      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Account Snapshot</h1>
              <p>A quick overview of your current account status.</p>
            </div>
          </div>

          <div class="card-grid">
            <div class="mini-card">
              <h3>Member Access</h3>
              <p>This account has access to the member area and can continue to security, profile, and account-related flows.</p>
              <div class="meta" style="margin-top:12px;">
                <span>ID #<?= (int)$user['id'] ?></span>
                <span><?= bv_member_e($user['email'] ?? 'No email') ?></span>
              </div>
            </div>

            <div class="mini-card">
              <h3>Seller Status</h3>
              <?php
              $pillClass = 'gray';
              if ($sellerStatus === 'approved') $pillClass = 'green';
              elseif (in_array($sellerStatus, ['submitted', 'under_review'], true)) $pillClass = 'gold';
              elseif ($role === 'seller') $pillClass = 'blue';
              ?>
              <p style="margin-bottom:12px;">Your latest seller access status for this account.</p>
              <span class="status-pill <?= $pillClass ?>"><?= bv_member_e($sellerStatus !== '' ? $sellerStatus : ($isSellerApproved ? 'approved' : 'not_applied')) ?></span>
              <div class="meta" style="margin-top:12px;">
                <span><?= $isSellerApproved ? 'Listing tools unlocked' : 'Listing tools locked' ?></span>
                <?php if ($isSellerApproved): ?>
                  <span><?= (int)$discountStats['active_discounts'] ?> active discounts</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">What You Can Do Next</h1>
              <p>Simple next steps, without guesswork.</p>
            </div>
          </div>

          <div class="list">
            <div class="list-item">
              <div>
                <strong style="color:#f3efe6;">Complete buyer profile</strong>
                <div class="muted">Add your contact and address details so your account is ready for the upcoming order flow.</div>
              </div>
              <a class="btn-outline" href="/member/profile.php"><?= $profileIsComplete ? 'Review' : 'Update' ?></a>
            </div>

            <div class="list-item">
              <div>
                <strong style="color:#f3efe6;">Open my offers</strong>
                <div class="muted">Review negotiation threads, checkout-ready deals, and completed offer history.</div>
              </div>
              <a class="btn-outline" href="/member/offers.php">Open</a>
            </div>
			
<div class="list-item">
  <div>
    <strong style="color:#f3efe6;">My Orders</strong>
    <div class="muted">View your own orders, open each order detail, and submit cancel requests when allowed.</div>
  </div>
  <a class="btn-outline" href="/member/order_view.php">Open</a>
</div>

            <?php if ($isSellerApproved): ?>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Open seller listings</strong>
                  <div class="muted">View all listings for this account, including status and stock summary.</div>
                </div>
                <a class="btn-outline" href="/member/listings.php">Open</a>
              </div>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Create new listing</strong>
                  <div class="muted">Add a new fish listing with seller-safe access controls.</div>
                </div>
                <a class="btn-outline" href="/member/listing_form.php">Create</a>
              </div>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Manage seller offers</strong>
                  <div class="muted">Check incoming buyer negotiations and jump straight into each offer thread.</div>
                </div>
                <a class="btn-outline" href="/seller/offers.php">Open</a>
              </div>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Manage listing media</strong>
                  <div class="muted">Open image and video management from each listing.</div>
                </div>
                <a class="btn-outline" href="/member/listings.php">Open Listings</a>
              </div>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Manage seller discounts</strong>
                  <div class="muted">Create, update, and review active promotions connected to your seller account.</div>
                </div>
                <a class="btn-outline" href="/seller/discounts.php">Open</a>
              </div>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Review discounted orders</strong>
                  <div class="muted">See which orders used your discounts and how much was reduced.</div>
                </div>
                <a class="btn-outline" href="/seller/discounts.php">View</a>
              </div>
            <?php else: ?>
              <div class="list-item">
                <div>
                  <strong style="color:#f3efe6;">Apply to become a seller</strong>
                  <div class="muted">Once approved, this dashboard will unlock your listing tools automatically.</div>
                </div>
                <a class="btn-outline" href="/seller/apply.php">Apply</a>
              </div>
            <?php endif; ?>

            <div class="list-item">
              <div>
                <strong style="color:#f3efe6;">Browse public listings</strong>
                <div class="muted">Review the public storefront before or after publishing your listings.</div>
              </div>
              <a class="btn-outline" href="/listings.php">Browse</a>
            </div>

            <div class="list-item">
              <div>
                <strong style="color:#f3efe6;">Security</strong>
                <div class="muted">Update your password and keep your account session clean and secure.</div>
              </div>
              <a class="btn-outline" href="/member/change-password.php">Open</a>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="panel">
      <div class="panel-body">
        <div class="hero" style="margin-bottom:14px;">
          <div>
            <h1 style="font-size:24px;margin-bottom:6px;">Offer Summary</h1>
            <p>Direct overview of your current offer activity as a buyer, plus seller-side offer flow when your seller account is approved.</p>
          </div>
          <div class="actions">
            <a class="btn-outline" href="/member/offers.php">Buyer Offers</a>
            <?php if ($isSellerApproved): ?>
              <a class="btn-soft" href="/seller/offers.php">Seller Offers</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$offerFeatureAvailable): ?>
          <div class="empty">Offer tools are not connected yet on this account. Once the offer tables are available, this section will display live stats automatically.</div>
        <?php else: ?>
          <?php if ($offerError !== ''): ?>
            <div class="flash flash-error" style="margin-bottom:14px;"><?= bv_member_e($offerError) ?></div>
          <?php endif; ?>

          <div class="offer-grid" style="margin-bottom:14px;">
            <div class="offer-card"><strong><?= (int)$offerStats['buyer_total'] ?></strong><span>Buyer Offers</span></div>
            <div class="offer-card"><strong><?= (int)$offerStats['buyer_open'] ?></strong><span>Buyer Open</span></div>
            <div class="offer-card"><strong><?= (int)$offerStats['buyer_checkout_ready'] ?></strong><span>Ready for Checkout</span></div>
            <div class="offer-card"><strong><?= (int)$offerStats['buyer_completed'] ?></strong><span>Buyer Completed</span></div>
            <div class="offer-card"><strong><?= (int)$offerStats['active_tokens'] ?></strong><span>Active Tokens</span></div>
          </div>

          <?php if ($isSellerApproved): ?>
            <div class="offer-grid">
              <div class="offer-card"><strong><?= (int)$offerStats['seller_total'] ?></strong><span>Seller Offers</span></div>
              <div class="offer-card"><strong><?= (int)$offerStats['seller_open'] ?></strong><span>Need Reply</span></div>
              <div class="offer-card"><strong><?= (int)$offerStats['seller_checkout_ready'] ?></strong><span>Accepted / Waiting</span></div>
              <div class="offer-card"><strong><?= (int)$offerStats['seller_completed'] ?></strong><span>Seller Completed</span></div>
              <div class="offer-card"><strong><?= (int)($offerStats['buyer_open'] + $offerStats['seller_open']) ?></strong><span>Total Open Threads</span></div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <div class="grid-2">
      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Recent Buyer Offers</h1>
              <p>Your latest offer threads as a buyer.</p>
            </div>
            <div class="actions">
              <a class="btn-outline" href="/member/offers.php">View All</a>
            </div>
          </div>

          <?php if (!$offerFeatureAvailable): ?>
            <div class="empty">Offer feature is not available yet.</div>
          <?php elseif (!$recentBuyerOffers): ?>
            <div class="empty">No buyer offer threads yet. Once you send an offer on a listing, it will appear here.</div>
          <?php else: ?>
            <div class="list">
              <?php foreach ($recentBuyerOffers as $row): ?>
                <?php
                [$offerLabel, $offerPillClass] = bv_member_offer_status_meta((string)($row['status'] ?? ''));
                $offerCurrency = trim((string)($row['currency'] ?? 'USD'));
                $offerTitle = trim((string)($row['title'] ?? 'Untitled Listing'));
                $latestOfferPrice = (float)($row['latest_offer_price'] ?? 0);
                $agreedOfferPrice = (float)($row['agreed_price'] ?? 0);
                ?>
                <div class="list-item">
                  <div>
                    <strong style="color:#f3efe6;"><?= bv_member_e($offerTitle) ?></strong>
                    <div class="meta">
                      <span>#<?= (int)($row['id'] ?? 0) ?></span>
                      <span class="status-pill <?= $offerPillClass ?>"><?= bv_member_e($offerLabel) ?></span>
                      <?php if ($latestOfferPrice > 0): ?><span>Latest: <?= bv_member_e(bv_member_offer_money($latestOfferPrice, $offerCurrency)) ?></span><?php endif; ?>
                      <?php if ($agreedOfferPrice > 0): ?><span>Agreed: <?= bv_member_e(bv_member_offer_money($agreedOfferPrice, $offerCurrency)) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($row['updated_at'])): ?>
                      <div class="muted" style="margin-top:8px;">Updated <?= bv_member_e(date('Y-m-d H:i', strtotime((string)$row['updated_at']) ?: time())) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="actions">
                    <a class="btn-outline" href="/offer.php?id=<?= (int)($row['id'] ?? 0) ?>">Open Thread</a>
                    <a class="btn-soft" href="<?= bv_member_e(bv_member_offer_listing_url($row)) ?>">Listing</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;"><?= $isSellerApproved ? 'Recent Seller Offers' : 'Offer Guidance' ?></h1>
              <p><?= $isSellerApproved ? 'Latest offer threads that reached your seller side.' : 'Seller-side offer tools will unlock after seller approval.' ?></p>
            </div>
            <div class="actions">
              <?php if ($isSellerApproved): ?>
                <a class="btn-outline" href="/seller/offers.php">View All</a>
              <?php else: ?>
                <a class="btn-outline" href="/seller/apply.php">Apply</a>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!$isSellerApproved): ?>
            <div class="empty">Once your seller application is approved, this panel will show incoming buyer negotiations for your listings.</div>
          <?php elseif (!$offerFeatureAvailable): ?>
            <div class="empty">Offer feature is not available yet.</div>
          <?php elseif (!$recentSellerOffers): ?>
            <div class="empty">No seller-side offer threads yet. When buyers start negotiating on your listings, the latest ones will appear here.</div>
          <?php else: ?>
            <div class="list">
              <?php foreach ($recentSellerOffers as $row): ?>
                <?php
                [$offerLabel, $offerPillClass] = bv_member_offer_status_meta((string)($row['status'] ?? ''));
                $offerCurrency = trim((string)($row['currency'] ?? 'USD'));
                $offerTitle = trim((string)($row['title'] ?? 'Untitled Listing'));
                $latestOfferPrice = (float)($row['latest_offer_price'] ?? 0);
                $agreedOfferPrice = (float)($row['agreed_price'] ?? 0);
                ?>
                <div class="list-item">
                  <div>
                    <strong style="color:#f3efe6;"><?= bv_member_e($offerTitle) ?></strong>
                    <div class="meta">
                      <span>#<?= (int)($row['id'] ?? 0) ?></span>
                      <span class="status-pill <?= $offerPillClass ?>"><?= bv_member_e($offerLabel) ?></span>
                      <?php if ($latestOfferPrice > 0): ?><span>Latest: <?= bv_member_e(bv_member_offer_money($latestOfferPrice, $offerCurrency)) ?></span><?php endif; ?>
                      <?php if ($agreedOfferPrice > 0): ?><span>Agreed: <?= bv_member_e(bv_member_offer_money($agreedOfferPrice, $offerCurrency)) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($row['updated_at'])): ?>
                      <div class="muted" style="margin-top:8px;">Updated <?= bv_member_e(date('Y-m-d H:i', strtotime((string)$row['updated_at']) ?: time())) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="actions">
                    <a class="btn-outline" href="/offer.php?id=<?= (int)($row['id'] ?? 0) ?>">Open Thread</a>
                    <a class="btn-soft" href="<?= bv_member_e(bv_member_offer_listing_url($row)) ?>">Listing</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <section class="panel">
      <div class="panel-body">
        <div class="hero" style="margin-bottom:14px;">
          <div>
            <h1 style="font-size:24px;margin-bottom:6px;">Buyer Profile Snapshot</h1>
            <p>Your buyer-side account data, ready for cart, checkout, and direct purchase flows in the next phase.</p>
          </div>
          <div class="actions">
            <a class="btn" href="/member/profile.php">Edit Buyer Profile</a>
          </div>
        </div>

        <div class="mini-card" style="margin-bottom:14px;">
          <h3 style="margin-bottom:8px;">Profile Completeness</h3>
          <p>
            Your buyer profile currently has <strong><?= (int)$profileFilled ?>/<?= (int)$profileTotal ?></strong> key fields completed.
            <?= $profileIsComplete ? 'Your profile is in good shape for the next buying flow.' : 'You should complete the remaining details before enabling live ordering.' ?>
          </p>
          <div class="progress-line">
            <div class="progress-fill" style="width:<?= $profileTotal > 0 ? (int)round(($profileFilled / $profileTotal) * 100) : 0 ?>%;"></div>
          </div>
          <div class="meta">
            <span class="status-pill <?= $profileIsComplete ? 'green' : 'gold' ?>">
              <?= $profileIsComplete ? 'Profile Ready' : 'Needs More Details' ?>
            </span>
          </div>
        </div>

        <div class="detail-grid">
          <div class="detail-box">
            <strong>Phone</strong>
            <div><?= bv_member_e((string)($buyerProfile['phone'] ?? 'Not added yet')) ?></div>
          </div>
          <div class="detail-box">
            <strong>WhatsApp</strong>
            <div><?= bv_member_e((string)($buyerProfile['whatsapp'] ?? 'Not added yet')) ?></div>
          </div>
          <div class="detail-box">
            <strong>Email</strong>
            <div><?= bv_member_e((string)($buyerProfile['email'] ?? 'No email')) ?></div>
          </div>
          <div class="detail-box">
            <strong>Country</strong>
            <div><?= bv_member_e((string)($buyerProfile['country'] ?? 'Not added yet')) ?></div>
          </div>
          <div class="detail-box" style="grid-column:1 / -1;">
            <strong>Full Address</strong>
            <div><?= bv_member_e($buyerFullAddress) ?></div>
          </div>
        </div>
      </div>
    </section>

    <?php if ($isSellerApproved): ?>
      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Seller Control Center</h1>
              <p>Customer orders, refund requests, offer threads, and monthly sales in one seller-approved workspace.</p>
            </div>
            <div class="actions">
              <a class="btn-outline" href="<?= bv_member_e(bv_member_sd_existing_page(['/seller/orders.php', '/member/order_view.php'])) ?>">Orders</a>
              <a class="btn-outline" href="<?= bv_member_e(bv_member_sd_existing_page(['/seller/refunds.php'])) ?>">Refunds</a>
              <a class="btn-soft" href="<?= bv_member_e(bv_member_sd_existing_page(['/seller/offers.php', '/offer.php'])) ?>">Offers</a>
            </div>
          </div>

          <div class="seller-kpi-grid">
            <div class="stat-card"><strong><?= (int)$sellerDashboardStats['new_orders'] ?></strong><span>New Orders</span></div>
            <div class="stat-card"><strong><?= (int)$sellerDashboardStats['paid_orders'] ?></strong><span>Paid Orders</span></div>
            <div class="stat-card"><strong><?= (int)$sellerDashboardStats['refund_pending'] ?></strong><span>Refund Pending</span></div>
            <div class="stat-card"><strong><?= (int)$sellerDashboardStats['refund_processed'] ?></strong><span>Refund Processed</span></div>
            <div class="stat-card"><strong><?= (int)$sellerDashboardStats['open_offers'] ?></strong><span>Open Offers</span></div>
            <div class="stat-card"><strong><?= bv_member_e(bv_member_sd_money((float)$sellerDashboardStats['this_month_sales'], (string)$sellerDashboardStats['currency'])) ?></strong><span>This Month Sales</span></div>
          </div>

          <div class="grid-2">
            <div class="mini-card">
              <h3>Recent Orders</h3>
              <?php if (!$sellerRecentOrders): ?>
                <div class="empty">No seller orders found yet.</div>
              <?php else: ?>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead><tr><th>Order</th><th>Buyer</th><th>Listing</th><th>Qty</th><th>Total</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerRecentOrders as $row): ?>
                      <?php [$orderLabel,$orderClass]=bv_member_sd_status_badge((string)($row['order_status'] ?? '')); [$paymentLabel,$paymentClass]=bv_member_sd_status_badge((string)($row['payment_status'] ?? '')); $orderId=(int)($row['order_id'] ?? 0); ?>
                      <tr>
                        <td><?= bv_member_e((string)($row['order_code'] ?? ('ORD-' . $orderId))) ?></td>
                        <td><?= bv_member_e((string)($row['buyer_name'] ?? 'Buyer')) ?></td>
                        <td><?= bv_member_e((string)($row['listing_title'] ?? '-')) ?></td>
                        <td><?= (int)($row['qty'] ?? 0) ?></td>
                        <td><?= bv_member_e(bv_member_sd_money((float)($row['total'] ?? 0), (string)($row['currency'] ?? 'USD'))) ?></td>
                        <td><span class="status-pill <?= $orderClass ?>"><?= bv_member_e($orderLabel) ?></span></td>
                        <td><span class="status-pill <?= $paymentClass ?>"><?= bv_member_e($paymentLabel) ?></span></td>
                        <td><a class="btn-soft" href="<?= bv_member_e(bv_member_sd_url($orderPage, ['id' => $orderId])) ?>">View</a></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <div class="mini-card">
              <h3>Refund Requests</h3>
              <?php if (!$sellerRecentRefunds): ?>
                <div class="empty">No refund requests found.</div>
              <?php else: ?>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead><tr><th>Refund</th><th>Order</th><th>Requested</th><th>Approved</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerRecentRefunds as $row): ?>
                      <?php [$refundLabel,$refundClass]=bv_member_sd_status_badge((string)($row['refund_status'] ?? '')); $refundId=(int)($row['refund_id'] ?? 0); $refundOrderId=(int)($row['order_id'] ?? 0); ?>
                      <tr>
                        <td><?= bv_member_e((string)($row['refund_code'] ?? ('RF-' . $refundId))) ?></td>
                        <td><?= bv_member_e((string)($row['order_code'] ?? ('ORD-' . $refundOrderId))) ?></td>
                        <td><?= isset($row['requested_refund_amount']) ? bv_member_e(bv_member_sd_money((float)($row['requested_refund_amount'] ?? 0), (string)($row['currency'] ?? 'USD'))) : '-' ?></td>
                        <td><?= isset($row['approved_refund_amount']) ? bv_member_e(bv_member_sd_money((float)($row['approved_refund_amount'] ?? 0), (string)($row['currency'] ?? 'USD'))) : '-' ?></td> 
                        <td><span class="status-pill <?= $refundClass ?>"><?= bv_member_e($refundLabel) ?></span></td>
                        <td><a class="btn-soft" href="<?= bv_member_e(bv_member_sd_url($refundPage, ['id' => $refundId])) ?>">View</a></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="grid-2" style="margin-top:14px;">
            <div class="mini-card">
              <h3>Offer Messages</h3>
              <?php if (!$sellerRecentOfferThreads): ?>
                <div class="empty">No offers found yet.</div>
              <?php else: ?>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead><tr><th>Offer</th><th>Buyer</th><th>Listing</th><th>Offer Price</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerRecentOfferThreads as $row): ?>
                      <?php [$offerStatusLabel,$offerStatusClass]=bv_member_sd_status_badge((string)($row['offer_status'] ?? '')); $offerId=(int)($row['offer_id'] ?? 0); ?>
                      <tr>
                        <td><?= bv_member_e((string)($row['offer_code'] ?? ('OFF-' . $offerId))) ?></td>
                        <td><?= bv_member_e((string)($row['buyer_name'] ?? 'Buyer')) ?></td>
                        <td><?= bv_member_e((string)($row['listing_title'] ?? '-')) ?></td>
                        <td><?= bv_member_e(bv_member_sd_money((float)($row['offer_price'] ?? 0), (string)($row['currency'] ?? 'USD'))) ?></td>
                        <td><span class="status-pill <?= $offerStatusClass ?>"><?= bv_member_e($offerStatusLabel) ?></span></td>
                        <td><a class="btn-soft" href="<?= bv_member_e(bv_member_sd_url($offerPage, ['id' => $offerId])) ?>">Open</a></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <div class="mini-card">
              <h3>Sales Summary by Month</h3>
              <?php if (!$sellerSalesMonthly): ?>
                <div class="empty">No sales summary data yet.</div>
              <?php else: ?>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead><tr><th>Month</th><th>Orders</th><th>Gross Sales</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerSalesMonthly as $row): ?>
                      <tr>
                        <td><?= bv_member_e((string)($row['sales_month'] ?? '-')) ?></td>
                        <td><?= (int)($row['orders_count'] ?? 0) ?></td>
                        <td><?= bv_member_e(bv_member_sd_money((float)($row['gross_sales'] ?? 0), (string)($row['currency'] ?? $sellerDashboardStats['currency']))) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>
	
      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Seller Summary</h1>
              <p>Your seller tools and listing overview are available here for immediate access.</p>
            </div>
          </div>

          <div class="stat-grid">
            <div class="stat-card"><strong><?= (int)$listingStats['total'] ?></strong><span>Total listings</span></div>
            <div class="stat-card"><strong><?= (int)$listingStats['active'] ?></strong><span>Live / published</span></div>
            <div class="stat-card"><strong><?= (int)$listingStats['draft'] ?></strong><span>Draft</span></div>
            <div class="stat-card"><strong><?= (int)$listingStats['sold'] ?></strong><span>Sold</span></div>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Seller Discount Summary</h1>
              <p>Quick overview of discount activity connected to your seller account.</p>
            </div>
            <div class="actions">
              <a class="btn-outline" href="/seller/discounts.php">Manage Discounts</a>
            </div>
          </div>

          <?php if (!$discountFeatureAvailable): ?>
            <div class="empty">Discount tools are not fully connected yet on this account. Once the discount tables are available, this section will show live numbers automatically.</div>
          <?php else: ?>
            <div class="stat-grid">
              <div class="stat-card">
                <strong><?= (int)$discountStats['active_discounts'] ?></strong>
                <span>Active discounts</span>
              </div>
              <div class="stat-card">
                <strong><?= (int)$discountStats['discounted_orders'] ?></strong>
                <span>Orders with discount</span>
              </div>
              <div class="stat-card">
                <strong><?= bv_member_e(bv_member_discount_money((float)$discountStats['discount_total'], (string)$discountStats['currency'])) ?></strong>
                <span>Total discount amount</span>
              </div>
              <div class="stat-card">
                <strong><?= bv_member_e(bv_member_discount_money((float)$discountStats['discounted_subtotal'], (string)$discountStats['currency'])) ?></strong>
                <span>Subtotal before discount</span>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Recent Discount Orders</h1>
              <p>Latest orders that used seller discounts.</p>
            </div>
            <div class="actions">
              <a class="btn-outline" href="/seller/discounts.php">Open Discounts</a>
            </div>
          </div>

          <?php if (!$discountFeatureAvailable): ?>
            <div class="empty">Discount order tracking is not available yet for this account.</div>
          <?php elseif (!$recentDiscountOrders): ?>
            <div class="empty">No discounted orders yet. Once buyers use a seller discount, the latest entries will appear here.</div>
          <?php else: ?>
            <div class="list">
              <?php foreach ($recentDiscountOrders as $row): ?>
                <?php
                $orderId = (int)($row['order_id'] ?? 0);
                $orderCode = trim((string)($row['order_code'] ?? ''));
                $buyerName = trim((string)($row['buyer_name'] ?? ''));
                $rowCurrency = trim((string)($row['currency'] ?? $discountStats['currency']));
                $discountAmount = (float)($row['discount_amount'] ?? 0);
                $subtotalBefore = (float)($row['subtotal_before_discount'] ?? 0);
                $subtotalAfter = (float)($row['subtotal_after_discount'] ?? 0);
                ?>
                <div class="list-item">
                  <div>
                    <strong style="color:#f3efe6;"><?= bv_member_e($orderCode !== '' ? $orderCode : ('Order #' . $orderId)) ?></strong>

                    <div class="meta">
                      <span>Buyer: <?= bv_member_e($buyerName !== '' ? $buyerName : 'Unknown') ?></span>
                      <span>Discount: <?= bv_member_e(bv_member_discount_money($discountAmount, $rowCurrency)) ?></span>
                      <span>Before: <?= bv_member_e(bv_member_discount_money($subtotalBefore, $rowCurrency)) ?></span>
                      <span>After: <?= bv_member_e(bv_member_discount_money($subtotalAfter, $rowCurrency)) ?></span>
                    </div>

                    <?php if (!empty($row['created_at'])): ?>
                      <div class="muted" style="margin-top:8px;">Logged <?= bv_member_e(date('Y-m-d H:i', strtotime((string)$row['created_at']) ?: time())) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="actions">
                    <?php if ($orderId > 0): ?>
                      <a class="btn-outline" href="/seller/order_view.php?id=<?= $orderId ?>">View Order</a>
                    <?php else: ?>
                      <a class="btn-soft" href="/seller/discounts.php">Open</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <div class="hero" style="margin-bottom:14px;">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Recent Seller Listings</h1>
              <p>Quick access to your most recent listing pages.</p>
            </div>
            <div class="actions">
              <a class="btn-outline" href="/member/listings.php">View All</a>
              <a class="btn" href="/member/listing_form.php">+ New Listing</a>
            </div>
          </div>

          <?php if (!$recentListings): ?>
            <div class="empty">There are no seller listings for this account yet. Start with your first one and this dashboard will come alive immediately.</div>
          <?php else: ?>
            <div class="list">
              <?php foreach ($recentListings as $row): ?>
                <?php
                $label = bv_member_status_label((string)($row['sale_status'] ?: $row['status'] ?: 'draft'));
                $pillClass = 'gray';
                $token = bv_member_status_token((string)($row['sale_status'] ?: $row['status'] ?: 'draft'));
                if (in_array($token, ['sold', 'completed'], true)) $pillClass = 'blue';
                elseif (in_array($token, ['active', 'published', 'reserved', 'awaiting_payment', 'paid'], true)) $pillClass = 'green';
                elseif ($token === 'draft') $pillClass = 'gold';
                ?>
                <div class="list-item">
                  <div>
                    <strong style="color:#f3efe6;"><?= bv_member_e((string)($row['title'] ?? 'Untitled Listing')) ?></strong>
                    <div class="meta">
                      <span>#<?= (int)($row['id'] ?? 0) ?></span>
                      <?php if (!empty($row['slug'])): ?><span><?= bv_member_e((string)$row['slug']) ?></span><?php endif; ?>
                      <span class="status-pill <?= $pillClass ?>"><?= bv_member_e($label) ?></span>
                    </div>
                    <?php if (!empty($row['updated_at'])): ?><div class="muted" style="margin-top:8px;">Updated <?= bv_member_e(date('Y-m-d H:i', strtotime((string)$row['updated_at']) ?: time())) ?></div><?php endif; ?>
                  </div>
                  <div class="actions">
                    <a class="btn-outline" href="/member/listing_view.php?id=<?= (int)$row['id'] ?>">View</a>
                    <a class="btn-soft" href="/member/listing_form.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <a class="btn-soft" href="/member/listing_images.php?listing_id=<?= (int)$row['id'] ?>">Media</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php else: ?>
      <section class="panel">
        <div class="panel-body">
          <div class="hero">
            <div>
              <h1 style="font-size:24px;margin-bottom:6px;">Seller Tools Are Waiting</h1>
              <p>Once your seller application is approved, this dashboard will automatically unlock <strong>My Listings, Create Listing, Discount Tools, Seller Offers, and Listing Detail</strong> tools for your account.</p>
            </div>
            <div class="actions">
              <a class="btn" href="/seller/apply.php">Start Seller Application</a>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php bv_member_page_end(); ?>