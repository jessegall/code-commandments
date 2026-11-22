<?php

namespace Symfony\Component\Console;

class Application implements \Symfony\Contracts\Service\ResetInterface
{
    private array $commands = array (
);
    private bool $wantHelps = false;
    private ?\Symfony\Component\Console\Command\Command $runningCommand = NULL;
    private ?\Symfony\Component\Console\CommandLoader\CommandLoaderInterface $commandLoader = NULL;
    private bool $catchExceptions = true;
    private bool $catchErrors = false;
    private bool $autoExit = true;
    private \Symfony\Component\Console\Input\InputDefinition $definition;
    private \Symfony\Component\Console\Helper\HelperSet $helperSet;
    private ?\Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher = NULL;
    private \Symfony\Component\Console\Terminal $terminal;
    private string $defaultCommand;
    private bool $singleCommand = false;
    private bool $initialized = false;
    private ?\Symfony\Component\Console\SignalRegistry\SignalRegistry $signalRegistry = NULL;
    private array $signalsToDispatchEvent = array (
);
    private ?int $alarmInterval = NULL;
    private string $name;
    private string $version;
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN') {}
    public function setDispatcher(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher): void {}
    public function setCommandLoader(\Symfony\Component\Console\CommandLoader\CommandLoaderInterface $commandLoader): void {}
    public function getSignalRegistry(): \Symfony\Component\Console\SignalRegistry\SignalRegistry {}
    public function setSignalsToDispatchEvent(int ...$signalsToDispatchEvent): void {}
    public function setAlarmInterval(?int $seconds): void {}
    public function getAlarmInterval(): ?int {}
    private function scheduleAlarm(): void {}
    public function run(?\Symfony\Component\Console\Input\InputInterface $input = NULL, ?\Symfony\Component\Console\Output\OutputInterface $output = NULL): int {}
    public function doRun(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int {}
    public function reset(): void {}
    public function setHelperSet(\Symfony\Component\Console\Helper\HelperSet $helperSet): void {}
    public function getHelperSet(): \Symfony\Component\Console\Helper\HelperSet {}
    public function setDefinition(\Symfony\Component\Console\Input\InputDefinition $definition): void {}
    public function getDefinition(): \Symfony\Component\Console\Input\InputDefinition {}
    public function complete(\Symfony\Component\Console\Completion\CompletionInput $input, \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions): void {}
    public function getHelp(): string {}
    public function areExceptionsCaught(): bool {}
    public function setCatchExceptions(bool $boolean): void {}
    public function setCatchErrors(bool $catchErrors = true): void {}
    public function isAutoExitEnabled(): bool {}
    public function setAutoExit(bool $boolean): void {}
    public function getName(): string {}
    public function setName(string $name): void {}
    public function getVersion(): string {}
    public function setVersion(string $version): void {}
    public function getLongVersion(): string {}
    public function register(string $name): \Symfony\Component\Console\Command\Command {}
    public function addCommands(array $commands): void {}
    public function add(\Symfony\Component\Console\Command\Command $command): ?\Symfony\Component\Console\Command\Command {}
    public function addCommand(\Symfony\Component\Console\Command\Command|callable $command): ?\Symfony\Component\Console\Command\Command {}
    public function get(string $name): \Symfony\Component\Console\Command\Command {}
    public function has(string $name): bool {}
    public function getNamespaces(): array {}
    public function findNamespace(string $namespace): string {}
    public function find(string $name): \Symfony\Component\Console\Command\Command {}
    public function all(?string $namespace = NULL): array {}
    public static function getAbbreviations(array $names): array {}
    public function renderThrowable(\Throwable $e, \Symfony\Component\Console\Output\OutputInterface $output): void {}
    protected function doRenderThrowable(\Throwable $e, \Symfony\Component\Console\Output\OutputInterface $output): void {}
    protected function configureIO(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void {}
    protected function doRunCommand(\Symfony\Component\Console\Command\Command $command, \Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int {}
    protected function getCommandName(\Symfony\Component\Console\Input\InputInterface $input): ?string {}
    protected function getDefaultInputDefinition(): \Symfony\Component\Console\Input\InputDefinition {}
    protected function getDefaultCommands(): array {}
    protected function getDefaultHelperSet(): \Symfony\Component\Console\Helper\HelperSet {}
    private function getAbbreviationSuggestions(array $abbrevs): string {}
    public function extractNamespace(string $name, ?int $limit = NULL): string {}
    private function findAlternatives(string $name, iterable $collection): array {}
    public function setDefaultCommand(string $commandName, bool $isSingleCommand = false): static {}
    public function isSingleCommand(): bool {}
    private function splitStringByWidth(string $string, int $width): array {}
    private function extractAllNamespaces(string $name): array {}
    private function init(): void {}
}
