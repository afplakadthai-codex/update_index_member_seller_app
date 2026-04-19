<?php
declare(strict_types=1);

/**
 * Bettavaro Offer System Helper
 *
 * Scope:
 * - DB bootstrap + query helpers
 * - current user / role / permission helpers
 * - offer status helpers
 * - checkout token helpers
 * - expiry helpers
 * - audit log helpers
 *
 * Recommended tables:
 * - listing_offers
 * - listing_offer_messages
 * - listing_offer_checkout_tokens
 * - listing_offer_status_logs
 * - audit_logs (optional, best-effort)
 */

if (!function_exists('bv_listing_offers_boot')) {
    function bv_listing_offers_boot(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $projectRoot = dirname(__DIR__);

        $candidates = [
            $projectRoot . '/config/db.php',
            $projectRoot . '/includes/db.php',
            $projectRoot . '/includes/config.php',
            $projectRoot . '/config.php',
            dirname($projectRoot) . '/config/db.php',
            dirname($projectRoot) . '/includes/db.php',
            dirname($projectRoot) . '/includes/config.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
            }
        }

        $booted = true;
    }
}

if (!function_exists('bv_listing_offers_db')) {
    function bv_listing_offers_db()
    {
        bv_listing_offers_boot();

        $candidates = [
            $GLOBALS['conn'] ?? null,
            $GLOBALS['db'] ?? null,
            $GLOBALS['mysqli'] ?? null,
            $GLOBALS['pdo'] ?? null,
            $GLOBALS['PDO'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof mysqli || $candidate instanceof PDO) {
                return $candidate;
            }
        }

        foreach (['conn', 'db', 'mysqli', 'pdo', 'PDO'] as $name) {
            if (isset($GLOBALS[$name]) && ($GLOBALS[$name] instanceof mysqli || $GLOBALS[$name] instanceof PDO)) {
                return $GLOBALS[$name];
            }
        }

        throw new RuntimeException('Database connection not found. Please check your real DB bootstrap file path.');
    }
}

if (!function_exists('bv_listing_offers_is_mysqli')) {
    function bv_listing_offers_is_mysqli($db): bool
    {
        return $db instanceof mysqli;
    }
}

if (!function_exists('bv_listing_offers_is_pdo')) {
    function bv_listing_offers_is_pdo($db): bool
    {
        return $db instanceof PDO;
    }
}

if (!function_exists('bv_listing_offers_query_all')) {
    function bv_listing_offers_query_all(string $sql, array $params = []): array
    {
        $db = bv_listing_offers_db();

        if (bv_listing_offers_is_mysqli($db)) {
            $stmt = mysqli_prepare($db, $sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . mysqli_error($db));
            }

            if ($params) {
                $types = '';
                $values = [];
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }

                $refs = [];
                foreach ($values as $i => $value) {
                    $refs[$i] = &$values[$i];
                }

                array_unshift($refs, $types);
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }

            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Execute failed: ' . $err);
            }

            $result = mysqli_stmt_get_result($stmt);
            if ($result === false) {
                mysqli_stmt_close($stmt);
                return [];
            }

            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }

            mysqli_free_result($result);
            mysqli_stmt_close($stmt);

            return $rows;
        }

        if (bv_listing_offers_is_pdo($db)) {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed.');
            }
            if (!$stmt->execute(array_values($params))) {
                $err = $stmt->errorInfo();
                throw new RuntimeException('Execute failed: ' . ($err[2] ?? 'Unknown PDO error'));
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        }

        throw new RuntimeException('Unsupported database driver');
    }
}

if (!function_exists('bv_listing_offers_query_one')) {
    function bv_listing_offers_query_one(string $sql, array $params = []): ?array
    {
        $rows = bv_listing_offers_query_all($sql, $params);
        return $rows[0] ?? null;
    }
}

if (!function_exists('bv_listing_offers_execute')) {
    function bv_listing_offers_execute(string $sql, array $params = []): array
    {
        $db = bv_listing_offers_db();

        if (bv_listing_offers_is_mysqli($db)) {
            $stmt = mysqli_prepare($db, $sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . mysqli_error($db));
            }

            if ($params) {
                $types = '';
                $values = [];
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }

                $refs = [];
                foreach ($values as $i => $value) {
                    $refs[$i] = &$values[$i];
                }

                array_unshift($refs, $types);
                call_user_func_array([$stmt, 'bind_param'], $refs);
            }

            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Execute failed: ' . $err);
            }

            $affected = mysqli_stmt_affected_rows($stmt);
            $insertId = mysqli_insert_id($db);
            mysqli_stmt_close($stmt);

            return [
                'affected_rows' => $affected,
                'insert_id' => (int) $insertId,
            ];
        }

        if (bv_listing_offers_is_pdo($db)) {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed.');
            }
            if (!$stmt->execute(array_values($params))) {
                $err = $stmt->errorInfo();
                throw new RuntimeException('Execute failed: ' . ($err[2] ?? 'Unknown PDO error'));
            }

            return [
                'affected_rows' => (int) $stmt->rowCount(),
                'insert_id' => (int) $db->lastInsertId(),
            ];
        }

        throw new RuntimeException('Unsupported database driver');
    }
}

