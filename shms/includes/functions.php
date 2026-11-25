<?php
// ============================================================
// Helper Functions — SHMS
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

/* ---------- Session helpers ---------- */

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn($role) {
    startSecureSession();
    $allowedRoles = is_array($role) ? $role : [$role];
    return isset($_SESSION['user_id'], $_SESSION['role'])
        && in_array($_SESSION['role'], $allowedRoles, true);
}

function requireLogin($role, $redirect = null) {
    if (!isLoggedIn($role)) {
        $redirect = $redirect ?? BASE_URL . '/index.php';
        header('Location: ' . $redirect);
        exit;
    }
}

function logout() {
    startSecureSession();
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

/* ---------- Security ---------- */

function sanitize($value) {
    if (!is_string($value)) { throw new InvalidArgumentException('Invalid text value.'); }
    // Store raw text through prepared statements; escape only when rendering HTML.
    return trim($value);
}

function hashPassword($plain) {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

function verifyPassword($plain, $hash) {
    return password_verify($plain, $hash);
}

function generateCSRF() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    startSecureSession();
    return is_string($token) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------- Flash messages ---------- */

function setFlash($type, $message) {
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    startSecureSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $cls = $flash['type'] === 'success' ? 'success'
             : ($flash['type'] === 'error'   ? 'danger'
             : ($flash['type'] === 'warning' ? 'warning' : 'info'));
        echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
           . htmlspecialchars($flash['message'])
           . '<button type="button" class="close" data-dismiss="alert">'
           . '<span>&times;</span></button></div>';
    }
}

/* ---------- Pagination ---------- */

function paginate($totalRows, $perPage, $currentPage) {
    $totalPages = (int)ceil($totalRows / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return ['total' => $totalRows, 'pages' => $totalPages,
            'current' => $currentPage, 'offset' => $offset, 'per_page' => $perPage];
}

/* ---------- Billing helpers ---------- */

function calcMessCharges($residentId, $month, $year) {
    $total = 0.0;
    foreach (getMessChargeDetails($residentId, $month, $year) as $item) { $total += (float)$item['charged_rate']; }
    return round($total, 2);
}

function getPreviousUnpaidDue($residentId, $month, $year) {
    $conn = getDBConnection();
    $residentId = (int)$residentId;
    $month = (int)$month;
    $year = (int)$year;
    $stmt = $conn->prepare(
        "SELECT total_amount,payment_status
         FROM bills
         WHERE resident_id=? AND (`year`<? OR (`year`=? AND `month`<?))
         ORDER BY `year` DESC,`month` DESC,id DESC LIMIT 1"
    );
    $stmt->bind_param('iiii', $residentId, $year, $year, $month);
    $stmt->execute();
    $previousBill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $previousBill && $previousBill['payment_status'] === 'unpaid'
        ? round((float)$previousBill['total_amount'], 2)
        : 0.0;
}

function getMessChargeDetails($residentId, $month, $year) {
    $conn = getDBConnection();
    $rid  = (int)$residentId;
    $m    = (int)$month;
    $y    = (int)$year;

    // Get meal prices
    $prices = [];
    $res = $conn->query("SELECT meal_type, price FROM meal_prices");
    while ($row = $res->fetch_assoc()) {
        $prices[$row['meal_type']] = (float)$row['price'];
    }

    $hasDailyPrices = shmsTableExists('mess_daily_menu');
    $hasSnapshots = shmsColumnExists('mess_attendance', 'breakfast_rate');
    $hasAvailability = $hasDailyPrices && shmsColumnExists('mess_daily_menu', 'breakfast_enabled');
    $select = $hasDailyPrices
        ? "SELECT a.*, d.breakfast_price, d.lunch_price, d.snacks_price, d.dinner_price" .
          ($hasAvailability ? ", d.breakfast_enabled, d.lunch_enabled, d.snacks_enabled, d.dinner_enabled" : "") . "
           FROM mess_attendance a LEFT JOIN mess_daily_menu d ON d.menu_date=a.date"
        : "SELECT a.* FROM mess_attendance a";
    $rows = $conn->query(
        $select . " WHERE a.resident_id=$rid AND MONTH(a.date)=$m AND YEAR(a.date)=$y"
    );
    $details = [];
    while ($row = $rows->fetch_assoc()) {
        foreach (['breakfast','lunch','snacks','dinner'] as $meal) {
            if (!(int)$row[$meal]) { continue; }
            if ($hasAvailability && !(int)($row[$meal . '_enabled'] ?? 0)) { continue; }
            $dailyKey = $meal . '_price';
            $snapshotKey = $meal . '_rate';
            if ($hasSnapshots && $row[$snapshotKey] !== null) {
                $price = (float)$row[$snapshotKey];
            } else {
                $price = $hasDailyPrices && $row[$dailyKey] !== null
                    ? (float)$row[$dailyKey]
                    : (float)($prices[$meal] ?? 0);
            }
            $details[] = ['attendance_date' => $row['date'], 'meal_type' => $meal, 'charged_rate' => round($price, 2)];
        }
    }
    return $details;
}

function calcMiscCharges($residentId, $month, $year) {
    $conn = getDBConnection();
    $rid  = (int)$residentId;
    $m    = (int)$month;
    $y    = (int)$year;
    $res  = $conn->query(
        "SELECT COALESCE(SUM(amount),0) AS total
         FROM miscellaneous_charges
         WHERE resident_id=$rid AND month=$m AND year=$y"
    );
    return (float)$res->fetch_assoc()['total'];
}

function getRoomRent($residentId) {
    $conn = getDBConnection();
    $rid  = (int)$residentId;
    $res  = $conn->query(
        "SELECT rt.price FROM room_allocations ra
         JOIN rooms r       ON ra.room_id = r.id
         JOIN room_types rt ON r.room_type_id = rt.id
         WHERE ra.resident_id=$rid AND ra.is_active=1
         LIMIT 1"
    );
    if ($row = $res->fetch_assoc()) return (float)$row['price'];
    return 0.0;
}

/* ---------- Misc utilities ---------- */

function monthName($m) {
    return date('F', mktime(0,0,0,(int)$m,1));
}

function formatCurrency($amount) {
    return 'Rs. ' . number_format((float)$amount, 2);
}

function shmsTableExists($table) {
    static $cache = [];
    if (isset($cache[$table])) { return $cache[$table]; }
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $cache[$table] = $exists;
}

function shmsColumnExists($table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) { return $cache[$key]; }
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $cache[$key] = $exists;
}

function shmsMonthWeeks($month, $year) {
    $month = (int)$month; $year = (int)$year;
    if ($month < 1 || $month > 12 || $year < 1901 || $year > 2155) {
        throw new InvalidArgumentException('Choose a valid menu month and year.');
    }
    $daysInMonth = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    $weeks = [];
    $weekNumber = 1;
    for ($day=1; $day<=$daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $weeks[$weekNumber][] = $date;
        // A calendar week ends on Sunday. The first/last week may be partial.
        if ((int)(new DateTimeImmutable($date))->format('N') === 7 && $day < $daysInMonth) {
            $weekNumber++;
        }
    }
    return $weeks;
}

function statusBadge($status) {
    $map = [
        'pending'     => 'warning',
        'in_progress' => 'info',
        'resolved'    => 'success',
        'paid'        => 'success',
        'unpaid'      => 'danger',
        'available'   => 'success',
        'occupied'    => 'danger',
        'maintenance' => 'warning',
        'active'      => 'success',
        'inactive'    => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"badge badge-$cls\">$label</span>";
}

function formatStayDuration($startDate, $endDate = null) {
    if (empty($startDate)) return '—';
    try {
        $start = new DateTime($startDate);
        $end = !empty($endDate) ? new DateTime($endDate) : new DateTime();
        if ($end < $start) {
            return '—';
        }
        $diff = $start->diff($end);
        $parts = [];
        if ($diff->y > 0) $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'yr' : 'yrs');
        if ($diff->m > 0) $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'mo' : 'mos');
        if ($diff->d > 0 || empty($parts)) $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'day' : 'days');
        $text = implode(', ', $parts);
        return empty($endDate) ? $text . ' (Current)' : $text;
    } catch (Exception $e) {
        return '—';
    }
}
