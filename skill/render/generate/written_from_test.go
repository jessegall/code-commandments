package main

import (
	"os"
	"path/filepath"
	"testing"
)

// The key is what keeps the pre-commit hook off a run of minutes on every commit that cannot change a generated
// byte, so what it must do is answer the same for the same sources and differently for anything else.

// aFixtureTree is a folder holding each file with its content.
func aFixtureTree(t *testing.T, files map[string]string) string {
	t.Helper()
	root := t.TempDir()

	for name, content := range files {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

// TestTheSameSourcesAnswerTheSameKey holds the whole point: read twice with nothing changed, the answer is the
// same, so the second run of the generator has something to compare against.
func TestTheSameSourcesAnswerTheSameKey(t *testing.T) {
	fixtures := aFixtureTree(t, map[string]string{
		"backend/app/Orders.php": "<?php\n",
		"python/shop/cart.py":    "def total():\n    return 0\n",
	})

	first, err := writtenFrom(fixtures)
	if err != nil {
		t.Fatal(err)
	}
	second, err := writtenFrom(fixtures)
	if err != nil {
		t.Fatal(err)
	}

	if first != second {
		t.Errorf("the same sources answered %s and then %s", first, second)
	}
	if first == "" {
		t.Error("the key is empty, so it says nothing about the sources")
	}
}

// TestAFixtureThatChangedAnswersANewKey holds the half that matters most: an example carved from a fixture must be
// written again when that fixture moves, so a changed file has to change the key.
func TestAFixtureThatChangedAnswersANewKey(t *testing.T) {
	for name, change := range map[string]func(t *testing.T, root string){
		"a file's content": func(t *testing.T, root string) {
			if err := os.WriteFile(filepath.Join(root, "backend/app/Orders.php"), []byte("<?php\n\nfinal class Orders {}\n"), 0o644); err != nil {
				t.Fatal(err)
			}
		},
		"a file added": func(t *testing.T, root string) {
			if err := os.WriteFile(filepath.Join(root, "backend/app/Carts.php"), []byte("<?php\n"), 0o644); err != nil {
				t.Fatal(err)
			}
		},
		"a file removed": func(t *testing.T, root string) {
			if err := os.Remove(filepath.Join(root, "python/shop/cart.py")); err != nil {
				t.Fatal(err)
			}
		},
		"a file renamed": func(t *testing.T, root string) {
			if err := os.Rename(filepath.Join(root, "python/shop/cart.py"), filepath.Join(root, "python/shop/basket.py")); err != nil {
				t.Fatal(err)
			}
		},
	} {
		t.Run(name, func(t *testing.T) {
			fixtures := aFixtureTree(t, map[string]string{
				"backend/app/Orders.php": "<?php\n",
				"python/shop/cart.py":    "def total():\n    return 0\n",
			})

			before, err := writtenFrom(fixtures)
			if err != nil {
				t.Fatal(err)
			}

			change(t, fixtures)

			after, err := writtenFrom(fixtures)
			if err != nil {
				t.Fatal(err)
			}

			if after == before {
				t.Errorf("%s left the key as it was, so the skills would never be written again", name)
			}
		})
	}
}

// TestAnEmptiedFileIsNotTheSameAsOneThatWasAlwaysEmpty holds the case a hash of contents alone would miss, which is
// why each file's name and size go into the key beside its bytes.
func TestAnEmptiedFileIsNotTheSameAsOneThatWasAlwaysEmpty(t *testing.T) {
	held := aFixtureTree(t, map[string]string{"backend/app/Orders.php": "<?php\n", "backend/app/Carts.php": ""})
	moved := aFixtureTree(t, map[string]string{"backend/app/Orders.php": "", "backend/app/Carts.php": "<?php\n"})

	first, err := writtenFrom(held)
	if err != nil {
		t.Fatal(err)
	}
	second, err := writtenFrom(moved)
	if err != nil {
		t.Fatal(err)
	}

	if first == second {
		t.Error("two trees whose content moved between files answered the one key")
	}
}

// TestTheKeyIsOnlyTrustedWhenItWasWrittenForTheseSkills holds how the key is read back: a mark from other sources,
// or none at all, is not a reason to skip the work.
func TestTheKeyIsOnlyTrustedWhenItWasWrittenForTheseSkills(t *testing.T) {
	if alreadyWrittenFrom("a key nothing ever wrote") {
		t.Error("a key nobody wrote was taken as the one the skills were written from")
	}
	if alreadyWrittenFrom("") {
		t.Error("an empty key was taken as a reason to skip the work")
	}
}
