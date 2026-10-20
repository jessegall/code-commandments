<?php

// @righteous-file ArrayReturnBag
// @righteous-file ArrayBag

/*
 * The dispatch settings. A config file declares nothing and answers with an array because the framework reads it
 * that way: a type here would be built only to be turned back into the array the reader takes, and the closure's
 * parameter is the shape the package hands it.
 */

return [

    'cutoff_hour' => 16,

    'lanes' => [
        'default' => static function (array $context): array {
            return [
                'queue' => $context['queue'],
                'workers' => $context['workers'] * 2,
            ];
        },
    ],

];
