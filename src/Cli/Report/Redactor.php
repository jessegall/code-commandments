<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;

/**
 * Scrubs secrets from a source line BEFORE it is captured into a `report` GitHub issue — the report reads a
 * consumer's real files and posts them to a (often public) tracker, so a hard-coded password, API key,
 * token, connection string, or `.env` value must never travel with it. This is genuine SECRET-TEXT scanning
 * (not code-structure parsing), so it is regex over the raw line by design: it redacts the VALUE — a
 * sensitive-named assignment's right-hand side, an `env()`/`getenv` default, a dotenv `KEY=value`, a
 * recognised token shape (AWS/GitHub/Google/Slack/Stripe/JWT/PEM), URI credentials, or a high-entropy blob
 * — while leaving the surrounding code readable so the report still shows the shape of the bug.
 */
final class Redactor
{
    /**
     * Names whose value is sensitive wherever it is assigned or keyed.
     */
    private const string SENSITIVE = '(?:passw(?:ord|d)?|pwd|secret|secrets|api[_-]?key|apikey|access[_-]?key|'
        . 'client[_-]?secret|private[_-]?key|credentials?|authorization|auth[_-]?token|bearer|token|dsn|'
        . 'app[_-]?key|encryption[_-]?key|session[_-]?secret|webhook[_-]?secret|salt|signature|passphrase)';

    /**
     * Provider-specific secret formats (AWS, GitHub, Google, Slack, Stripe, JWT, PEM, private keys).
     */
    private const array TOKENS = [
        '/A(?:KIA|SIA|GPA|IDA|ROA|IPA|NPA|NVA)[0-9A-Z]{16}/',                 // AWS access key id
        '/gh[pousr]_[A-Za-z0-9]{20,}/',                                       // GitHub token
        '/github_pat_[A-Za-z0-9_]{20,}/',                                     // GitHub fine-grained PAT
        '/AIza[0-9A-Za-z_\-]{20,}/',                                          // Google API key
        '/xox[baprs]-[0-9A-Za-z\-]{10,}/',                                    // Slack token
        '/(?:sk|pk|rk)_(?:live|test)_[0-9A-Za-z]{16,}/',                      // Stripe key
        '/eyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}/',    // JWT
        '/-----BEGIN[A-Z ]*PRIVATE KEY-----/',                               // PEM private key header
    ];

    public function line(string $line): string
    {
        // A sensitive-named key → its quoted value: `'api_key' => 'x'`, `apiKey: "x"`, `APP_KEY = 'x'`.
        $line = (string) preg_replace_callback(
            '/(["\']?' . self::SENSITIVE . '["\']?\s*(?:=>|:|=)\s*)(["\'])((?:\\\\.|(?!\2).)*)(\2)/i',
            static fn (array $m): string => $m[1] . $m[2] . self::mask($m[3]) . $m[4],
            $line,
        );

        // An `env('SENSITIVE', 'default')` / `getenv(...)` fallback that hard-codes the secret.
        $line = (string) preg_replace_callback(
            '/((?:env|getenv|config)\(\s*["\'][^"\']*' . self::SENSITIVE . '[^"\']*["\']\s*,\s*)(["\'])((?:\\\\.|(?!\2).)*)(\2)/i',
            static fn (array $m): string => $m[1] . $m[2] . self::mask($m[3]) . $m[4],
            $line,
        );

        // A dotenv / shell assignment of a sensitive key: `DB_PASSWORD=...` (unquoted, to end of line).
        $line = (string) preg_replace_callback(
            '/^(\s*(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*' . self::SENSITIVE . '[A-Za-z0-9_]*\s*=\s*)(?![\'"])(\S.*)$/i',
            static fn (array $m): string => $m[1] . self::mask($m[2]),
            $line,
        );

        // Credentials embedded in a connection URI: `scheme://user:pass@host` → keep the shape, drop the creds.
        $line = (string) preg_replace_callback(
            '#([a-z][a-z0-9+.\-]*://)([^\s:@/]+:[^\s:@/]+)@#i',
            static fn (array $m): string => $m[1] . self::mask($m[2]) . '@',
            $line,
        );

        // Recognised secret token shapes, wherever they appear.
        foreach (self::TOKENS as $pattern) {
            $line = (string) preg_replace_callback($pattern, static fn (array $m) => self::mask($m[0]), $line);
        }

        // A high-entropy blob inside a string literal — a key/token that dodged the rules above. Requires a
        // long run of base64/hex characters carrying BOTH letters and digits, so prose and identifiers stay.
        $line = (string) preg_replace_callback(
            '/(["\'])([A-Za-z0-9+\/=_\-]{32,})(\1)/',
            static fn (array $m): string => self::looksRandom($m[2]) ? $m[1] . self::mask($m[2]) . $m[3] : $m[0],
            $line,
        );

        return $line;
    }

    /**
     * A censored bar (U+2588 full blocks) the SAME length as the secret it hides — so the length leaks nothing.
     */
    private static function mask(string $secret): string
    {
        return str_repeat('█', max(1, mb_strlen($secret)));
    }

    /**
     * Does a base64/hex-ish string carry both letters and digits — the signature of a random secret?
     */
    private static function looksRandom(string $value): bool
    {
        return preg_match('/[A-Za-z]/', $value) === 1 && preg_match('/[0-9]/', $value) === 1;
    }
}
