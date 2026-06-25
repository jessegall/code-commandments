<?php

use JesseGall\CodeCommandments\Prophets\Backend;

// Standalone config for the OptionDiscipline corpus — a self-contained fake project
// the prophet runs against end-to-end (real cross-file call graph). Mirrors
// commandments.self.php in shape.
return [
    'scrolls' => [
        'backend' => [
            'path' => __DIR__ . '/src',
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [
                Backend\OptionDisciplineProphet::class => [
                    'option_class' => JesseGall\PhpTypes\Option::class,
                ],
            ],
        ],
    ],
];
