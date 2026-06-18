<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JesseGall\PhpTypes\T_String;

class AbsolveConsoleCommand extends Command
{
    use BootsStandalone;

    protected function configure(): void
    {
        $this
            ->setName('absolve')
            ->setDescription('Absolve a single finding by fingerprint, with a required reason')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'The finding fingerprint shown by judge --next')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Why the rule does not apply here (required)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Baseline the queue: absolve every current advisory finding at once (sins still block)')
            ->addOption('warnings', null, InputOption::VALUE_NONE, 'Batch-absolve every WARNING in scope under one --reason; hard-refuses if any sin is in scope (absolves nothing)')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Limit --warnings to changed files: "git" (vs tracked state) or "staged" (the index)')
            ->addOption('prophet', null, InputOption::VALUE_REQUIRED, 'Limit --warnings to one prophet (partial name match), e.g. --prophet=DuplicateCode — one scan, not one-per-finding')
            ->addOption('until-push', null, InputOption::VALUE_NONE, 'Make the absolution STICKY: it survives the post-commit reset and stays until git push (warnings only)')
            ->addOption('clear-until-push', null, InputOption::VALUE_NONE, 'Drop every push-scoped (until-push) absolution; used by the pre-push hook')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Remove every ordinary absolution (post-commit reset so nothing stays hidden); report-linked absolutions persist until their issue is answered');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$registry, $manager, $tracker] = $this->bootEnvironment($input->getOption('config'));

        if ((bool) $input->getOption('clear-until-push')) {
            $cleared = $tracker->clearUntilPushAbsolutions();
            $output->writeln("<info>Cleared {$cleared} push-scoped absolution(s).</info>");

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('clear')) {
            $cleared = $tracker->clearFindingAbsolutions();
            $output->writeln("<info>Cleared {$cleared} absolution(s). Every finding will be re-evaluated from scratch.</info>");

            return Command::SUCCESS;
        }

        $untilPush = (bool) $input->getOption('until-push');

        if ((bool) $input->getOption('warnings')) {
            $scopeFiles = $this->resolveScope($input->getOption('scope'), $output);

            if ($scopeFiles === false) {
                return Command::FAILURE;
            }

            $prophet = $input->getOption('prophet');
            $result = (new Absolver($manager, $registry, $tracker))
                ->absolveWarnings($input->getOption('reason'), $scopeFiles, $untilPush, is_string($prophet) ? $prophet : null);

            $tag = $result['status'] === Absolver::STATUS_OK ? 'info' : 'error';
            $output->writeln("<{$tag}>" . $result['message'] . "</{$tag}>");

            return $result['status'] === Absolver::STATUS_OK ? Command::SUCCESS : Command::FAILURE;
        }

        if ((bool) $input->getOption('all')) {
            $result = (new Absolver($manager, $registry, $tracker))->absolveAll($input->getOption('reason'));

            $output->writeln("<info>Baselined the queue: absolved {$result['absolved']} advisory finding(s).</info>");

            if ($result['blocking_sins'] > 0) {
                $output->writeln("<comment>{$result['blocking_sins']} sin(s) cannot be absolved and still block — fix them with: commandments judge --next</comment>");
            }

            return Command::SUCCESS;
        }

        $fingerprint = $input->getOption('fingerprint');

        if (! is_string($fingerprint) || T_String::isBlank($fingerprint)) {
            $output->writeln('<error>--fingerprint is required (copy it from judge --next).</error>');

            return Command::FAILURE;
        }

        $result = (new Absolver($manager, $registry, $tracker))
            ->absolve(trim($fingerprint), $input->getOption('reason'), $untilPush);

        if ($result['status'] === Absolver::STATUS_OK) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>' . $result['message'] . '</error>');

        return Command::FAILURE;
    }

    /**
     * Resolve --scope to a list of absolute file paths, or null for the whole
     * queue. Returns false on an invalid scope value.
     *
     * @return list<string>|null|false
     */
    private function resolveScope(mixed $scope, OutputInterface $output): array|null|false
    {
        if ($scope === null) {
            return null;
        }

        $detector = GitFileDetector::for(getcwd());

        return match ($scope) {
            'git' => $detector->getChangedFiles(),
            'staged' => $detector->getStagedFiles(),
            default => $this->invalidScope($output),
        };
    }

    private function invalidScope(OutputInterface $output): false
    {
        $output->writeln('<error>--scope must be "git" or "staged".</error>');

        return false;
    }
}
