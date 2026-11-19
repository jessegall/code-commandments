package library

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

var everyLanguage = []string{"go.mod", "app/Order.php", "app/Order.vue", "app/order.ts", "app/order.py", "app/Order.cs", "app/order.go"}

func holdingEveryLanguage(t *testing.T) string {
	t.Helper()

	return holding(t, everyLanguage...)
}

func holding(t *testing.T, files ...string) string {
	t.Helper()
	project := t.TempDir()
	for _, file := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(project, file)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(project, file), nil, 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return project
}

func TestOnlyTheSkillsOfTheLanguagesAProjectHoldsArePublished(t *testing.T) {
	project := holding(t, "composer.json", "app/Order.php")

	ids, err := At(project, config.Config{}).Publish()
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(ids, "commandments-php-absence") {
		t.Fatalf("a PHP project is not given the PHP skills: %v", ids)
	}

	for _, id := range ids {
		for _, absent := range []string{"-python-", "-csharp-", "-vue-", "-typescript-", "-go-"} {
			if strings.Contains(id, absent) {
				t.Errorf("a PHP-only project was given %s", id)
			}
		}
	}
}

func TestASkillOfALanguageTheProjectNoLongerHoldsIsWithdrawn(t *testing.T) {
	project := holding(t, "composer.json", "app/Order.php", "app/order.py")

	if _, err := At(project, config.Config{}).Publish(); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(project, Dir, "commandments-python-absence", "SKILL.md")); err != nil {
		t.Fatalf("the Python skill was not published while the project held Python: %v", err)
	}

	if err := os.Remove(filepath.Join(project, "app", "order.py")); err != nil {
		t.Fatal(err)
	}

	if _, err := At(project, config.Config{}).Publish(); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(project, Dir, "commandments-python-absence")); !os.IsNotExist(err) {
		t.Fatalf("the Python skill outlived the project's last Python file: %v", err)
	}
}
