<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * A minimal carriage-return progress bar for the `judge` command, drawn on STDERR
 * so it never mixes into the findings or the checklist on STDOUT. It is fully
 * silent unless STDERR is an interactive terminal — so piped runs, hooks, CI, and
 * the test suite see nothing. The caller drives it: {@see status} for an opaque
 * phase (parsing), then {@see start}/{@see advance}/{@see finish} for the
 * determinate per-detector phase.
 */
final class ProgressBar
{
    private const int WIDTH = 24;

    private int $total = 1;

    private int $current = 0;

    private bool $active = false;

    /** @var resource */
    private $stream;

    /**
     * @param  resource|null  $stream
     */
    public function __construct($stream = null)
    {
        $this->stream = $stream ?? STDERR;
    }

    /**
     * Show an opaque status line (no bar) for a phase whose progress can't be
     * counted, e.g. parsing the tree. Overwritten by the next render.
     */
    public function status(string $message): void
    {
        if (! $this->enabled()) {
            return;
        }

        fwrite($this->stream, "\r\033[2K\033[2m{$message}\033[0m");
    }

    /**
     * Begin a determinate bar of $total steps.
     */
    public function start(int $total): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->total = max(1, $total);
        $this->current = 0;
        $this->active = true;
        $this->render('');
    }

    /**
     * Advance one step, labelling it with what's now running.
     */
    public function advance(string $label = ''): void
    {
        if (! $this->active) {
            return;
        }

        $this->current = min($this->total, $this->current + 1);
        $this->render($label);
    }

    /**
     * Clear the bar line. Safe to call when inactive.
     */
    public function finish(): void
    {
        if (! $this->active) {
            return;
        }

        fwrite($this->stream, "\r\033[2K");
        $this->active = false;
    }

    private function render(string $label): void
    {
        $filled = (int) round(self::WIDTH * $this->current / $this->total);
        $bar = str_repeat('█', $filled) . str_repeat('░', self::WIDTH - $filled);

        fwrite($this->stream, sprintf(
            "\r\033[2K\033[2mjudging\033[0m [%s] %d/%d \033[2m%s\033[0m",
            $bar,
            $this->current,
            $this->total,
            $label,
        ));
    }

    private function enabled(): bool
    {
        return function_exists('stream_isatty') && @stream_isatty($this->stream);
    }
}
