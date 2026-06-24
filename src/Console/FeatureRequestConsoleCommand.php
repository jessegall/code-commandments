<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ReportService;
use JesseGall\PhpTypes\T_String;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Propose a NEW rule / enhancement as a GitHub issue. Unlike `report` (which targets
 * a specific wrong finding), a feature request has no finding to scope to — so it is
 * the ONE judging action that stays available mid-pilgrimage. The proposal text is a
 * single positional argument; for a long multi-paragraph body that would be awkward
 * to shell-quote, pass `--stdin` (read the whole proposal from STDIN) or
 * `--reason-file=<path>` instead.
 */
class FeatureRequestConsoleCommand extends Command
{
    use BootsStandalone;

    private const DEFAULT_REPO = 'jessegall/code-commandments';

    protected function configure(): void
    {
        $this
            ->setName('feature-request')
            ->setDescription('Propose a NEW rule / enhancement as a GitHub issue (no finding needed; allowed mid-pilgrimage)')
            ->addArgument('text', InputArgument::OPTIONAL, 'The whole proposal: what to build and why')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Read the proposal body from STDIN (robust for multi-paragraph text — no shell-quoting)')
            ->addOption('reason-file', null, InputOption::VALUE_REQUIRED, 'Read the proposal body from a file (alternative to the positional text / --stdin)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Short issue title; defaults to a summary of the proposal')
            ->addOption('proposed-prophet', null, InputOption::VALUE_REQUIRED, 'Proposed name for the new prophet you are suggesting')
            ->addOption('rubric', null, InputOption::VALUE_REQUIRED, 'Proposed APPLY/LEAVE rubric for the suggested rule')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'GitHub repo (owner/name) to file the issue on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = Environment::workingDirectory();
        $reason = $this->resolveReason($input);

        if ($reason === null || T_String::isBlank($reason)) {
            $output->writeln('<error>Describe the feature / new rule and why.</error> Pass it as the argument, or with --stdin / --reason-file=<path> for a long body.');

            return Command::FAILURE;
        }

        return ReportService::fileFeatureRequest(
            [
                'reason' => $reason,
                'title' => $input->getOption('title'),
                'proposed_prophet' => $input->getOption('proposed-prophet'),
                'rubric' => $input->getOption('rubric'),
            ],
            $input->getOption('repo') ?: $this->repoFromConfig($input->getOption('config')),
            fn (string $line) => $output->writeln('<info>' . $line . '</info>'),
            fn (string $line) => $output->writeln('<comment>' . $line . '</comment>'),
        ) === ReportService::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveReason(InputInterface $input): ?string
    {
        $file = $input->getOption('reason-file');

        if (is_string($file) && T_String::isNotBlank($file)) {
            return is_file($file) ? (string) file_get_contents($file) : null;
        }

        if ((bool) $input->getOption('stdin')) {
            return (string) file_get_contents('php://stdin');
        }

        $text = $input->getArgument('text');

        return is_string($text) ? $text : null;
    }

    private function repoFromConfig(?string $configPath): string
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);
        $resolved = ConfigLoader::resolve($configPath, $basePath);

        if ($resolved !== null) {
            $repo = ConfigLoader::load($resolved)['report']['repo'] ?? null;

            if (is_string($repo) && T_String::isNotEmpty($repo)) {
                return $repo;
            }
        }

        return self::DEFAULT_REPO;
    }
}
