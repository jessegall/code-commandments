package source

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func TestAGlobMatchesAsPhpFnmatchDoes(t *testing.T) {
	raw, err := os.ReadFile("testdata/fnmatch.json")
	if err != nil {
		t.Fatal(err)
	}

	var cases []struct {
		Pattern  string `json:"pattern"`
		Name     string `json:"name"`
		Pathname bool   `json:"pathname"`
		Want     bool   `json:"want"`
	}
	json.Unmarshal(raw, &cases)

	for _, c := range cases {
		if got := fnmatch(c.Pattern, c.Name, c.Pathname); got != c.Want {
			t.Errorf("fnmatch(%q, %q, %v) = %v", c.Pattern, c.Name, c.Pathname, got)
		}
	}
}

func TestTheWalkSkipsWhatIsNeverSourceAndWhatTheProjectExcluded(t *testing.T) {
	root := t.TempDir()

	for _, file := range []string{
		"src/A.php", "src/b.vue", "src/c.ts", "src/d.py", "src/E.cs", "src/notes.md",
		"vendor/x.php", "node_modules/y.ts", ".hidden/z.php", "pkg.egg-info/p.py",
		"venv/pyvenv.cfg", "venv/lib/q.py", "app/app.csproj", "app/bin/Out.cs", "app/Keep.cs",
		"generated/G.php", "legacy/old/L.php",
		"web/package.json", "web/dist/bundle.js", "web/src/order-form.js", "web/src/Cart.tsx",
		"site/artisan", "site/public/build/app.js", "site/resources/js/menu.mjs",
	} {
		os.MkdirAll(filepath.Join(root, filepath.Dir(file)), 0o755)
		os.WriteFile(filepath.Join(root, file), nil, 0o644)
	}

	var got []string

	for _, file := range Sources(root, Under(root, []string{"generated", "legacy/*"})) {
		relative, _ := filepath.Rel(root, file)
		got = append(got, relative)
	}

	slices.Sort(got)
	want := []string{"app/Keep.cs", "site/resources/js/menu.mjs", "src/A.php", "src/E.cs", "src/b.vue", "src/c.ts", "src/d.py", "web/src/Cart.tsx", "web/src/order-form.js"}

	if !slices.Equal(got, want) {
		t.Errorf("sources %v", got)
	}
}

func TestAFileIsReadByItsExtension(t *testing.T) {
	switch {
	case OfFile("a/B.vue") != Vue || OfFile("x.unknown") != PHP:
		t.Error("OfFile")
	case !Judges("a.ts") || Judges("a.md"):
		t.Error("Judges")
	case OfFile("form.js") != TypeScript || OfFile("Cart.tsx") != TypeScript || OfFile("config.mjs") != TypeScript || !Judges("form.jsx"):
		t.Error("the TypeScript compiler's other forms and JavaScript read as TypeScript")
	}
}

// TestAWorktreeInsideTheProjectIsNoneOfItsSource holds the walk to the project's own code: a helper's git worktree
// checked out inside it is left out, while a submodule and a repository of its own are read.
func TestAWorktreeInsideTheProjectIsNoneOfItsSource(t *testing.T) {
	root := t.TempDir()
	for file, content := range map[string]string{
		"src/A.php":                              "<?php\n",
		".claude/worktrees/main-helper/.git":     "gitdir: /repo/.git/worktrees/main-helper\n",
		".claude/worktrees/main-helper/src/B.php": "<?php\n",
		"tools/helper/.git":                      "gitdir: /repo/.git/worktrees/helper\n",
		"tools/helper/C.php":                     "<?php\n",
		"modules/lib/.git":                       "gitdir: ../../.git/modules/lib\n",
		"modules/lib/D.php":                      "<?php\n",
		"platform/.git/HEAD":                     "ref: refs/heads/main\n",
		"platform/E.php":                         "<?php\n",
	} {
		os.MkdirAll(filepath.Join(root, filepath.Dir(file)), 0o755)
		os.WriteFile(filepath.Join(root, file), []byte(content), 0o644)
	}

	var got []string
	for _, file := range Sources(root, Excluded{}) {
		relative, _ := filepath.Rel(root, file)
		got = append(got, relative)
	}
	slices.Sort(got)

	if want := []string{"modules/lib/D.php", "platform/E.php", "src/A.php"}; !slices.Equal(got, want) {
		t.Errorf("sources %v", got)
	}
	if !InWorktree(root, filepath.Join(root, "tools/helper/C.php")) || InWorktree(root, filepath.Join(root, "modules/lib/D.php")) {
		t.Error("InWorktree")
	}
}
