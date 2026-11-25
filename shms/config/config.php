<?php
// ============================================================
// Application Configuration
// Smart Hostel Management System (SHMS)
// ============================================================

define('APP_NAME',    'Smart Hostel Management System');
define('APP_SHORT',   'SHMS');
define('BASE_URL',    '/shms');

// Session lifetime (seconds)
define('SESSION_LIFETIME', 3600);

// Date / time
define('DATE_FORMAT', 'd M Y');
define('DATETIME_FORMAT', 'd M Y H:i');

// Password hashing cost
define('HASH_COST', 10);

// Match reporting boundaries to the MySQL session's Pakistan time zone.
date_default_timezone_set('Asia/Karachi');

// Roles
define('ROLE_ADMIN',    'admin');
define('ROLE_RESIDENT', 'resident');
define('ROLE_MESS',     'mess');
