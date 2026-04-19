<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$__bv_member_dir = __DIR__;
$__bv_root_dir = dirname(__DIR__);

$__guard_candidates = [
    $__bv_member_dir . '/_guard.php',
    $__bv_member_dir . '/auth_bootstrap.php',
    $__bv_root_dir . '/include/auth.php',
    $__bv_root_dir . '/includes/auth.php',
    $__bv_root_dir . '/config/auth.php',
];

foreach ($__guard_candidates as $__guard_file) {
    if (is_file($__guard_file)) {
        require_once $__guard_file;
    }
}

$__db_candidates = [
    $__bv_root_dir . '/includes/db.php',
    $__bv_root_dir . '/include/db.php',
    $__bv_root_dir . '/config/db.php',
    $__bv_root_dir . '/db.php',
];

foreach ($__db_candidates as $__db_file) {
    if (is_file($__db_file)) {
        $___returned = require_once $__db_file;
        if ($___returned instanceof PDO && !isset($pdo)) {
            $pdo = $___returned;
        }
    }
}

if (!function_exists('bv_member_pdo')) {
    function bv_member_pdo(): PDO
    {
        global $pdo, $conn, $db;

        if ($pdo instanceof PDO) {
            return $pdo;
        }
        if ($conn instanceof PDO) {
            return $conn;
        }
        if ($db instanceof PDO) {
            return $db;
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            return $GLOBALS['conn'];
        }
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
            return $GLOBALS['db'];
        }

        http_response_code(500);
        exit('PDO connection not available.');
    }
}

if (!function_exists('bv_member_e')) {
    function bv_member_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('bv_member_h')) {
    function bv_member_h($value): string
    {
        return bv_member_e($value);
    }
}

if (!function_exists('bv_member_table_exists')) {
    function bv_member_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute([':table' => $table]);
            $cache[$table] = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

if (!function_exists('bv_member_table_columns')) {
    function bv_member_table_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $columns = [];
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = $row;
                }
            }
            $cache[$table] = $columns;
        } catch (Throwable $e) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }
}

