<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Reporting\IssueReporter;
use JesseGall\CodeCommandments\Support\Reporting\ReportLedger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

class ReportConsoleCommand extends Command
{
    private const DEFAULT_REPO = 'jessegall/code-commandments';

    protected function configure(): void
    {
        $this
            ->setName('report')
            ->setDescription('Report a prophet false-positive or wrong rule as a GitHub issue')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'The prophet that misbehaved (name or class)')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'What is wrong (false positive / wrong rule / unclear)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'File where it was flagged')
            ->addOption('line', null, InputOption::VALUE_REQUIRED, 'Line number')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'GitHub repo (owner/name) to file the issue on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prophet = $input->getOption('prophet');
        $reason = $input->getOption('reason');

        if (! is_string($prophet) || T_String::isBlank($prophet) || ! is_string($reason) || T_String::isBlank($reason)) {
            $output->writeln('<error>--prophet and --reason are required.</error>');

            return Command::FAILURE;
        }

        $file = $input->getOption('file');
        $line = $input->getOption('line') !== null ? (int) $input->getOption('line') : null;
        $repo = $input->getOption('repo') ?: $this->repoFromConfig($input->getOption('config'));

        $reporter = new IssueReporter($repo);
        $issue = $reporter->build($prophet, $file, $line, $reason, $this->snippet($file, $line));
        $result = $reporter->send($issue);

        if ($result['ok']) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            if (($result['number'] ?? null) !== null && ($result['url'] ?? null) !== null) {
                (new ReportLedger(getcwd() ?: '.'))->record(
                    $result['number'],
                    $result['url'],
                    $prophet,
                    $repo,
                    $reason,
                    date('c'),
                );
            }

            return Command::SUCCESS;
        }

        $output->writeln('<comment>' . $result['message'] . '</comment>');

        return Command::FAILURE;
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

    private function snippet(?string $file, ?int $line): ?string
    {
        if ($file === null || $line === null || ! is_file($file)) {
            return null;
        }

        $lines = explode(T_String::NEWLINE, (string) file_get_contents($file));

        return $lines[$line - 1] ?? null;
    }
}
