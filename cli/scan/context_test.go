package scan

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
)

// sources is a file of each bridge's own language that reads whatever its neighbour declares.
var sources = map[source.Language][2]string{
	source.PHP:        {"<?php\n\nfinal class Order {}\n", "<?php\n\nfinal class Cart { public function __construct(public Order $order) {} }\n"},
	source.TypeScript: {"export const price = 3\n", "import { price } from './one'\n\nexport const total = price * 2\n"},
	source.Python:     {"def price() -> int:\n    return 3\n", "from one import price\n\n\ndef total() -> int:\n    return price() * 2\n"},
	source.Go:         {"package shop\n\nfunc price() int { return 3 }\n", "package shop\n\nfunc total() int { return price() * 2 }\n"},
	source.CSharp:     {"namespace Shop;\n\npublic sealed class Order;\n", "namespace Shop;\n\npublic sealed class Cart(Order order);\n"},
}

// Every bridge is asked the same: read two files, write one. Whatever a bridge marks, the other file reaches the
// engine only as context, by the one rule the contract states.
func TestEveryBridgeWritesOnlyTheFilesItWasAskedToAsJudged(t *testing.T) {
	for _, held := range Bridges {
		var language source.Language
		var texts [2]string
		known := false
		for _, each := range held.Languages {
			if texts, known = sources[each]; known {
				language = each

				break
			}
		}
		if !known {
			t.Errorf("no sources to ask the %s bridge with", held.Label)

			continue
		}
		t.Run(held.Label, func(t *testing.T) {
			project := t.TempDir()
			written(t, project, "go.mod", "module shop\n\ngo 1.22\n")
			extension := "." + contract.ExtensionsOf(language.Written())[0]
			one, two := filepath.Join(project, "one"+extension), filepath.Join(project, "two"+extension)
			written(t, project, "one"+extension, texts[0])
			written(t, project, "two"+extension, texts[1])

			stream, err := read(held, []string{project}, []string{one, two}, []string{two}, func() {})
			if err != nil {
				t.Skipf("the %s bridge could not be reached here: %v", held.Label, err)
			}
			for _, file := range stream.Files {
				if !file.Context && filepath.Base(file.Path) != "two"+extension {
					t.Errorf("%s was written as judged, though only %s was asked", file.Path, two)
				}
			}
		})
	}
}
