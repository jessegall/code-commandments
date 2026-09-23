<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use Composer\Autoload\ClassLoader;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Support\FileTree;

/**
 * `commandments journal-serve` — {@see JournalHook} kept running. The journal starts it as one of the
 * plugin's services and sends each moment over a Unix socket instead of starting PHP for every tool
 * call; the answer is the one the command would print. It exits when the code it runs changes — a
 * package update, the project's config, a rule of its own — and the journal starts it again. It is
 * the plugin's one process that loads the project's config, and the plugin never runs `sync`, so it
 * strips a retired `planExecution()` call first ({@see ConfigScribe::removePlanExecution}).
 */
final class JournalServe implements Command
{
    public const SOCKET = 'JOURNAL_PLUGIN_SOCKET';

    public function __construct(
        private readonly JournalHook $hook = new JournalHook(),
        private readonly HookIO $io = new HookIO(),
    ) {}

    public function names(): array
    {
        return ['journal-serve'];
    }

    public function help(): Help
    {
        return Help::of("Answer the agent journal's hooks from one running process, over the socket the journal names in \$JOURNAL_PLUGIN_SOCKET.")
            ->form('journal-serve', 'serve until the code it runs changes (started by the journal as a plugin service)')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $path = getenv(self::SOCKET) ?: null;

        if ($path === null) {
            return HelpScreen::usage($this, 'The journal names the socket in $' . self::SOCKET . '; there is none to listen on.');
        }

        ConfigScribe::inProject($this->io->projectRoot())->removePlanExecution();

        if (file_exists($path)) {
            unlink($path);
        }

        $server = stream_socket_server("unix://{$path}", $code, $message);

        if ($server === false) {
            fwrite(STDERR, "Cannot listen on {$path}: {$message}\n");

            return 1;
        }

        chmod($path, 0600);
        $home = (string) getcwd();
        $root = $this->io->projectRoot();
        $stamp = self::stamp($root);

        while (self::stamp($root) === $stamp) {
            $connection = stream_socket_accept($server, -1);

            if ($connection !== false) {
                $this->answer($connection, $home);
            }
        }

        return 0;
    }

    /**
     * Read one moment off $connection, answer it and hang up — from $home, whatever the last moment left as
     * the working directory — then let the {@see Advisor} ask the advising hooks after the call was answered.
     *
     * @param  resource  $connection
     */
    private function answer($connection, string $home): void
    {
        chdir($home);
        $given = (array) json_decode((string) fgets($connection), true);
        $advisor = new Advisor($this->hook);

        fwrite($connection, $advisor->answerNow($given)->toJson() . "\n");
        fclose($connection);

        $advisor->adviseLater($given, $home);
    }

    /**
     * When the code this process runs last changed: the installed packages, the project's config, and
     * the rules the project writes of its own — what a running process cannot load again.
     */
    private static function stamp(string $root): int
    {
        $installed = dirname((string) new \ReflectionClass(ClassLoader::class)->getFileName()) . '/installed.php';
        $custom = "{$root}/.commandments/custom";
        $rules = is_dir($custom) ? iterator_to_array(FileTree::filesIn($custom, 'php'), false) : [];
        $watched = array_filter([$installed, "{$root}/.commandments/config.php", ...$rules], is_file(...));

        return max([0, ...array_map(static fn (string $file): int => (int) filemtime($file), $watched)]);
    }
}
