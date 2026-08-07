<?php

namespace Shop\Templating;

use JesseGall\CodeCommandments\Sins\Backend\AssembledTemplate;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Drafts a schema migration. The fixed frame is spelled as fragments and the
 * computed columns are spread into the middle of it, so neither the frame nor
 * the hole in it is visible.
 */
final class MigrationDraft
{
    /**
     * @param  list<string>  $columns
     */
    #[Sinful(AssembledTemplate::class)]
    public function draft(string $table, array $columns): string
    {
        $body = [
            'return new class extends Migration',
            '{',
            '    public function up(): void',
            '    {',
            "        Schema::create('{$table}', function (Blueprint \$table): void {",
            ...$columns,
            '        });',
            '    }',
            '};',
        ];

        return implode("\n", $body);
    }
}
