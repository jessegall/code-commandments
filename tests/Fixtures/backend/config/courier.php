<?php

// @example DeadConfigKey good

/*
 * The courier settings, holding only what something reads: the pickup cutoff is bound in the settings
 * provider, and a key whose reader is deleted is deleted with it.
 */

return [

    'pickup_cutoff_hour' => 16,

];
