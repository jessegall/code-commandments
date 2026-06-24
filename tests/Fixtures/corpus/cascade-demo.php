<?php

/**
 * Dev tool: run the backend prophets over a dir, tag each finding with its
 * doctrine + band, apply the DoctrineCascade, and show what the doctrines surface
 * (roots) vs suppress (symptoms in the same method as a coarser root).
 *
 *   php tests/Fixtures/corpus/cascade-demo.php tests/Fixtures/corpus/<sub>/messy
 */

use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Doctrines\DoctrineCascade;
use JesseGall\CodeCommandments\Doctrines\DoctrineRegistry;
use JesseGall\CodeCommandments\Doctrines\Ranked;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$dir = $argv[1];
$files = array_values(array_filter((array) glob(rtrim($dir, '/') . '/*.php')));
$index = CodebaseIndex::build($files);

$registry = new ProphetRegistry();
$config = ConfigLoader::load($root . '/config/commandments.php');
$backend = $config['scrolls']['backend'] ?? [];
$registry->registerMany('backend', $backend['prophets'] ?? []);
$registry->setScrollConfig('backend', $backend);
$prophets = $registry->getProphets('backend');
foreach ($prophets as $prophet) {
    if ($prophet instanceof NeedsCodebaseIndex) {
        $prophet->setCodebaseIndex($index);
    }
}

$parser = (new ParserFactory)->createForNewestSupportedVersion();

$methodRange = static function (array $ast, ?int $line): array {
    if ($line === null) {
        return [0, 0];
    }
    $best = [$line, $line];
    $span = PHP_INT_MAX;
    foreach ((new NodeFinder)->findInstanceOf($ast, Node\FunctionLike::class) as $fn) {
        $s = $fn->getStartLine();
        $e = $fn->getEndLine();
        if ($s <= $line && $line <= $e && ($e - $s) < $span) {
            $best = [$s, $e];
            $span = $e - $s;
        }
    }

    return $best;
};

$ranked = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    $ast = $parser->parse($content) ?? [];
    foreach ($prophets as $prophet) {
        $class = get_class($prophet);
        $short = (new ReflectionClass($prophet))->getShortName();
        $judgment = $prophet->judge($file, $content);
        foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
            $loc = DoctrineRegistry::locate($class);
            [$ms, $me] = $methodRange($ast, $finding->line);
            $ranked[] = new Ranked(
                sprintf('%s @ %s:%s', $short, basename($file), $finding->line ?? '?'),
                $loc['doctrine'] ?? null,
                $loc['band'] ?? null,
                basename($file),
                $ms,
                $me,
            );
        }
    }
}

$survivors = array_map(static fn (Ranked $r): string => $r->finding, DoctrineCascade::apply($ranked));
$totality = array_filter($ranked, static fn (Ranked $r): bool => $r->doctrine === 'totality');

printf("%d findings → %d after cascade. Totality: %d (kept %d).\n\n", count($ranked), count($survivors), count($totality),
    count(array_filter($totality, static fn (Ranked $r): bool => in_array($r->finding, $survivors, true))));

foreach ($totality as $r) {
    $kept = in_array($r->finding, $survivors, true);
    printf("  %s  band %d  %s  (method %d-%d)\n", $kept ? 'KEPT ' : 'hush ', $r->band, $r->finding, $r->startLine, $r->endLine);
}
