<?php
require_once __DIR__ . '/enhancements.php';
require_once __DIR__ . '/account_validation.php';

function shmsBackendError($message, $status = 422) {
    http_response_code($status);
    if (!headers_sent()) {
        shmsPageStart('Request could not be completed', $_SESSION['role']);
        echo '<div class="alert alert-danger" role="alert">' . h($message) . '</div>';
        echo '<button type="button" class="btn btn-secondary" onclick="history.back()">Back to form</button>';
        shmsPageEnd();
    }
    exit;
}

function shmsBackendStart($role) {
    set_exception_handler(function ($e) {
        try { getDBConnection()->rollback(); } catch (Throwable $ignored) {}
        if ($e instanceof InvalidArgumentException) { shmsBackendError($e->getMessage()); }
        if ($e instanceof mysqli_sql_exception) {
            try { getDBConnection()->rollback(); } catch (Throwable $ignored) {}
            $messages = [1062=>'That email, phone number, CNIC/ID number, room number or record already exists.',
                1451=>'This record has linked history and cannot be deleted. Deactivate it or vacate its allocation instead.',
                1452=>'The selected resident or room no longer exists. Reload the form.',
                1406=>'One of the entered values is too long. Shorten it and try again.'];
            if (isset($messages[$e->getCode()])) { shmsBackendError($messages[$e->getCode()]); }
        }
        error_log('SHMS backend: ' . $e->getMessage());
        shmsBackendError('The request failed. Check the database setup and PHP error log, then try again.',500);
    });
    $page = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    foreach (['date','alloc_date','vacate_date','join_date','due_date'] as $field) {
        foreach ([$_GET, $_POST] as $input) {
            if (isset($input[$field]) && $input[$field] !== '') { shmsDate($input[$field]); }
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { shmsBackendError('Invalid or expired form token. Reload the form and try again.',403); }
    foreach (['month'=>[1,12],'year'=>[1901,2155],'floor'=>[1,127]] as $field=>$range) {
        if (isset($_POST[$field]) && (filter_var($_POST[$field], FILTER_VALIDATE_INT) === false || (int)$_POST[$field] < $range[0] || (int)$_POST[$field] > $range[1])) {
            throw new InvalidArgumentException('Choose a valid ' . $field . '.');
        }
    }
    if (isset($_POST['email']) && !filter_var($_POST['email'],FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid email address.'); }
    foreach (['name'=>100,'email'=>100,'phone'=>20,'emergency_contact'=>20,'id_proof_type'=>50,'id_proof_number'=>50,'room_number'=>10,'subject'=>200] as $field=>$limit) {
        if (isset($_POST[$field]) && (!is_string($_POST[$field]) || strlen($_POST[$field]) > $limit)) { throw new InvalidArgumentException($field . ' is invalid or too long.'); }
    }
    if (isset($_POST['status'])) {
        $allowed = in_array($page,['rooms.php','room_add.php','room_edit.php'],true) ? ['available','occupied','maintenance']
            : ($page === 'complaints.php' ? ['pending','in_progress','resolved'] : ($page === 'bills.php' ? ['paid','unpaid'] : ['active','inactive']));
        if (!in_array($_POST['status'],$allowed,true)) { throw new InvalidArgumentException('Invalid status.'); }
    }
    if ($page === 'misc_charges.php' && in_array($_POST['action'] ?? '', ['add','update'], true)) { shmsMoney($_POST['amount'] ?? '',false); }
    if ($page === 'meal_prices.php') {
        foreach (['breakfast','lunch','snacks','dinner'] as $meal) { shmsMoney($_POST[$meal] ?? '',true); }
    }
}

function shmsMoney($value, $allowZero = true) {
    if (!is_string($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$value) || (!$allowZero && (float)$value <= 0)) {
        throw new InvalidArgumentException('Enter a valid amount with at most two decimal places.');
    }
    return (float)$value;
}

function shmsSyncRoom($roomId) {
    shmsQuery("UPDATE rooms SET status=CASE WHEN EXISTS (SELECT 1 FROM room_allocations WHERE room_id=? AND is_active=1) THEN 'occupied' ELSE 'available' END WHERE id=? AND status<>'maintenance'",[(int)$roomId,(int)$roomId])->close();
}

function shmsCheckRoomState($roomId, $status, $typeId = null) {
    $count = (int)shmsValue('SELECT COUNT(*) FROM room_allocations WHERE room_id=? AND is_active=1',[(int)$roomId]);
    if ($typeId !== null) {
        $types = shmsRows('SELECT beds FROM room_types WHERE id=?',[(int)$typeId]);
        if (!$types) { throw new InvalidArgumentException('Choose an existing room type.'); }
        if ($count > (int)$types[0]['beds']) { throw new InvalidArgumentException('The selected room type has fewer beds than current residents. Vacate allocations first.'); }
    }
    if (($count > 0 && $status !== 'occupied') || ($count === 0 && $status === 'occupied')) {
        throw new InvalidArgumentException('Room occupancy must match its allocations. Allocate or vacate residents before changing this status.');
    }
}
