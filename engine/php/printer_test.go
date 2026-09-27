package php_test

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// Every expression php-parser's standard printer writes, as the bridge carries it, against the Go port on the same
// parsed items.
func TestConstantExpressionsPrintAsPhpParserPrintsThem(t *testing.T) {
	source := `<?php
namespace App;
final class Money {
    const ZERO = 0;
    public static function all(): array {
        return [
            'plain', "double", 'it\'s', 'back\\slash\\', "tab\tnew\nline \$x \"q\"", 'a\\\\b', "\x01ctl", 'naïve', "é",
            0, 42, -7, 0x1F, 0XfF, 0b101, 0o17, 017, 1_000_000, - -1, -(-2),
            1.5, 0.1, 1e3, 1E-7, .5, 1_000.25, 3.0, 1e25, 123456789012345678.0, 0.30000000000000004, -0.0,
            true, FALSE, null, PHP_EOL, \PHP_INT_MAX, __CLASS__, __LINE__,
            self::ZERO, static::ZERO,
            new self(), new static(1, 'x'), new self(amount: 5, currency: "EUR"), new self(new self(-1)),
        ];
    }
}
`
	path := filepath.Join(t.TempDir(), "Money.php")
	if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}
	probe := `require $argv[1];
$ast = (new PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse(file_get_contents($argv[2]));
$ast = (new PhpParser\NodeTraverser(new PhpParser\NodeVisitor\NameResolver()))->traverse($ast);
$items = (new PhpParser\NodeFinder())->findFirstInstanceOf($ast, PhpParser\Node\Expr\Array_::class)->items;
$printer = new PhpParser\PrettyPrinter\Standard();
echo json_encode(array_map(fn ($item) => $printer->prettyPrintExpr($item->value), $items));`
	out, err := exec.Command("php", "-r", probe, filepath.Join(repository(t), "bridge", "php", "parser", "autoload.php"), path).Output()
	if err != nil {
		t.Fatal(err)
	}
	var want []string
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}

	codebase, err := php.Here().Scan(path)
	if err != nil {
		t.Fatal(err)
	}
	items := codebase.WhereKind("Expr_Array").Get()[0].ChildrenIn("items")
	if len(items) != len(want) {
		t.Fatalf("%d items here, %d in PHP", len(items), len(want))
	}
	for index, item := range items {
		got, err := php.Printed(item.Child("value"), func(engine.Match) (string, bool) { return "", false })
		if err != nil {
			t.Errorf("%s: %v", want[index], err)

			continue
		}
		if got != want[index] {
			t.Errorf("got %s, PHP prints %s", got, want[index])
		}
	}
}

func repository(t *testing.T) string {
	t.Helper()
	root, err := filepath.Abs(filepath.Join("..", ".."))
	if err != nil {
		t.Fatal(err)
	}

	return root
}