if (!function_exists('bv_member_pick_column')) {
    function bv_member_pick_column(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('bv_member_enum_options')) {
    function bv_member_enum_options(array $columns, ?string $field): array
    {
        if ($field === null || !isset($columns[$field])) {
            return [];
        }

        $type = (string)($columns[$field]['Type'] ?? '');
        preg_match_all("/'([^']+)'/", $type, $matches);
        return $matches[1] ?? [];
    }
}

if (!function_exists('bv_member_flash_set')) {
    function bv_member_flash_set(string $type, string $message): void
    {
        $_SESSION['member_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('bv_member_flash_get')) {
    function bv_member_flash_get(): ?array
    {
        if (!isset($_SESSION['member_flash']) || !is_array($_SESSION['member_flash'])) {
            return null;
        }

        $flash = $_SESSION['member_flash'];
        unset($_SESSION['member_flash']);
        return $flash;
    }
}

if (!function_exists('bv_member_csrf_token')) {
    function bv_member_csrf_token(): string
    {
        if (empty($_SESSION['member_csrf_token']) || !is_string($_SESSION['member_csrf_token'])) {
            $_SESSION['member_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['member_csrf_token'];
    }
}

if (!function_exists('bv_member_verify_csrf')) {
    function bv_member_verify_csrf(?string $token): bool
    {
        $sessionToken = (string)($_SESSION['member_csrf_token'] ?? '');
        return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('bv_member_redirect')) {
    function bv_member_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('bv_member_safe_url')) {
    function bv_member_safe_url(?string $url, string $fallback): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return $fallback;
        }
        if ($url[0] !== '/' || preg_match('~^//~', $url)) {
            return $fallback;
        }
        return $url;
    }
}

if (!function_exists('bv_member_current_user')) {
    function bv_member_current_user(): ?array
    {
        $sources = [
            $_SESSION['user'] ?? null,
            $_SESSION['member'] ?? null,
        ];

        foreach ($sources as $src) {
            if (!is_array($src)) {
                continue;
            }
            $id = (int)($src['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            return [
                'id' => $id,
                'first_name' => (string)($src['first_name'] ?? ''),
                'last_name' => (string)($src['last_name'] ?? ''),
                'email' => (string)($src['email'] ?? ''),
                'role' => strtolower(trim((string)($src['role'] ?? 'user'))),
                'seller_application_status' => strtolower(trim((string)($src['seller_application_status'] ?? ''))),
            ];
        }

        $legacyId = 0;
        if (!empty($_SESSION['user_id'])) {
            $legacyId = (int)$_SESSION['user_id'];
        } elseif (!empty($_SESSION['member_id'])) {
            $legacyId = (int)$_SESSION['member_id'];
        }

        if ($legacyId <= 0) {
            return null;
        }

        return [
            'id' => $legacyId,
            'first_name' => (string)($_SESSION['user_first_name'] ?? $_SESSION['member_first_name'] ?? ''),
            'last_name' => (string)($_SESSION['user_last_name'] ?? $_SESSION['member_last_name'] ?? ''),
            'email' => (string)($_SESSION['user_email'] ?? $_SESSION['member_email'] ?? ''),
            'role' => strtolower(trim((string)($_SESSION['user_role'] ?? $_SESSION['member_role'] ?? 'user'))),
            'seller_application_status' => strtolower(trim((string)($_SESSION['seller_application_status'] ?? ''))),
        ];
    }
}

if (!function_exists('bv_member_user_display_name')) {
    function bv_member_user_display_name(array $user): string
    {
        $full = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'Member';
    }
}

if (!function_exists('bv_member_resolve_seller_status')) {
    function bv_member_resolve_seller_status(PDO $pdo, array $user): string
    {
        $sessionStatus = strtolower(trim((string)($user['seller_application_status'] ?? '')));
        if ($sessionStatus !== '') {
            return $sessionStatus;
        }

        if (!bv_member_table_exists($pdo, 'seller_applications')) {
            return '';
        }

        $columns = bv_member_table_columns($pdo, 'seller_applications');
        $userIdCol = bv_member_pick_column($columns, ['user_id']);
        $statusCol = bv_member_pick_column($columns, ['application_status', 'status']);
        $orderCol = bv_member_pick_column($columns, ['updated_at', 'submitted_at', 'created_at', 'id']);

        if ($userIdCol === null || $statusCol === null) {
            return '';
        }

        $sql = 'SELECT `' . $statusCol . '` FROM `seller_applications` WHERE `' . $userIdCol . '` = :user_id';
        if ($orderCol !== null) {
            $sql .= ' ORDER BY `' . $orderCol . '` DESC';
        }
        $sql .= ' LIMIT 1';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => (int)$user['id']]);
            return strtolower(trim((string)$stmt->fetchColumn()));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('bv_member_is_seller_approved')) {
    function bv_member_is_seller_approved(PDO $pdo, array $user): bool
    {
        $role = strtolower(trim((string)($user['role'] ?? 'user')));
        $status = bv_member_resolve_seller_status($pdo, $user);

        if ($role === 'seller' && ($status === '' || $status === 'approved')) {
            return true;
        }

        return $status === 'approved';
    }
}

if (!function_exists('bv_member_require_login')) {
    function bv_member_require_login(): array
    {
        $user = bv_member_current_user();
        if (!$user || (int)($user['id'] ?? 0) <= 0) {
            $redirect = '/login.php?redirect=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? '/member/index.php'));
            bv_member_redirect($redirect);
        }
        return $user;
    }
}

if (!function_exists('bv_member_require_seller')) {
    function bv_member_require_seller(PDO $pdo): array
    {
        $user = bv_member_require_login();
        if (!bv_member_is_seller_approved($pdo, $user)) {
            bv_member_flash_set('error', 'Seller access is available only after your seller account is approved.');
            bv_member_redirect('/member/index.php');
        }
        return $user;
    }
}

if (!function_exists('bv_member_listing_columns')) {
    function bv_member_listing_columns(PDO $pdo): array
    {
        $columns = bv_member_table_columns($pdo, 'listings');

        return [
            'all' => $columns,
            'id' => bv_member_pick_column($columns, ['id']),
            'seller_id' => bv_member_pick_column($columns, ['seller_id', 'user_id', 'member_id', 'created_by']),
            'title' => bv_member_pick_column($columns, ['title', 'name']),
            'slug' => bv_member_pick_column($columns, ['slug']),
            'sku' => bv_member_pick_column($columns, ['sku']),
            'short_desc' => bv_member_pick_column($columns, ['short_desc', 'short_description', 'excerpt', 'summary']),
            'description' => bv_member_pick_column($columns, ['description', 'long_desc', 'body']),
            'species' => bv_member_pick_column($columns, ['species']),
            'category' => bv_member_pick_column($columns, ['category']),
            'strain' => bv_member_pick_column($columns, ['strain']),
            'grade' => bv_member_pick_column($columns, ['grade', 'tier']),
            'price' => bv_member_pick_column($columns, ['price', 'sale_price']),
            'currency' => bv_member_pick_column($columns, ['currency']),
            'country' => bv_member_pick_column($columns, ['country']),
            'city' => bv_member_pick_column($columns, ['city']),
            'status' => bv_member_pick_column($columns, ['status', 'listing_status']),
            'sale_status' => bv_member_pick_column($columns, ['sale_status']),
            'featured' => bv_member_pick_column($columns, ['featured', 'is_featured']),
            'cover_image' => bv_member_pick_column($columns, ['cover_image', 'main_image', 'image_url', 'image']),
            'meta_title' => bv_member_pick_column($columns, ['meta_title']),
            'meta_description' => bv_member_pick_column($columns, ['meta_description']),
            'selling_method' => bv_member_pick_column($columns, ['selling_method']),
            'stock_total' => bv_member_pick_column($columns, ['stock_total', 'stock', 'quantity', 'qty']),
            'stock_sold' => bv_member_pick_column($columns, ['stock_sold', 'sold_qty', 'qty_sold']),
            'stock_available' => bv_member_pick_column($columns, ['stock_available', 'available_qty', 'qty_available']),
            'auction_min' => bv_member_pick_column($columns, ['auction_min_price']),
            'auction_max' => bv_member_pick_column($columns, ['auction_max_price']),
            'min_offer_price' => bv_member_pick_column($columns, ['min_offer_price']),
            'created_at' => bv_member_pick_column($columns, ['created_at', 'created']),
            'updated_at' => bv_member_pick_column($columns, ['updated_at', 'updated']),
            'is_published' => bv_member_pick_column($columns, ['is_published']),
            'is_active' => bv_member_pick_column($columns, ['is_active', 'active']),
        ];
    }
}

if (!function_exists('bv_member_listing_status_options')) {
    function bv_member_listing_status_options(array $listingCols): array
    {
        $enum = bv_member_enum_options($listingCols['all'] ?? [], $listingCols['status'] ?? null);
        $safe = ['draft', 'active', 'published', 'hidden', 'inactive'];

        if (!$enum) {
            return $safe;
        }

        $filtered = array_values(array_intersect($enum, $safe));
        return $filtered ?: $enum;
    }
}

if (!function_exists('bv_member_listing_method_options')) {
    function bv_member_listing_method_options(array $listingCols): array
    {
        return bv_member_enum_options($listingCols['all'] ?? [], $listingCols['selling_method'] ?? null);
    }
}

if (!function_exists('bv_member_status_token')) {
    function bv_member_status_token(?string $value): string
    {
        $value = strtolower(trim((string)$value));
        return str_replace(['-', ' '], '_', $value);
    }
}

if (!function_exists('bv_member_status_label')) {
    function bv_member_status_label(?string $value): string
    {
        $token = bv_member_status_token($value);
        if ($token === '') {
            return '—';
        }
        return ucwords(str_replace('_', ' ', $token));
    }
}

if (!function_exists('bv_member_public_listing_url')) {
    function bv_member_public_listing_url(array $row): string
    {
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }
        return '/listing.php?id=' . (int)($row['id'] ?? 0);
    }
}

if (!function_exists('bv_member_resolve_image_expr')) {
    function bv_member_resolve_image_expr(PDO $pdo, array $listingCols): string
    {
        $idCol = $listingCols['id'] ?? 'id';
        $coverCol = $listingCols['cover_image'];
        $expr = $coverCol ? 'l.`' . $coverCol . '`' : 'NULL';

        if (bv_member_table_exists($pdo, 'listing_media')) {
            $mediaCols = bv_member_table_columns($pdo, 'listing_media');
            $pathCol = bv_member_pick_column($mediaCols, ['media_url', 'file_path', 'file_url', 'path', 'url', 'storage_path']);
            $listingIdCol = bv_member_pick_column($mediaCols, ['listing_id']);
            $coverFlagCol = bv_member_pick_column($mediaCols, ['is_cover']);
            $sortCol = bv_member_pick_column($mediaCols, ['sort_order']);
            $statusCol = bv_member_pick_column($mediaCols, ['status']);
            $idMediaCol = bv_member_pick_column($mediaCols, ['id']);

            if ($pathCol && $listingIdCol) {
                $whereParts = ['lm.`' . $listingIdCol . '` = l.`' . $idCol . '`'];
                if ($statusCol) {
                    $whereParts[] = "lm.`{$statusCol}` = 'active'";
                }
                $orderParts = [];
                if ($coverFlagCol) {
                    $orderParts[] = 'COALESCE(lm.`' . $coverFlagCol . '`,0) DESC';
                }
                if ($sortCol) {
                    $orderParts[] = 'COALESCE(lm.`' . $sortCol . '`,999999) ASC';
                }
                if ($idMediaCol) {
                    $orderParts[] = 'lm.`' . $idMediaCol . '` ASC';
                }
                $expr = 'COALESCE(' . $expr . ', (SELECT lm.`' . $pathCol . '` FROM `listing_media` lm WHERE ' . implode(' AND ', $whereParts) . ' ORDER BY ' . implode(', ', $orderParts ?: ['1']) . ' LIMIT 1))';
            }
        }

        if (bv_member_table_exists($pdo, 'listing_images')) {
            $imageCols = bv_member_table_columns($pdo, 'listing_images');
            $pathCol = bv_member_pick_column($imageCols, ['image_path', 'image', 'file_path', 'image_url']);
            $listingIdCol = bv_member_pick_column($imageCols, ['listing_id']);
            $coverFlagCol = bv_member_pick_column($imageCols, ['is_cover', 'is_main', 'is_primary']);
            $sortCol = bv_member_pick_column($imageCols, ['sort_order']);
            $imageIdCol = bv_member_pick_column($imageCols, ['id']);

            if ($pathCol && $listingIdCol) {
                $orderParts = [];
                if ($coverFlagCol) {
                    $orderParts[] = 'COALESCE(li.`' . $coverFlagCol . '`,0) DESC';
                }
                if ($sortCol) {
                    $orderParts[] = 'COALESCE(li.`' . $sortCol . '`,999999) ASC';
                }
                if ($imageIdCol) {
                    $orderParts[] = 'li.`' . $imageIdCol . '` ASC';
                }

                $expr = 'COALESCE(' . $expr . ', (SELECT li.`' . $pathCol . '` FROM `listing_images` li WHERE li.`' . $listingIdCol . '` = l.`' . $idCol . '` ORDER BY ' . implode(', ', $orderParts ?: ['1']) . ' LIMIT 1))';
            }
        }

        return $expr;
    }
}

if (!function_exists('bv_member_normalize_image_url')) {
    function bv_member_normalize_image_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $path) || strpos($path, 'data:image/') === 0) {
            return $path;
        }
        if ($path[0] === '/') {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('bv_member_local_path')) {
    function bv_member_local_path(?string $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '' || preg_match('~^https?://~i', $path)) {
            return null;
        }
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}

if (!function_exists('bv_member_delete_local_file')) {
    function bv_member_delete_local_file(?string $path): void
    {
        $fullPath = bv_member_local_path($path);
        if ($fullPath !== null && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

if (!function_exists('bv_member_store_listing_file')) {
    function bv_member_store_listing_file(array $file, int $listingId): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded image.');
        }

        $size = (int)($file['size'] ?? 0);
        $maxBytes = 8 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Image is too large. Maximum size is 8MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpName);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, WEBP, and GIF are allowed.');
        }

        $relativeDir = '/uploads/listings/' . $listingId;
        $absoluteDir = dirname(__DIR__) . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $fileName = 'seller_' . $listingId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $absolutePath = $absoluteDir . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        return $relativeDir . '/' . $fileName;
    }
}

if (!function_exists('bv_member_sync_cover_media')) {
    function bv_member_sync_cover_media(PDO $pdo, int $listingId, string $newPath, string $title, ?string $oldPath = null): void
    {
        if (bv_member_table_exists($pdo, 'listing_images')) {
            $cols = bv_member_table_columns($pdo, 'listing_images');
            $pathCol = bv_member_pick_column($cols, ['image_path', 'file_path', 'path', 'image_url']);
            $altCol = bv_member_pick_column($cols, ['alt_text']);
            $sortCol = bv_member_pick_column($cols, ['sort_order']);
            $coverCol = bv_member_pick_column($cols, ['is_cover']);
            if ($pathCol && isset($cols['listing_id'])) {
                if ($coverCol) {
                    $pdo->prepare('UPDATE listing_images SET `' . $coverCol . '` = 0 WHERE listing_id = :listing_id')->execute([':listing_id' => $listingId]);
                }

                $fields = ['listing_id', $pathCol];
                $values = [':listing_id', ':path'];
                $params = [':listing_id' => $listingId, ':path' => $newPath];

                if ($altCol) {
                    $fields[] = $altCol;
                    $values[] = ':alt_text';
                    $params[':alt_text'] = $title;
                }
                if ($sortCol) {
                    $fields[] = $sortCol;
                    $values[] = ':sort_order';
                    $params[':sort_order'] = 0;
                }
                if ($coverCol) {
                    $fields[] = $coverCol;
                    $values[] = ':is_cover';
                    $params[':is_cover'] = 1;
                }

                try {
                    $sql = 'INSERT INTO listing_images (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $values) . ')';
                    $pdo->prepare($sql)->execute($params);
                } catch (Throwable $e) {
                    // ignore sync failure quietly
                }
            }
        }

        if (bv_member_table_exists($pdo, 'listing_media')) {
            $cols = bv_member_table_columns($pdo, 'listing_media');
            if (isset($cols['listing_id'])) {
                try {
                    $fieldMap = [
                        'listing_id' => $listingId,
                        'media_type' => isset($cols['media_type']) ? 'image' : null,
                        'mime_type' => isset($cols['mime_type']) ? 'image/jpeg' : null,
                        'original_name' => isset($cols['original_name']) ? basename($newPath) : null,
                        'file_name' => isset($cols['file_name']) ? basename($newPath) : null,
                        'media_url' => isset($cols['media_url']) ? $newPath : null,
                        'file_url' => isset($cols['file_url']) ? $newPath : null,
                        'file_path' => isset($cols['file_path']) ? $newPath : null,
                        'path' => isset($cols['path']) ? $newPath : null,
                        'url' => isset($cols['url']) ? $newPath : null,
                        'storage_path' => isset($cols['storage_path']) ? $newPath : null,
                        'is_cover' => isset($cols['is_cover']) ? 1 : null,
                        'sort_order' => isset($cols['sort_order']) ? 0 : null,
                        'status' => isset($cols['status']) ? 'active' : null,
                        'created_at' => isset($cols['created_at']) ? date('Y-m-d H:i:s') : null,
                        'updated_at' => isset($cols['updated_at']) ? date('Y-m-d H:i:s') : null,
                    ];

                    if (isset($cols['is_cover'])) {
                        $pdo->prepare('UPDATE listing_media SET `is_cover` = 0 WHERE listing_id = :listing_id')->execute([':listing_id' => $listingId]);
                    }

                    $fields = [];
                    $placeholders = [];
                    $params = [];
                    foreach ($fieldMap as $col => $val) {
                        if (!isset($cols[$col]) || $val === null) {
                            continue;
                        }
                        $fields[] = '`' . $col . '`';
                        $placeholders[] = ':' . $col;
                        $params[':' . $col] = $val;
                    }

                    if ($fields) {
                        $sql = 'INSERT INTO listing_media (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
                        $pdo->prepare($sql)->execute($params);
                    }
                } catch (Throwable $e) {
                    // ignore sync failure quietly
                }
            }
        }

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $newPath) {
            bv_member_delete_local_file($oldPath);
        }
    }
}

