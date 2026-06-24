<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Native Composer integration: after a consumer's `composer update`/`install`,
 * re-run `sync --after=previous` so newly-shipped prophets are registered and the
 * scaffold / skills / .gitignore state is refreshed — automatically, on every
 * update and on fresh CI installs (where the git post-merge hook never fires).
 *
 * Self-defending: it never runs in the package's own repo, only when the consumer
 * actually has a commandments config, and a sync failure is reported, never fatal —
 * a lint tool must not break `composer update`.
 */
final class CommandmentsPlugin implements PluginInterface, EventSubscriberInterface
{
    private const PACKAGE = 'jessegall/code-commandments';

    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'onComposerEvent',
            ScriptEvents::POST_INSTALL_CMD => 'onComposerEvent',
        ];
    }

    public function onComposerEvent(Event $event): void
    {
        $composer = $event->getComposer();
        $io = $event->getIO();

        // Never act inside our own repository — only when installed as a dependency.
        if ($composer->getPackage()->getName() === self::PACKAGE) {
            return;
        }

        $root = getcwd() ?: '.';

        // Only when the consumer has actually adopted the tool.
        if (! is_file($root . '/config/commandments.php') && ! is_file($root . '/commandments.php')) {
            return;
        }

        $binDir = (string) $composer->getConfig()->get('bin-dir');
        $bin = $binDir . DIRECTORY_SEPARATOR . 'commandments';

        if (! is_file($bin)) {
            return;
        }

        $io->write('<info>code-commandments:</info> syncing prophets, scaffold and skills…');

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' sync --after=previous 2>&1';
        $output = [];
        $status = 0;
        exec($command, $output, $status);

        foreach ($output as $line) {
            $io->write('  ' . $line);
        }

        if ($status !== 0) {
            // A lint tool must never break the update — warn and move on.
            $io->writeError('<warning>code-commandments: sync exited non-zero; run `commandments sync` by hand.</warning>');
        }
    }
}
