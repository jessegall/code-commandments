package source

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

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
	case OfFile("a/B.vue") != Vue || OfFile("x.unknown") != "":
		t.Error("OfFile")
	case !Judges("a.ts") || Judges("a.md"):
		t.Error("Judges")
	case OfFile("form.js") != TypeScript || OfFile("Cart.tsx") != TypeScript || OfFile("config.mjs") != TypeScript || !Judges("form.jsx"):
		t.Error("the TypeScript compiler's other forms and JavaScript read as TypeScript")
	}
}

func TestAWorktreeInsideTheProjectIsNoneOfItsSource(t *testing.T) {
	root := t.TempDir()
	for file, content := range map[string]string{
		"src/A.php":                               "<?php\n",
		".claude/worktrees/main-helper/.git":      "gitdir: /repo/.git/worktrees/main-helper\n",
		".claude/worktrees/main-helper/src/B.php": "<?php\n",
		"tools/helper/.git":                       "gitdir: /repo/.git/worktrees/helper\n",
		"tools/helper/C.php":                      "<?php\n",
		"modules/lib/.git":                        "gitdir: ../../.git/modules/lib\n",
		"modules/lib/D.php":                       "<?php\n",
		"platform/.git/HEAD":                      "ref: refs/heads/main\n",
		"platform/E.php":                          "<?php\n",
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

func TestAFolderLeftOutAtAnyDepthIsLeftOutAtTheTopToo(t *testing.T) {
	root := t.TempDir()

	for _, under := range []string{
		"dist", "src", "modules/library/web/dist", "modules/profiles/web/dist", "bootstrap/cache",
	} {
		if err := os.MkdirAll(filepath.Join(root, under), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, under, "thing.py"), []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	excluded := Under(root, []string{"**/dist", "**/bootstrap/cache"})

	for _, left := range []string{
		"dist", "dist/thing.py",
		"modules/library/web/dist", "modules/library/web/dist/thing.py",
		"modules/profiles/web/dist/thing.py",
		"bootstrap/cache", "bootstrap/cache/thing.py",
	} {
		if !excluded.Covers(filepath.Join(root, left)) {
			t.Errorf("%s is read; one pattern covers that folder wherever it lies, the top of the project included", left)
		}
	}

	for _, read := range []string{"src", "src/thing.py", "modules"} {
		if excluded.Covers(filepath.Join(root, read)) {
			t.Errorf("%s is left out by a pattern that names only build folders", read)
		}
	}
}
