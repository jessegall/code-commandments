<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commandments;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Severity;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\RootCauseMap;
use JesseGall\CodeCommandments\Support\Skills\SkillRegistry;
use JesseGall\CodeCommandments\Support\TextHelper;

/**
 * Base class for all commandments (prophets).
 * Provides common functionality for judging files.
 */
abstract class BaseCommandment implements Commandment
{
    /**
     * Configuration options for this commandment.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * An explicit, user-set severity override (fluent or config). Null means "no
     * override" — the prophet's findings keep the severity it emits them at.
     */
    protected ?Severity $severityOverride = null;

    /**
     * Fluent entry point — `Prophet::make()->severity(Severity::Sin)->...`. The
     * prophet is its own config builder, so a consumer's `commandments.php` reads
     * as a list of configured prophet instances with full IntelliSense for every
     * setting the prophet supports.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set the stake this prophet's findings carry (Sin blocks, Admonition advises,
     * Off silences). The user's call — overrides whatever the prophet emits.
     */
    public function severity(Severity $severity): static
    {
        $this->severityOverride = $severity;

        return $this;
    }

    /**
     * Silence this prophet entirely — it stays listed and discoverable, but never
     * reports. Sugar for `->severity(Severity::Off)`.
     */
    public function disabled(): static
    {
        return $this->severity(Severity::Off);
    }

    /**
     * Toggle the prophet on/off; `->enabled(false)` is `->disabled()`.
     */
    public function enabled(bool $enabled = true): static
    {
        return $enabled ? $this : $this->disabled();
    }

    /**
     * Store a single setting fluently. Prophet subclasses expose TYPED wrappers
     * over this (e.g. `->minCallers(10)`) so each prophet's own settings are
     * IntelliSense-discoverable; this is the shared sink they write to.
     */
    protected function setting(string $key, mixed $value): static
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * The resolved severity override — the explicit fluent value, else a legacy
     * `['severity' => 'sin'|'warning'|'off']` config entry. Null when neither is
     * set (the prophet's emitted severity stands; a doctrine-level default is
     * layered in by the registry before this is read).
     */
    public function severityOverride(): ?Severity
    {
        if ($this->severityOverride !== null) {
            return $this->severityOverride;
        }

        $configured = $this->config('severity');

        if ($configured instanceof Severity) {
            return $configured;
        }

        return is_string($configured) ? Severity::fromName($configured) : null;
    }

    public function isDisabled(): bool
    {
        return $this->severityOverride() === Severity::Off;
    }

    /**
     * Re-stamp a prophet's judgment with the user's chosen severity: Off drops
     * every finding, Sin promotes admonitions to sins, Admonition demotes sins to
     * admonitions. With no override the judgment passes through untouched, so a
     * prophet's natural emission is preserved unless the user said otherwise.
     */
    public function applyConfiguredSeverity(Judgment $judgment): Judgment
    {
        $override = $this->severityOverride();

        if ($override === null || $judgment->skipped) {
            return $judgment;
        }

        return match ($override) {
            Severity::Off => Judgment::righteous(),
            Severity::Sin => Judgment::fallen([
                ...$judgment->sins,
                ...array_map($this->asSin(...), $judgment->warnings),
            ]),
            Severity::Admonition => Judgment::withWarnings([
                ...$judgment->warnings,
                ...array_map($this->asWarning(...), $judgment->sins),
            ]),
        };
    }

    private function asSin(Warning $warning): Sin
    {
        return new Sin($warning->message, $warning->line, null, $warning->snippet, null, $warning->symbol, $warning->autoFixable);
    }

    private function asWarning(Sin $sin): Warning
    {
        // A demoted sin keeps its guidance inline (admonitions carry no separate
        // suggestion field), so the advice isn't lost.
        $message = $sin->suggestion !== null && $sin->suggestion !== ''
            ? rtrim($sin->message, '.') . '. ' . $sin->suggestion
            : $sin->message;

        return new Warning($message, $sin->line, $sin->snippet, $sin->symbol, $sin->autoFixable);
    }

