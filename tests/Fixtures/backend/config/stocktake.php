<?php

/**
 * This file is part of the Shop fixture, read by every rule and never run.
 */

/**
 * Warehouse stocktake. The cycle length is read by the stocktake planner; `reconciliation_endpoint`
 * pointed at the reconciliation service that was decommissioned, and nothing has read it since.
 */
return [

    'cycle_days' => 14,

    'reconciliation_endpoint' => 'https://reconcile.internal/api/v2/submit',

];
