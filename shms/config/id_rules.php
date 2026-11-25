<?php
// The hostel accepts CNIC and Passport only.
return [
    'CNIC' => ['maxLength'=>15, 'pattern'=>'(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])',
        'hint'=>'13 digits, for example 12345-1234567-1. Dashes are optional.', 'inputmode'=>'numeric'],
    'Passport' => ['maxLength'=>9, 'pattern'=>'[A-Za-z0-9]{9}',
        'hint'=>'9 letters/numbers for a Pakistani passport.', 'inputmode'=>'text'],
];
