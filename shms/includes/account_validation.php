<?php

function shmsIdentityRules() {
    static $rules = null;
    if ($rules === null) { $rules = require __DIR__ . '/../config/id_rules.php'; }
    return $rules;
}

function shmsValidateIdentity($type, $number) {
    if (!is_string($type) || !is_string($number)) { throw new InvalidArgumentException('Invalid identity document.'); }
    $type = trim($type); $number = trim($number);
    if ($type === '' && $number === '') { return ''; }
    $rules = shmsIdentityRules();
    if (!isset($rules[$type])) { throw new InvalidArgumentException('Select a valid ID proof type.'); }
    $rule = $rules[$type];
    if (strlen($number) > $rule['maxLength'] || !preg_match('~^' . $rule['pattern'] . '$~D', $number)) {
        throw new InvalidArgumentException($type . ': ' . $rule['hint']);
    }
    return $type === 'CNIC' ? str_replace('-', '', $number) : strtoupper($number);
}

function shmsNormalizePhone($phone) {
    if (!is_string($phone)) { throw new InvalidArgumentException('Invalid phone number.'); }
    $phone = trim($phone);
    if ($phone === '') { return ''; }
    $phone = preg_replace('/[\s()\-]/', '', $phone);
    if (!preg_match('/^\+?[0-9]{7,15}$/D', $phone)) {
        throw new InvalidArgumentException('Phone number must contain 7 to 15 digits and may start with +.');
    }
    return $phone;
}

function shmsResidentDuplicateErrors($email, $phone, $idNumber, $excludeId = 0) {
    $errors = [];
    $excludeId = (int)$excludeId;
    if (shmsValue('SELECT COUNT(*) FROM residents WHERE LOWER(email)=LOWER(?) AND id<>?', [$email, $excludeId])) {
        $errors[] = 'Email already exists.';
    }
    if ($phone !== '' && $phone !== null && shmsValue('SELECT COUNT(*) FROM residents WHERE phone=? AND id<>?', [$phone, $excludeId])) {
        $errors[] = 'Phone number already exists.';
    }
    if ($idNumber !== '' && $idNumber !== null && shmsValue('SELECT COUNT(*) FROM residents WHERE UPPER(id_proof_number)=UPPER(?) AND id<>?', [$idNumber, $excludeId])) {
        $errors[] = 'CNIC/ID proof number already exists.';
    }
    return $errors;
}

function shmsPasswordChangeErrors($hash, $input) {
    $new = $input['password'] ?? '';
    $confirm = $input['password_confirm'] ?? '';
    $old = $input['old_password'] ?? '';
    if (!is_string($new) || !is_string($confirm) || !is_string($old)) { return ['Invalid password input.']; }
    if ($new === '' && $confirm === '') { return []; } // Ordinary profile edits need no password.
    $errors = [];
    if ($old === '' || !password_verify($old, $hash)) { $errors[] = 'Current password is incorrect or missing.'; }
    if ($new === '' || $new !== $confirm) { $errors[] = 'New password and confirmation must match.'; }
    // Bcrypt accepts up to 72 bytes; reject truncation instead of changing intent.
    if (strlen($new) < 8 || strlen($new) > 72) { $errors[] = 'New password must be between 8 and 72 bytes (8-72 characters for English text).'; }
    return $errors;
}