if (!function_exists('bv_listing_offers_begin_transaction')) {
    function bv_listing_offers_begin_transaction(): void
    {
        $db = bv_listing_offers_db();

        if (bv_listing_offers_is_mysqli($db)) {
            mysqli_begin_transaction($db);
            return;
        }

        if (bv_listing_offers_is_pdo($db)) {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
            }
            return;
        }

        throw new RuntimeException('Unsupported database driver');
    }
}

if (!function_exists('bv_listing_offers_commit')) {
    function bv_listing_offers_commit(): void
    {
        $db = bv_listing_offers_db();

        if (bv_listing_offers_is_mysqli($db)) {
            mysqli_commit($db);
            return;
        }

        if (bv_listing_offers_is_pdo($db)) {
            if ($db->inTransaction()) {
                $db->commit();
            }
            return;
        }

        throw new RuntimeException('Unsupported database driver');
    }
}

if (!function_exists('bv_listing_offers_rollback')) {
    function bv_listing_offers_rollback(): void
    {
        $db = bv_listing_offers_db();

        if (bv_listing_offers_is_mysqli($db)) {
            mysqli_rollback($db);
            return;
        }

        if (bv_listing_offers_is_pdo($db)) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return;
        }

        throw new RuntimeException('Unsupported database driver');
    }
}

if (!function_exists('bv_listing_offers_require_session')) {
    function bv_listing_offers_require_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}

