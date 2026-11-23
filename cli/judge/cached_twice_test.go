package judge

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestASecondJudgeOfAnUnchangedTreeParsesNoFileAndAsksNoRuleAgain(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, t.TempDir())
	project := emptyProject(t)
	sources := map[string]string{
		"cart.php":  "<?php\n\nfinal class Cart\n{\n    public function total(): int\n    {\n        return 1;\n    }\n}\n",
		"stock.php": "<?php\n\nfinal class Stock\n{\n    public function count(): int\n    {\n        return 2;\n    }\n}\n",
		"stock.go":  "package stock\n\nfunc Count() int {\n\treturn 1\n}\n",
		"go.mod":    "module stock\n\ngo 1.22\n",
		"stock.py":  "def count() -> int:\n    return 1\n",
		"stock.ts":  "export function count(): number {\n    return 1\n}\n",
	}
	for name, source := range sources {
		if err := os.WriteFile(filepath.Join(project, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	judged := func() engine.Work {
		before := engine.Counted()
		code, _, warned := judgedIn(t, project, ".", "--no-checklist")
		if code > 1 {
			t.Fatalf("the judge exits %d: %s", code, warned)
		}

		return engine.Since(before)
	}

	first := judged()
	if written := int64(len(sources) - 1); first.Parsed != written || first.Kept != 0 {
		t.Fatalf("the first judge of %d new source files read %d, %d of them from the cache, where each is parsed once", written, first.Parsed, first.Kept)
	}

	second := judged()
	if second.Parsed != second.Kept || second.Judged != 0 {
		t.Errorf("a second judge of the unchanged tree parsed %d files again and asked %d rule answers again, where the cache serves every file and every answer", second.Parsed-second.Kept, second.Judged)
	}

	if err := os.WriteFile(filepath.Join(project, "cart.php"), []byte(sources["cart.php"]+"\n// a later line\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	changed := judged()
	if parsed := changed.Parsed - changed.Kept; parsed != 1 {
		t.Errorf("one file changed and %d files were parsed, where only the changed file is", parsed)
	}
}
