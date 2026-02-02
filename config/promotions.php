<?php

return [
    'reservation_ttl' => (int) env('COUPON_RESERVATION_TTL', 900),

    'invalid_attempt_limit' => (int) env('COUPON_INVALID_ATTEMPT_LIMIT', 5),

    'invalid_attempt_window' => (int) env('COUPON_INVALID_ATTEMPT_WINDOW', 60),
];
