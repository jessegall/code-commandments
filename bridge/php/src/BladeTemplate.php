<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Parser;
use Throwable;

/**
 * A Blade view read as the PHP Blade compiles it to: each `{{ }}` and `{!! !!}` echo, each `@php` block, each directive's arguments and each component's `:attribute` becomes PHP
 * on the line it is written, and everything else stays the view's own text, so the calls a view makes are read like any
 * other. An echo PHP cannot parse is left as text.
 */
final class BladeTemplate
{
    public static function isOne(string $path): bool
    {
        return str_ends_with(strtolower($path), '.blade.php');
    }

    public static function asPhp(string $code, Parser $parser): string
    {
        $echoed = (string) preg_replace_callback('/(?<!@)(\{\{(?!--)|\{!!)(.+?)(\}\}|!!\})/s', static fn (array $echo): string => self::parses($echo[2], $parser) ? '<?php echo ('.$echo[2].'); ?>' : $echo[0], $code);

        $blocks = (string) preg_replace_callback('/(?<!@)@php\b(.*?)@endphp/s', static fn (array $block): string => self::parses($block[1].';', $parser, false) ? '<?php '.$block[1].'; ?>' : $block[0], $echoed);
        $directives = (string) preg_replace_callback('/(?<![@\w])@(?!php\b)\w+\s*(?<arguments>\((?:[^()]++|(?&arguments))*\))/', static fn (array $directive): string => self::statement(substr($directive['arguments'], 1, -1), $parser) ?? $directive[0], $blocks);

        return (string) preg_replace_callback('/(<x[-:][^>]*?\s):([\w.-]+)="([^"]*)"/', static fn (array $attribute): string => self::parses($attribute[3], $parser) ? $attribute[1].$attribute[2].'="<?php echo ('.$attribute[3].'); ?>"' : $attribute[0], $directives);
    }

    private static function statement(string $arguments, Parser $parser): ?string
    {
        return match (true) {
            self::parses($arguments, $parser) => '<?php echo ('.$arguments.'); ?>',
            self::parses('foreach ('.$arguments.') {}', $parser, false) => '<?php foreach ('.$arguments.') {} ?>',
            default => null,
        };
    }

    private static function parses(string $written, Parser $parser, bool $expression = true): bool
    {
        try {
            $parser->parse('<?php '.($expression ? 'echo ('.$written.');' : $written));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
