package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var dataCollectionType = scribeCase{spatie.DataCollectionTypeDetector{}, DataCollectionTypeScribe{}}

// The prelude PHP's DataCollectionTypeDetectorTest opens each case with, joined to it without a break as PHP joins it.
const dataCollectionPrelude = `<?php
namespace App;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
class NodeData extends Data { public function __construct(public string $id) {} }`

func TestDataCollectionTypeRetypesToArrayKeepingAnExistingAttribute(t *testing.T) {
	fixed := dataCollectionType.fix(t, dataCollectionPrelude+`class Page extends Data {
    public function __construct(
        #[DataCollectionOf(NodeData::class)]
        public readonly DataCollection $nodes,
    ) {}
}`)

	if !strings.Contains(fixed, "public readonly array $nodes") || strings.Contains(fixed, "DataCollection $nodes") || !strings.Contains(fixed, "#[DataCollectionOf(NodeData::class)]") {
		t.Fatalf("got\n%s", fixed)
	}
}

func TestDataCollectionTypeRetypesADataCollectionInAUnion(t *testing.T) {
	fixed := dataCollectionType.fix(t, dataCollectionPrelude+`class Page extends Data {
    public function __construct(
        #[DataCollectionOf(NodeData::class)]
        public readonly DataCollection|null $nodes,
    ) {}
}`)

	if strings.Contains(fixed, "DataCollection|null $nodes") || !strings.Contains(fixed, "#[DataCollectionOf(NodeData::class)]") {
		t.Fatalf("got\n%s", fixed)
	}
}

func TestDataCollectionTypeLeavesADocblockOnlyPropertyForAHandFix(t *testing.T) {
	source := dataCollectionPrelude + `class Page extends Data {
    public function __construct(
        /** @var DataCollection<int, NodeData> */
        public readonly DataCollection $nodes,
    ) {}
}`

	if dataCollectionType.rewrote(t, source) {
		t.Fatal("a docblock-only element is left for a hand-fix, never scraped")
	}
}

func TestDataCollectionTypeRetypesTheMatchingParamDocblockAndDropsTheDeadImport(t *testing.T) {
	source := dataCollectionPrelude + `class Page extends Data {
    /**
     * @param  DataCollection<int, NodeData>  $nodes
     * @param  array<string>  $icons
     */
    public function __construct(
        #[DataCollectionOf(NodeData::class)]
        public readonly DataCollection $nodes,
        public readonly DataCollection $rows,
        public readonly array $icons = [],
    ) {}
}`

	stillNamed := dataCollectionType.fix(t, source)
	if !strings.Contains(stillNamed, `use Spatie\LaravelData\DataCollection;`) || !strings.Contains(stillNamed, "@param  array<int, NodeData>  $nodes") {
		t.Fatalf("got\n%s", stillNamed)
	}

	fixed := dataCollectionType.fix(t, strings.Replace(source, "        public readonly DataCollection $rows,\n", "", -1))
	for _, kept := range []string{"@param  array<int, NodeData>  $nodes", "@param  array<string>  $icons", `use Spatie\LaravelData\Data;`} {
		if !strings.Contains(fixed, kept) {
			t.Fatalf("lost %q:\n%s", kept, fixed)
		}
	}
	for _, gone := range []string{"DataCollection<", `use Spatie\LaravelData\DataCollection;`} {
		if strings.Contains(fixed, gone) {
			t.Fatalf("kept %q:\n%s", gone, fixed)
		}
	}
}

func TestDataCollectionTypeKeepsAnImportANestedDocblockTypeStillSpells(t *testing.T) {
	fixed := dataCollectionType.fix(t, dataCollectionPrelude+`class Page extends Data {
    /**
     * @param  DataCollection<int, NodeData>  $nodes
     * @param  array<string, DataCollection<string, NodeData>>  $grouped
     */
    public function __construct(
        #[DataCollectionOf(NodeData::class)]
        public readonly DataCollection $nodes,
        public readonly array $grouped = [],
    ) {}
}`)

	for _, kept := range []string{"@param  array<int, NodeData>  $nodes", "@param  array<string, DataCollection<string, NodeData>>  $grouped", `use Spatie\LaravelData\DataCollection;`} {
		if !strings.Contains(fixed, kept) {
			t.Fatalf("lost %q:\n%s", kept, fixed)
		}
	}
}
