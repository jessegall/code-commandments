package config_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
)

// TestAStrayGoFileIsNoGoProject holds a language that declares itself to its own declaration. A PHP shop keeps a
// one-off Go script in a folder somewhere; judging it would hand that project a set of findings it never asked
// for, from a release it only upgraded. A Go module always declares itself with a go.mod, and has had to since
// Go 1.16, so the declaration is what says a tree is written in Go.
func TestAStrayGoFileIsNoGoProject(t *testing.T) {
	shop := tree(t, map[string]string{
		"composer.json":    "{}",
		"app/Cart.php":     "<?php\n\nfinal class Cart {}\n",
		"scripts/build.go": "package main\n\nfunc main() {}\n",
	})

	project := config.Config{Paths: []string{"app", "scripts"}}

	if project.Reads(shop, source.Go) {
		t.Error("a PHP project with one stray Go file reads as written in Go")
	}
	if project.Holds(shop, source.Go) {
		t.Error("a PHP project with one stray Go file holds Go")
	}
	if !project.Reads(shop, source.PHP) {
		t.Error("a PHP project does not read as written in PHP")
	}
}

// TestAGoModuleIsAGoProject holds the other half: a tree that declares a module is read as Go, wherever the
// judge is pointed — at the module, at one folder of it, or at a tree holding several.
func TestAGoModuleIsAGoProject(t *testing.T) {
	module := tree(t, map[string]string{
		"go.mod":       "module example.com/shop\n",
		"cart/cart.go": "package cart\n\ntype Cart struct{}\n",
	})

	project := config.Config{Paths: []string{"cart"}}

	if !project.Reads(module, source.Go) {
		t.Error("a module's own root does not read as written in Go")
	}
	if !project.Reads(filepath.Join(module, "cart"), source.Go) {
		t.Error("a folder of a module does not read as written in Go, where the go.mod sits above it")
	}

	several := tree(t, map[string]string{
		"services/billing/go.mod":  "module example.com/billing\n",
		"services/billing/main.go": "package main\n\nfunc main() {}\n",
	})
	if !project.Reads(several, source.Go) {
		t.Error("a tree holding a module below it does not read as written in Go")
	}
}

// TestALanguageThatNeedsNoDeclaringIsReadAnywhere holds every other language to what it was: a file of it is
// enough, since nothing else declares a PHP, Python, C# or front-end project.
func TestALanguageThatNeedsNoDeclaringIsReadAnywhere(t *testing.T) {
	empty := tree(t, map[string]string{"README.md": "# nothing here\n"})
	project := config.Config{}

	for _, language := range []source.Language{source.PHP, source.Vue, source.TypeScript, source.Python, source.CSharp} {
		if !project.Reads(empty, language) {
			t.Errorf("%s needs a declaration it never had", language.Label())
		}
	}
	if project.Reads(empty, source.Go) {
		t.Error("a tree with no go.mod reads as written in Go")
	}
}

// TestALanguageTurnedOffIsNotReadEvenWhereItIsDeclared holds the project's own word above the declaration.
func TestALanguageTurnedOffIsNotReadEvenWhereItIsDeclared(t *testing.T) {
	module := tree(t, map[string]string{"go.mod": "module example.com/shop\n", "main.go": "package main\n\nfunc main() {}\n"})

	off := config.Config{DisabledLanguages: []source.Language{source.Go}}
	if off.Reads(module, source.Go) {
		t.Error("a project that turned Go off reads as written in it")
	}
}

// tree writes the files under a fresh folder and answers its resolved path.
func tree(t *testing.T, files map[string]string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}