    /**
     * Set configuration options.
     *
     * @param array<string, mixed> $config
     */
    public function configure(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Get a configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * By default, commandments do not require manual confession.
     */
    public function requiresConfession(): bool
    {
        return false;
    }

    /**
     * By default a commandment is imperative and carries no advisory rubric.
     * Warning-emitting prophets override this to declare apply/leave/unsure.
     */
    public function advisory(): ?Advisory
    {
        return null;
    }

    /**
     * The ordering tier. Honours a `tier` config override (by name), then
     * falls back to the prophet's declared default.
     */
    public function tier(): Tier
    {
        $configured = $this->config('tier');

        if (is_string($configured)) {
            return Tier::tryFromName($configured) ?? $this->defaultTier();
        }

        return $this->defaultTier();
    }

    /**
     * The prophet's intrinsic tier. Override in prophets that are more (or
     * less) structural than the default convention tier.
     */
    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    /**
     * Prophet classes whose findings this commandment supersedes. Derived from
     * {@see RootCauseMap} (a cause defers its symptoms) so the two directions
     * stay in lock-step; override only for relationships outside the map.
     *
     * @return list<class-string>
     */
    public function supersedes(): array
    {
        return RootCauseMap::symptomsOf(static::class);
    }

    /**
     * Prophet classes that are the likely root cause of this commandment's
     * findings. Derived from {@see RootCauseMap} (the symptom-side inverse of
     * `supersedes()`); override only for relationships outside the map.
     *
     * @return list<class-string>
     */
    public function rootCauses(): array
    {
        return RootCauseMap::causesOf(static::class);
    }

    /**
     * The slug of the Claude Code skill that teaches this prophet's subject —
     * the on-demand "how to do it right" playbook a finding points at ("deep
     * dive"). Derived from {@see SkillRegistry} (the inverse of each skill's
     * prophet family) so the catalogue and the pointer never drift; returns
     * null when no skill backs this prophet.
     */
    public function skill(): ?string
    {
        return SkillRegistry::slugForProphet(static::class);
    }

    /**
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        return [];
    }

    /**
     * Get paths that should be excluded from this commandment.
     *
     * @return array<string>
     */
    public function getExcludedPaths(): array
    {
        return $this->config('exclude', []);
    }

    /**
     * Check if this commandment is supported in the current project.
     *
     * Override this method to check for package dependencies.
     */
    public function supported(): bool
    {
        return true;
    }

    /**
     * Create a righteous judgment.
     */
    protected function righteous(): Judgment
    {
        return Judgment::righteous();
    }

    /**
     * Create a fallen judgment with sins.
     *
     * @param array<Sin> $sins
     */
    protected function fallen(array $sins): Judgment
    {
        return Judgment::fallen($sins);
    }

    /**
     * Create a sin at a specific line.
     */
    protected function sinAt(int $line, string $message, ?string $snippet = null, ?string $suggestion = null, ?string $symbol = null, ?bool $autoFixable = null): Sin
    {
        return Sin::at($line, $message, $snippet, $suggestion, $symbol, $autoFixable);
    }

    /**
     * Create a general sin without line number.
     */
    protected function sin(string $message, ?string $suggestion = null): Sin
    {
        return Sin::general($message, $suggestion);
    }

    /**
     * Create a warning at a specific line.
     */
    protected function warningAt(int $line, string $message, ?string $snippet = null, ?string $symbol = null, ?bool $autoFixable = null): Warning
    {
        return Warning::at($line, $message, $snippet, $symbol, $autoFixable);
    }

    /**
     * Create a general warning.
     */
    protected function warning(string $message): Warning
    {
        return Warning::general($message);
    }

    /**
     * Skip this file with a reason.
     */
    protected function skip(string $reason): Judgment
    {
        return Judgment::skipped($reason);
    }

    /**
     * Check if file should be skipped based on extension.
     */
    protected function shouldSkipExtension(string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return !in_array($extension, $this->applicableExtensions(), true);
    }

    /**
     * Get the line number for a position in content.
     */
    protected function getLineNumber(string $content, int $position): int
    {
        return TextHelper::getLineNumber($content, $position);
    }

    /**
     * Get a snippet of code around a position.
     */
    protected function getSnippet(string $content, int $position, int $length = 60): string
    {
        return TextHelper::getSnippet($content, $position, $length);
    }

    /**
     * Find all matches of a pattern in content.
     *
     * @return array<array{0: string, 1: int}> Array of [match, position] pairs
     */
    protected function findMatches(string $pattern, string $content): array
    {
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches[0] ?? [];
    }
}
