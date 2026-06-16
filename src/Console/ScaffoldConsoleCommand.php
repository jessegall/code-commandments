<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScaffoldConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('scaffold')
            ->setDescription('Generate recommended support classes (Option, FromArrayOnly, …) into your namespace')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing support classes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd();
        Environment::setBasePath($basePath);

        $resolved = ConfigLoader::resolve($input->getOption('config'), $basePath);

        if ($resolved === null) {
            $output->writeln('<error>No configuration file found.</error>');

            return Command::FAILURE;
        }

        $config = ConfigLoader::load($resolved);
        $scaffold = $config['scaffold'] ?? [];

        $namespace = $scaffold['namespace'] ?? 'App\\Support';
        $path = $scaffold['path'] ?? ($basePath . '/app/Support');
        $except = $scaffold['except'] ?? [];

        $results = ScaffoldGenerator::packaged()
            ->generate($namespace, $path, (bool) $input->getOption('force'), $except);

        $created = ScaffoldReporter::report($results, fn (string $line) => $output->writeln($line));

        $output->writeln($created > 0
            ? "<info>Generated {$created} support class(es) into {$namespace}.</info>"
            : 'All support classes already present — nothing to generate.');

        return Command::SUCCESS;
    }
}