if (!function_exists('bv_member_listing_counts')) {
    function bv_member_listing_counts(PDO $pdo, array $listingCols, int $sellerId): array
    {
        $sellerCol = $listingCols['seller_id'] ?? null;
        if ($sellerCol === null) {
            return [
                'total' => 0,
                'active' => 0,
                'draft' => 0,
                'sold' => 0,
            ];
        }

        $statusCol = $listingCols['status'] ?? null;
        $saleStatusCol = $listingCols['sale_status'] ?? null;

        $sql = 'SELECT COUNT(*) AS total';
        if ($statusCol) {
            $sql .= ', SUM(CASE WHEN LOWER(COALESCE(`' . $statusCol . '`,"")) IN ("active","published") THEN 1 ELSE 0 END) AS active';
            $sql .= ', SUM(CASE WHEN LOWER(COALESCE(`' . $statusCol . '`,"")) = "draft" THEN 1 ELSE 0 END) AS draft';
        } else {
            $sql .= ', 0 AS active, 0 AS draft';
        }
        if ($saleStatusCol) {
            $sql .= ', SUM(CASE WHEN LOWER(COALESCE(`' . $saleStatusCol . '`,"")) IN ("sold","completed") THEN 1 ELSE 0 END) AS sold';
        } else {
            $sql .= ', 0 AS sold';
        }
        $sql .= ' FROM `listings` WHERE `' . $sellerCol . '` = :seller_id';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':seller_id' => $sellerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total' => (int)($row['total'] ?? 0),
                'active' => (int)($row['active'] ?? 0),
                'draft' => (int)($row['draft'] ?? 0),
                'sold' => (int)($row['sold'] ?? 0),
            ];
        } catch (Throwable $e) {
            return ['total' => 0, 'active' => 0, 'draft' => 0, 'sold' => 0];
        }
    }
}

if (!function_exists('bv_member_page_begin')) {
    function bv_member_page_begin(string $pageTitle, string $metaDescription = 'Bettavaro member area'): void
    {
        $GLOBALS['pageTitle'] = $pageTitle;
        $GLOBALS['metaDescription'] = $metaDescription;
        $GLOBALS['canonicalUrl'] = '';
        $GLOBALS['metaRobots'] = 'noindex,follow';
        $GLOBALS['bodyClass'] = 'member-page';

        $head = dirname(__DIR__) . '/includes/head.php';
        $menu = dirname(__DIR__) . '/includes/menu.php';
        if (is_file($head)) {
            include $head;
        } else {
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . bv_member_e($pageTitle) . '</title></head><body>';
        }
        if (is_file($menu)) {
            include $menu;
        }
    }
}

if (!function_exists('bv_member_page_end')) {
    function bv_member_page_end(): void
    {
        $footer = dirname(__DIR__) . '/includes/footer.php';
        if (is_file($footer)) {
            include $footer;
        } else {
            echo '</body></html>';
        }
    }
}
