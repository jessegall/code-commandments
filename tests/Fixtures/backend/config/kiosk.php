<?php

/*
 * Kiosk behaviour. The idle timeout is live; `legacy_pin_length` belonged to the PIN screen that
 * badge scanning replaced, and `pin_retry_grace_seconds` went with it.
 */

return [

    'idle_timeout' => 120,

    'legacy_pin_length' => 4,

    'pin_retry_grace_seconds' => 90,

];
