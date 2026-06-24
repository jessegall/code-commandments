<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Console;

use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageIndexCache;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Leave the pilgrimage early. The walk is forward-only and locks `judge` / bulk
 * `repent` while it runs, so if a prophet genuinely can't be resolved (a broken
 * auto-fix, a step that is out of scope), the agent needs a clean way out that does
 * NOT fake completion. `abandon` discards the walk — `judge`/`repent` return — but it
 * does NOT mark the pilgrimage complete, so the pre-push gate still enforces sins (you
 * have not earned the completed-walk push). Run `pilgrimage` to start a fresh walk.
 */
class AbandonConsoleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('abandon')
            ->setDescription('Leave the current pilgrimage early (judge/repent return; the push gate still enforces sins)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = getcwd() ?: '.';
        Environment::setBasePath($basePath);

        if (! PilgrimageState::isActive($basePath)) {
            $output->writeln('<comment>No pilgrimage in progress.</comment>');

            return Command::SUCCESS;
        }

        PilgrimageState::clear($basePath);
        PilgrimageIndexCache::clear($basePath);

        $output->writeln('<info>Pilgrimage abandoned.</info> `judge` and `repent` are available again — the push gate still enforces unresolved sins. Run `commandments pilgrimage` to start a fresh walk.');

        return Command::SUCCESS;
    }
}