if (!function_exists('bv_offer_current_user_id')) {
    function bv_offer_current_user_id(): int
    {
        bv_listing_offers_require_session();

        $candidates = [
            $_SESSION['user']['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['member']['id'] ?? null,
            $_SESSION['seller']['id'] ?? null,
            $_SESSION['admin']['id'] ?? null,
            $_SESSION['user_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }
}

if (!function_exists('bv_offer_current_user_role')) {
    function bv_offer_current_user_role(): string
    {
        bv_listing_offers_require_session();

        $candidates = [
            $_SESSION['user']['role'] ?? null,
            $_SESSION['auth_user']['role'] ?? null,
            $_SESSION['member']['role'] ?? null,
            $_SESSION['seller']['role'] ?? null,
            $_SESSION['admin']['role'] ?? null,
            $_SESSION['role'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $role = strtolower(trim((string) $candidate));
            if ($role !== '') {
                if ($role === 'super_admin' || $role === 'superadmin' || $role === 'owner') {
                    return 'admin';
                }
                return $role;
            }
        }

        return 'guest';
    }
}

if (!function_exists('bv_offer_is_admin_role')) {
    function bv_offer_is_admin_role(?string $role = null): bool
    {
        $role = strtolower(trim((string) ($role ?? bv_offer_current_user_role())));
        return in_array($role, ['admin', 'owner', 'superadmin', 'super_admin'], true);
    }
}

if (!function_exists('bv_offer_is_seller_role')) {
    function bv_offer_is_seller_role(?string $role = null): bool
    {
        $role = strtolower(trim((string) ($role ?? bv_offer_current_user_role())));
        return $role === 'seller' || bv_offer_is_admin_role($role);
    }
}

if (!function_exists('bv_offer_client_ip')) {
    function bv_offer_client_ip(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $raw = trim((string) $_SERVER[$key]);
            if ($raw === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $raw);
                $raw = trim((string) ($parts[0] ?? ''));
            }

            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('bv_offer_user_agent')) {
    function bv_offer_user_agent(): string
    {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
    }
}

if (!function_exists('bv_offer_escape')) {
    function bv_offer_escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_offer_now')) {
    function bv_offer_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('bv_offer_table_exists')) {
    function bv_offer_table_exists(string $tableName): bool
    {
        try {
            $db = bv_listing_offers_db();

            if (bv_listing_offers_is_mysqli($db)) {
                $stmt = mysqli_prepare($db, 'SHOW TABLES LIKE ?');
                if (!$stmt) {
                    return false;
                }

                mysqli_stmt_bind_param($stmt, 's', $tableName);

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    return false;
                }

                $result = mysqli_stmt_get_result($stmt);
                $exists = $result && mysqli_num_rows($result) > 0;

                if ($result) {
                    mysqli_free_result($result);
                }

                mysqli_stmt_close($stmt);
                return $exists;
            }

            if (bv_listing_offers_is_pdo($db)) {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                ");

                if (!$stmt || !$stmt->execute([$tableName])) {
                    return false;
                }

                return (int) $stmt->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('bv_listing_offers_require_tables')) {
    function bv_listing_offers_require_tables(): bool
    {
        $required = [
            'listing_offers',
            'listing_offer_messages',
            'listing_offer_checkout_tokens',
            'listing_offer_status_logs',
        ];

        foreach ($required as $table) {
            if (!bv_offer_table_exists($table)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('bv_offer_allowed_statuses')) {
    function bv_offer_allowed_statuses(): array
    {
        return [
            'open',
            'seller_accepted',
            'buyer_checkout_ready',
            'completed',
            'expired',
            'cancelled',
            'rejected',
        ];
    }
}

if (!function_exists('bv_offer_terminal_statuses')) {
    function bv_offer_terminal_statuses(): array
    {
        return ['completed', 'expired', 'cancelled', 'rejected'];
    }
}

if (!function_exists('bv_offer_is_terminal_status')) {
    function bv_offer_is_terminal_status(string $status): bool
    {
        return in_array(strtolower(trim($status)), bv_offer_terminal_statuses(), true);
    }
}

if (!function_exists('bv_offer_allowed_message_types')) {
    function bv_offer_allowed_message_types(): array
    {
        return [
            'offer',
            'counter',
            'message',
            'accept',
            'reject',
            'expire_notice',
            'system',
        ];
    }
}

if (!function_exists('bv_offer_allowed_token_statuses')) {
    function bv_offer_allowed_token_statuses(): array
    {
        return ['active', 'used', 'expired', 'cancelled'];
    }
}

if (!function_exists('bv_offer_get_listing_by_id')) {
    function bv_offer_get_listing_by_id(int $listingId): ?array
    {
        if ($listingId <= 0) {
            return null;
        }

        return bv_listing_offers_query_one(
            "SELECT *
             FROM listings
             WHERE id = ?
             LIMIT 1",
            [$listingId]
        );
    }
}

if (!function_exists('bv_offer_get_by_id')) {
    function bv_offer_get_by_id(int $offerId): ?array
    {
        if ($offerId <= 0) {
            return null;
        }

        return bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offers
             WHERE id = ?
             LIMIT 1",
            [$offerId]
        );
    }
}

if (!function_exists('bv_offer_get_open_by_listing_and_buyer')) {
    function bv_offer_get_open_by_listing_and_buyer(int $listingId, int $buyerUserId): ?array
    {
        if ($listingId <= 0 || $buyerUserId <= 0) {
            return null;
        }

        return bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offers
             WHERE listing_id = ?
               AND buyer_user_id = ?
               AND status IN ('open','seller_accepted','buyer_checkout_ready')
             ORDER BY id DESC
             LIMIT 1",
            [$listingId, $buyerUserId]
        );
    }
}

if (!function_exists('bv_offer_get_messages')) {
    function bv_offer_get_messages(int $offerId, int $limit = 200): array
    {
        if ($offerId <= 0) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $db = bv_listing_offers_db();

        $sql = "SELECT *
                FROM listing_offer_messages
                WHERE offer_id = ?
                ORDER BY id ASC
                LIMIT " . (int) $limit;

        if (bv_listing_offers_is_pdo($db) || bv_listing_offers_is_mysqli($db)) {
            return bv_listing_offers_query_all($sql, [$offerId]);
        }

        return [];
    }
}

if (!function_exists('bv_offer_current_user_can_view')) {
    function bv_offer_current_user_can_view(array $offer, ?int $userId = null, ?string $role = null): bool
    {
        $userId = $userId ?? bv_offer_current_user_id();
        $role = $role ?? bv_offer_current_user_role();

        if (bv_offer_is_admin_role($role)) {
            return true;
        }

        return $userId > 0 && (
            $userId === (int) ($offer['buyer_user_id'] ?? 0) ||
            $userId === (int) ($offer['seller_user_id'] ?? 0)
        );
    }
}

if (!function_exists('bv_offer_current_user_can_buyer_reply')) {
    function bv_offer_current_user_can_buyer_reply(array $offer, ?int $userId = null, ?string $role = null): bool
    {
        $userId = $userId ?? bv_offer_current_user_id();
        $role = $role ?? bv_offer_current_user_role();

        if (bv_offer_is_admin_role($role)) {
            return true;
        }

        if ($userId <= 0 || $userId !== (int) ($offer['buyer_user_id'] ?? 0)) {
            return false;
        }

        return !bv_offer_is_terminal_status((string) ($offer['status'] ?? ''));
    }
}

if (!function_exists('bv_offer_current_user_can_seller_manage')) {
    function bv_offer_current_user_can_seller_manage(array $offer, ?int $userId = null, ?string $role = null): bool
    {
        $userId = $userId ?? bv_offer_current_user_id();
        $role = $role ?? bv_offer_current_user_role();

        if (bv_offer_is_admin_role($role)) {
            return true;
        }

        if ($userId <= 0 || $userId !== (int) ($offer['seller_user_id'] ?? 0)) {
            return false;
        }

        return !bv_offer_is_terminal_status((string) ($offer['status'] ?? ''));
    }
}

if (!function_exists('bv_offer_can_checkout')) {
    function bv_offer_can_checkout(array $offer, ?int $buyerUserId = null): bool
    {
        $buyerUserId = $buyerUserId ?? bv_offer_current_user_id();
        if ($buyerUserId <= 0) {
            return false;
        }

        if ($buyerUserId !== (int) ($offer['buyer_user_id'] ?? 0)) {
            return false;
        }

        $status = strtolower(trim((string) ($offer['status'] ?? '')));
        if (!in_array($status, ['seller_accepted', 'buyer_checkout_ready'], true)) {
            return false;
        }

        $expiresAt = trim((string) ($offer['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return false;
        }

        return (float) ($offer['agreed_price'] ?? 0) > 0;
    }
}

if (!function_exists('bv_offer_validate_price')) {
    function bv_offer_validate_price($price): float
    {
        if (!is_numeric($price)) {
            return 0.0;
        }

        $value = round((float) $price, 2);
        return $value > 0 ? $value : 0.0;
    }
}

if (!function_exists('bv_offer_create')) {
    function bv_offer_create(array $data): int
    {
        $listingId = (int) ($data['listing_id'] ?? 0);
        $buyerUserId = (int) ($data['buyer_user_id'] ?? 0);
        $sellerUserId = (int) ($data['seller_user_id'] ?? 0);
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD')));
        $listingPriceSnapshot = bv_offer_validate_price($data['listing_price_snapshot'] ?? 0);
        $latestOfferPrice = bv_offer_validate_price($data['latest_offer_price'] ?? 0);
        $status = strtolower(trim((string) ($data['status'] ?? 'open')));
        $now = bv_offer_now();

        if ($listingId <= 0) {
            throw new InvalidArgumentException('Invalid listing_id');
        }
        if ($buyerUserId <= 0) {
            throw new InvalidArgumentException('Invalid buyer_user_id');
        }
        if ($sellerUserId <= 0) {
            throw new InvalidArgumentException('Invalid seller_user_id');
        }
        if ($listingPriceSnapshot <= 0) {
            throw new InvalidArgumentException('Invalid listing_price_snapshot');
        }
        if ($latestOfferPrice <= 0) {
            throw new InvalidArgumentException('Invalid latest_offer_price');
        }
        if (!in_array($status, bv_offer_allowed_statuses(), true)) {
            $status = 'open';
        }
        if ($currency === '') {
            $currency = 'USD';
        }

        $result = bv_listing_offers_execute(
            "INSERT INTO listing_offers
                (listing_id, buyer_user_id, seller_user_id, status, currency, listing_price_snapshot, latest_offer_price, last_message_at, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $listingId,
                $buyerUserId,
                $sellerUserId,
                $status,
                $currency,
                $listingPriceSnapshot,
                $latestOfferPrice,
                $now,
                $now,
                $now,
            ]
        );

        return (int) ($result['insert_id'] ?? 0);
    }
}

if (!function_exists('bv_offer_insert_message')) {
    function bv_offer_insert_message(array $data): int
    {
        $offerId = (int) ($data['offer_id'] ?? 0);
        $senderUserId = (int) ($data['sender_user_id'] ?? 0);
        $senderRole = strtolower(trim((string) ($data['sender_role'] ?? 'buyer')));
        $messageType = strtolower(trim((string) ($data['message_type'] ?? 'message')));
        $offerPrice = bv_offer_validate_price($data['offer_price'] ?? 0);
        $messageText = trim((string) ($data['message_text'] ?? ''));
        $now = bv_offer_now();

        if ($offerId <= 0) {
            throw new InvalidArgumentException('Invalid offer_id');
        }
        if ($senderUserId <= 0 && $senderRole !== 'system') {
            throw new InvalidArgumentException('Invalid sender_user_id');
        }
        if (!in_array($senderRole, ['buyer', 'seller', 'admin', 'system'], true)) {
            throw new InvalidArgumentException('Invalid sender_role');
        }
        if (!in_array($messageType, bv_offer_allowed_message_types(), true)) {
            $messageType = 'message';
        }
        if ($offerPrice <= 0) {
            $offerPrice = 0.0;
        }

        $result = bv_listing_offers_execute(
            "INSERT INTO listing_offer_messages
                (offer_id, sender_user_id, sender_role, message_type, offer_price, message_text, created_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?)",
            [
                $offerId,
                $senderUserId > 0 ? $senderUserId : null,
                $senderRole,
                $messageType,
                $offerPrice > 0 ? $offerPrice : null,
                $messageText !== '' ? $messageText : null,
                $now,
            ]
        );

        bv_listing_offers_execute(
            "UPDATE listing_offers
             SET latest_offer_price = CASE WHEN ? > 0 THEN ? ELSE latest_offer_price END,
                 last_message_at = ?,
                 updated_at = ?
             WHERE id = ?
             LIMIT 1",
            [
                $offerPrice,
                $offerPrice,
                $now,
                $now,
                $offerId,
            ]
        );

        return (int) ($result['insert_id'] ?? 0);
    }
}

if (!function_exists('bv_offer_status_log_insert')) {
    function bv_offer_status_log_insert(
        int $offerId,
        ?string $oldStatus,
        string $newStatus,
        ?string $actionNote = null,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): int {
        if ($offerId <= 0 || trim($newStatus) === '') {
            return 0;
        }

        $result = bv_listing_offers_execute(
            "INSERT INTO listing_offer_status_logs
                (offer_id, old_status, new_status, actor_user_id, actor_role, action_note, created_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?)",
            [
                $offerId,
                $oldStatus !== null ? trim($oldStatus) : null,
                trim($newStatus),
                $actorUserId,
                $actorRole !== null ? mb_substr(trim($actorRole), 0, 30) : null,
                $actionNote !== null ? mb_substr(trim($actionNote), 0, 255) : null,
                bv_offer_now(),
            ]
        );

        return (int) ($result['insert_id'] ?? 0);
    }
}

if (!function_exists('bv_offer_audit_log')) {
    function bv_offer_audit_log(
        string $eventType,
        string $entityType,
        int $entityId,
        string $action,
        string $summary,
        array $before = [],
        array $after = [],
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): int {
        if (!bv_offer_table_exists('audit_logs')) {
            return 0;
        }

        $actorUserId = $actorUserId ?? bv_offer_current_user_id();
        $actorRole = $actorRole ?? bv_offer_current_user_role();

        $actorName = '';
        $actorEmail = '';

        $sessionSources = [
            $_SESSION['user'] ?? null,
            $_SESSION['auth_user'] ?? null,
            $_SESSION['member'] ?? null,
            $_SESSION['seller'] ?? null,
            $_SESSION['admin'] ?? null,
        ];

        foreach ($sessionSources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $actorName = trim((string) (($source['display_name'] ?? '') ?: (($source['first_name'] ?? '') . ' ' . ($source['last_name'] ?? ''))));
            $actorEmail = trim((string) ($source['email'] ?? ''));
            if ($actorName !== '' || $actorEmail !== '') {
                break;
            }
        }

        try {
            $result = bv_listing_offers_execute(
                "INSERT INTO audit_logs
                    (actor_type, actor_id, actor_name, actor_email, event_type, entity_type, entity_id, action, summary, before_json, after_json, ip_address, user_agent, created_at)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $actorRole !== '' ? $actorRole : 'system',
                    $actorUserId > 0 ? $actorUserId : null,
                    $actorName !== '' ? $actorName : null,
                    $actorEmail !== '' ? $actorEmail : null,
                    $eventType,
                    $entityType,
                    $entityId,
                    $action,
                    mb_substr($summary, 0, 255),
                    !empty($before) ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    !empty($after) ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    bv_offer_client_ip(),
                    bv_offer_user_agent(),
                    bv_offer_now(),
                ]
            );

            return (int) ($result['insert_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('bv_offer_update_status')) {
    function bv_offer_update_status(
        int $offerId,
        string $newStatus,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $actionNote = null,
        array $extraFields = []
    ): bool {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return false;
        }

        $newStatus = strtolower(trim($newStatus));
        if (!in_array($newStatus, bv_offer_allowed_statuses(), true)) {
            return false;
        }

        $oldStatus = strtolower(trim((string) ($offer['status'] ?? '')));
        if ($oldStatus === $newStatus && empty($extraFields)) {
            return true;
        }

        $allowedExtra = [
            'latest_offer_price',
            'agreed_price',
            'approved_message_id',
            'approved_at',
            'expires_at',
            'completed_order_id',
            'last_message_at',
        ];

        $sets = ['status = ?', 'updated_at = ?'];
        $params = [$newStatus, bv_offer_now()];

        foreach ($allowedExtra as $field) {
            if (array_key_exists($field, $extraFields)) {
                $sets[] = $field . ' = ?';
                $params[] = $extraFields[$field];
            }
        }

        $params[] = $offerId;

        bv_listing_offers_execute(
            "UPDATE listing_offers
             SET " . implode(', ', $sets) . "
             WHERE id = ?
             LIMIT 1",
            $params
        );

        bv_offer_status_log_insert($offerId, $oldStatus !== '' ? $oldStatus : null, $newStatus, $actionNote, $actorUserId, $actorRole);

        $after = bv_offer_get_by_id($offerId);
        bv_offer_audit_log(
            'offer.status_updated',
            'listing_offer',
            $offerId,
            'status_update',
            $actionNote !== null && $actionNote !== '' ? $actionNote : ('Offer status changed from ' . $oldStatus . ' to ' . $newStatus . '.'),
            $offer,
            $after ?? [],
            $actorUserId,
            $actorRole
        );

        return true;
    }
}

if (!function_exists('bv_offer_generate_token')) {
    function bv_offer_generate_token(int $length = 64): string
    {
        $length = max(32, min(128, $length));
        $token = bin2hex(random_bytes((int) ceil($length / 2)));
        return substr($token, 0, $length);
    }
}

if (!function_exists('bv_offer_get_active_checkout_token')) {
    function bv_offer_get_active_checkout_token(int $offerId): ?array
    {
        if ($offerId <= 0) {
            return null;
        }

        return bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE offer_id = ?
               AND status = 'active'
             ORDER BY id DESC
             LIMIT 1",
            [$offerId]
        );
    }
}

if (!function_exists('bv_offer_cancel_active_checkout_tokens')) {
    function bv_offer_cancel_active_checkout_tokens(int $offerId, ?int $actorUserId = null, ?string $actorRole = null, string $note = 'Offer token cancelled.'): int
    {
        if ($offerId <= 0) {
            return 0;
        }

        $tokens = bv_listing_offers_query_all(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE offer_id = ?
               AND status = 'active'
             ORDER BY id DESC",
            [$offerId]
        );

        $count = 0;
        foreach ($tokens as $token) {
            bv_listing_offers_execute(
                "UPDATE listing_offer_checkout_tokens
                 SET status = 'cancelled',
                     updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [
                    bv_offer_now(),
                    (int) ($token['id'] ?? 0),
                ]
            );

            bv_offer_audit_log(
                'offer.checkout_token_cancelled',
                'listing_offer_checkout_token',
                (int) ($token['id'] ?? 0),
                'cancel',
                $note,
                $token,
                array_merge($token, ['status' => 'cancelled']),
                $actorUserId,
                $actorRole
            );
            $count++;
        }

        return $count;
    }
}

if (!function_exists('bv_offer_create_checkout_token')) {
    function bv_offer_create_checkout_token(
        int $offerId,
        float $agreedPrice,
        int $hoursValid = 24,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): ?array {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return null;
        }

        $agreedPrice = round($agreedPrice, 2);
        if ($agreedPrice <= 0) {
            throw new InvalidArgumentException('Invalid agreed price.');
        }

        $hoursValid = max(1, min(168, $hoursValid));
        $now = bv_offer_now();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $hoursValid . ' hour'));
        $token = bv_offer_generate_token(64);

        bv_offer_cancel_active_checkout_tokens(
            $offerId,
            $actorUserId,
            $actorRole,
            'Previous active checkout token cancelled before creating a new one.'
        );

        $result = bv_listing_offers_execute(
            "INSERT INTO listing_offer_checkout_tokens
                (offer_id, listing_id, buyer_user_id, seller_user_id, token, currency, agreed_price, status, expires_at, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)",
            [
                $offerId,
                (int) ($offer['listing_id'] ?? 0),
                (int) ($offer['buyer_user_id'] ?? 0),
                (int) ($offer['seller_user_id'] ?? 0),
                $token,
                (string) ($offer['currency'] ?? 'USD'),
                $agreedPrice,
                $expiresAt,
                $now,
                $now,
            ]
        );

        $tokenId = (int) ($result['insert_id'] ?? 0);
        $created = bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE id = ?
             LIMIT 1",
            [$tokenId]
        );

        bv_offer_audit_log(
            'offer.checkout_token_created',
            'listing_offer_checkout_token',
            $tokenId,
            'create',
            'Offer checkout token created.',
            [],
            $created ?? [],
            $actorUserId,
            $actorRole
        );

        return $created;
    }
}

if (!function_exists('bv_offer_validate_checkout_token')) {
    function bv_offer_validate_checkout_token(string $token, ?int $buyerUserId = null): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE token = ?
             LIMIT 1",
            [$token]
        );

        if (!$row) {
            return null;
        }

        if (strtolower(trim((string) ($row['status'] ?? ''))) !== 'active') {
            return null;
        }

        $buyerUserId = $buyerUserId ?? bv_offer_current_user_id();
        if ($buyerUserId > 0 && $buyerUserId !== (int) ($row['buyer_user_id'] ?? 0)) {
            return null;
        }

        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return null;
        }

        return $row;
    }
}

if (!function_exists('bv_offer_mark_checkout_token_used')) {
    function bv_offer_mark_checkout_token_used(
        int $tokenId,
        int $orderId,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): bool {
        if ($tokenId <= 0) {
            return false;
        }

        $token = bv_listing_offers_query_one(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE id = ?
             LIMIT 1",
            [$tokenId]
        );

        if (!$token) {
            return false;
        }

        if (strtolower(trim((string) ($token['status'] ?? ''))) !== 'active') {
            return false;
        }

        $after = $token;
        $after['status'] = 'used';
        $after['used_at'] = bv_offer_now();
        $after['order_id'] = $orderId;

        bv_listing_offers_execute(
            "UPDATE listing_offer_checkout_tokens
             SET status = 'used',
                 used_at = ?,
                 order_id = ?,
                 updated_at = ?
             WHERE id = ?
             LIMIT 1",
            [
                $after['used_at'],
                $orderId > 0 ? $orderId : null,
                bv_offer_now(),
                $tokenId,
            ]
        );

        bv_offer_audit_log(
            'offer.checkout_token_used',
            'listing_offer_checkout_token',
            $tokenId,
            'use',
            'Offer checkout token used for order.',
            $token,
            $after,
            $actorUserId,
            $actorRole
        );

        return true;
    }
}

if (!function_exists('bv_offer_accept')) {
    function bv_offer_accept(
        int $offerId,
        float $agreedPrice,
        ?int $approvedMessageId = null,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $actionNote = null
    ): ?array {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return null;
        }

        $actorUserId = $actorUserId ?? bv_offer_current_user_id();
        $actorRole = $actorRole ?? bv_offer_current_user_role();

        if (!bv_offer_current_user_can_seller_manage($offer, $actorUserId, $actorRole)) {
            throw new RuntimeException('Permission denied.');
        }

        $agreedPrice = round($agreedPrice, 2);
        if ($agreedPrice <= 0) {
            throw new InvalidArgumentException('Invalid agreed price.');
        }

        $tokenRow = null;

        try {
            bv_listing_offers_begin_transaction();

            $acceptedAt = bv_offer_now();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            bv_offer_update_status(
                $offerId,
                'buyer_checkout_ready',
                $actorUserId,
                $actorRole,
                $actionNote ?: 'Seller accepted offer and opened 24-hour checkout window.',
                [
                    'agreed_price' => $agreedPrice,
                    'approved_message_id' => $approvedMessageId,
                    'approved_at' => $acceptedAt,
                    'expires_at' => $expiresAt,
                    'last_message_at' => $acceptedAt,
                ]
            );

            bv_offer_insert_message([
                'offer_id' => $offerId,
                'sender_user_id' => $actorUserId,
                'sender_role' => bv_offer_is_admin_role($actorRole) ? 'admin' : 'seller',
                'message_type' => 'accept',
                'offer_price' => $agreedPrice,
                'message_text' => $actionNote ?: 'Offer accepted by seller. Buyer can checkout within 24 hours.',
            ]);

            $tokenRow = bv_offer_create_checkout_token($offerId, $agreedPrice, 24, $actorUserId, $actorRole);

            bv_listing_offers_commit();
        } catch (Throwable $e) {
            bv_listing_offers_rollback();
            throw $e;
        }

        return [
            'offer' => bv_offer_get_by_id($offerId),
            'token' => $tokenRow,
        ];
    }
}

if (!function_exists('bv_offer_reject')) {
    function bv_offer_reject(
        int $offerId,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $actionNote = null
    ): bool {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return false;
        }

        $actorUserId = $actorUserId ?? bv_offer_current_user_id();
        $actorRole = $actorRole ?? bv_offer_current_user_role();

        if (!bv_offer_current_user_can_seller_manage($offer, $actorUserId, $actorRole)) {
            throw new RuntimeException('Permission denied.');
        }

        try {
            bv_listing_offers_begin_transaction();

            bv_offer_cancel_active_checkout_tokens($offerId, $actorUserId, $actorRole, 'Offer rejected by seller.');
            bv_offer_update_status(
                $offerId,
                'rejected',
                $actorUserId,
                $actorRole,
                $actionNote ?: 'Offer rejected by seller.'
            );

            bv_offer_insert_message([
                'offer_id' => $offerId,
                'sender_user_id' => $actorUserId,
                'sender_role' => bv_offer_is_admin_role($actorRole) ? 'admin' : 'seller',
                'message_type' => 'reject',
                'message_text' => $actionNote ?: 'Offer rejected by seller.',
            ]);

            bv_listing_offers_commit();
            return true;
        } catch (Throwable $e) {
            bv_listing_offers_rollback();
            throw $e;
        }
    }
}

if (!function_exists('bv_offer_cancel_by_buyer')) {
    function bv_offer_cancel_by_buyer(
        int $offerId,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $actionNote = null
    ): bool {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return false;
        }

        $actorUserId = $actorUserId ?? bv_offer_current_user_id();
        $actorRole = $actorRole ?? bv_offer_current_user_role();

        if (!bv_offer_current_user_can_buyer_reply($offer, $actorUserId, $actorRole) && !bv_offer_is_admin_role($actorRole)) {
            throw new RuntimeException('Permission denied.');
        }

        try {
            bv_listing_offers_begin_transaction();

            bv_offer_cancel_active_checkout_tokens($offerId, $actorUserId, $actorRole, 'Offer cancelled by buyer.');
            bv_offer_update_status(
                $offerId,
                'cancelled',
                $actorUserId,
                $actorRole,
                $actionNote ?: 'Offer cancelled by buyer.'
            );

            bv_offer_insert_message([
                'offer_id' => $offerId,
                'sender_user_id' => $actorUserId,
                'sender_role' => bv_offer_is_admin_role($actorRole) ? 'admin' : 'buyer',
                'message_type' => 'system',
                'message_text' => $actionNote ?: 'Offer cancelled by buyer.',
            ]);

            bv_listing_offers_commit();
            return true;
        } catch (Throwable $e) {
            bv_listing_offers_rollback();
            throw $e;
        }
    }
}

if (!function_exists('bv_offer_mark_completed')) {
    function bv_offer_mark_completed(
        int $offerId,
        int $orderId,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $actionNote = null
    ): bool {
        $offer = bv_offer_get_by_id($offerId);
        if (!$offer) {
            return false;
        }

        $actorUserId = $actorUserId ?? bv_offer_current_user_id();
        $actorRole = $actorRole ?? bv_offer_current_user_role();

        bv_offer_update_status(
            $offerId,
            'completed',
            $actorUserId,
            $actorRole,
            $actionNote ?: 'Offer converted to completed order.',
            [
                'completed_order_id' => $orderId > 0 ? $orderId : null,
            ]
        );

        return true;
    }
}

if (!function_exists('bv_offer_expire_due_tokens')) {
    function bv_offer_expire_due_tokens(?int $actorUserId = null, ?string $actorRole = null): int
    {
        $tokens = bv_listing_offers_query_all(
            "SELECT *
             FROM listing_offer_checkout_tokens
             WHERE status = 'active'
               AND expires_at IS NOT NULL
               AND expires_at < ?
             ORDER BY id ASC",
            [bv_offer_now()]
        );

        $count = 0;
        foreach ($tokens as $token) {
            $tokenId = (int) ($token['id'] ?? 0);
            if ($tokenId <= 0) {
                continue;
            }

            bv_listing_offers_execute(
                "UPDATE listing_offer_checkout_tokens
                 SET status = 'expired',
                     updated_at = ?
                 WHERE id = ?
                 LIMIT 1",
                [
                    bv_offer_now(),
                    $tokenId,
                ]
            );

            $after = $token;
            $after['status'] = 'expired';

            bv_offer_audit_log(
                'offer.checkout_token_expired',
                'listing_offer_checkout_token',
                $tokenId,
                'expire',
                'Offer checkout token expired automatically.',
                $token,
                $after,
                $actorUserId,
                $actorRole
            );

            $count++;
        }

        return $count;
    }
}

if (!function_exists('bv_offer_expire_due_offers')) {
    function bv_offer_expire_due_offers(?int $actorUserId = null, ?string $actorRole = null): int
    {
        $offers = bv_listing_offers_query_all(
            "SELECT *
             FROM listing_offers
             WHERE status IN ('seller_accepted','buyer_checkout_ready')
               AND expires_at IS NOT NULL
               AND expires_at < ?
             ORDER BY id ASC",
            [bv_offer_now()]
        );

        $count = 0;
        foreach ($offers as $offer) {
            $offerId = (int) ($offer['id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }

            try {
                bv_listing_offers_begin_transaction();

                bv_offer_cancel_active_checkout_tokens(
                    $offerId,
                    $actorUserId,
                    $actorRole,
                    'Checkout token expired because offer expired.'
                );

                bv_offer_update_status(
                    $offerId,
                    'expired',
                    $actorUserId,
                    $actorRole,
                    'Offer expired automatically after checkout window ended.'
                );

                bv_offer_insert_message([
                    'offer_id' => $offerId,
                    'sender_user_id' => 0,
                    'sender_role' => 'system',
                    'message_type' => 'expire_notice',
                    'message_text' => 'This offer expired automatically because the 24-hour checkout window ended.',
                ]);

                bv_listing_offers_commit();
                $count++;
            } catch (Throwable $e) {
                bv_listing_offers_rollback();
            }
        }

        return $count;
    }
}

if (!function_exists('bv_offer_run_expiry_maintenance')) {
    function bv_offer_run_expiry_maintenance(?int $actorUserId = null, ?string $actorRole = null): array
    {
        return [
            'expired_tokens' => bv_offer_expire_due_tokens($actorUserId, $actorRole),
            'expired_offers' => bv_offer_expire_due_offers($actorUserId, $actorRole),
        ];
    }
}

if (!function_exists('bv_offer_admin_csrf_token')) {
    function bv_offer_admin_csrf_token(string $scope = 'offer_admin_actions'): string
    {
        bv_listing_offers_require_session();

        if (empty($_SESSION['_csrf_listing_offers'][$scope]) || !is_string($_SESSION['_csrf_listing_offers'][$scope])) {
            $_SESSION['_csrf_listing_offers'][$scope] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_listing_offers'][$scope];
    }
}

if (!function_exists('bv_offer_admin_verify_csrf')) {
    function bv_offer_admin_verify_csrf(?string $token, string $scope = 'offer_admin_actions'): bool
    {
        bv_listing_offers_require_session();

        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION['_csrf_listing_offers'][$scope] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('bv_offer_status_badge')) {
    function bv_offer_status_badge(string $status): array
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'open':
                return ['Open', '#1d4ed8', '#dbeafe'];
            case 'seller_accepted':
            case 'buyer_checkout_ready':
                return ['Checkout Ready', '#166534', '#dcfce7'];
            case 'completed':
                return ['Completed', '#14532d', '#bbf7d0'];
            case 'expired':
                return ['Expired', '#9a3412', '#ffedd5'];
            case 'cancelled':
                return ['Cancelled', '#6b7280', '#f3f4f6'];
            case 'rejected':
                return ['Rejected', '#991b1b', '#fee2e2'];
            default:
                return [ucfirst($status !== '' ? $status : 'Unknown'), '#374151', '#e5e7eb'];
        }
    }
}

if (!function_exists('bv_offer_format_money')) {
    function bv_offer_format_money($amount, ?string $currency = null): string
    {
        if (!is_numeric($amount)) {
            return '—';
        }

        $currency = strtoupper(trim((string) ($currency ?: 'USD')));
        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('bv_offer_redirect_url')) {
    function bv_offer_redirect_url(string $baseUrl, array $params = []): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            $baseUrl = 'offer.php';
        }

        $parts = parse_url($baseUrl);
        $path = $parts['path'] ?? $baseUrl;
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $qs = http_build_query($query);
        return $qs !== '' ? ($path . '?' . $qs) : $path;
    }
}