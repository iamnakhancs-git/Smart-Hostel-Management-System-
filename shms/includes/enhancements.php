<?php
require_once __DIR__ . '/functions.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function shmsQuery($sql, $params = []) {
    $stmt = getDBConnection()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $value) { $types .= is_int($value) ? 'i' : 's'; }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function shmsRows($sql, $params = []) {
    $stmt = shmsQuery($sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function shmsValue($sql, $params = []) {
    $rows = shmsRows($sql, $params);
    return $rows ? array_values($rows[0])[0] : 0;
}

function shmsPost($name, $default = '') {
    $value = $_POST[$name] ?? $default;
    if (!is_string($value)) { throw new InvalidArgumentException('Invalid form value.'); }
    return trim($value);
}

function shmsText($name, $max, $required = true) {
    $value = shmsPost($name);
    if (($required && $value === '') || strlen($value) > $max) {
        throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $name)) . ' is required or too long.');
    }
    return $value;
}

function shmsDate($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        throw new InvalidArgumentException('Choose a valid date.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || (int)$date->format('Y') < 1000 || (int)$date->format('Y') > 9998) {
        throw new InvalidArgumentException('Choose a valid date.');
    }
    return $date;
}

function shmsCheckPost() {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid or expired form token. Reload the page and try again.');
    }
}

function shmsReady($tables) {
    try {
        foreach ($tables as $table) {
            // Table names are internal constants supplied by the calling page.
            if (!shmsValue('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])) {
                return false;
            }
        }
        return true;
    } catch (mysqli_sql_exception $e) {
        error_log('SHMS database setup unavailable.');
        return false;
    }
}

function shmsSetupMessage() {
    echo '<div class="alert alert-warning">Database setup is required. Import the complete <strong>sql/shms_database.sql</strong> file into a fresh database.</div>';
}

function shmsPageStart($title, $role) {
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    require __DIR__ . '/sidebar_' . $role . '.php';
    echo '<div class="content-wrapper"><div class="content-header"><div class="container-fluid"><h1>' . h($title) . '</h1></div></div><section class="content"><div class="container-fluid">';
    showFlash();
}

function shmsPageEnd() {
    echo '</div></section></div>';
    require __DIR__ . '/footer.php';
}

function shmsPeriod($input) {
    $period = $input['period'] ?? 'monthly';
    if ($period === 'weekly') {
        $selected = shmsDate($input['week_date'] ?? date('Y-m-d'));
        $start = $selected->modify('-' . ((int)$selected->format('N') - 1) . ' days');
        $end = $start->modify('+7 days');
    } elseif ($period === 'monthly') {
        $month = $input['month'] ?? date('Y-m');
        if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/D', $month)) {
            throw new InvalidArgumentException('Choose a valid month.');
        }
        $start = shmsDate($month . '-01');
        $end = $start->modify('+1 month');
    } else { throw new InvalidArgumentException('Choose weekly or monthly.'); }
    return ['period' => $period, 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'),
        'last' => $end->modify('-1 day')->format('Y-m-d')];
}
