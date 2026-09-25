package config

import (
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAConfigPHPMigratesToTheConfigJSONThatSaysTheSame(t *testing.T) {
	dir := t.TempDir()
	must(t, os.MkdirAll(filepath.Join(dir, ".commandments"), 0o755))
	must(t, os.WriteFile(workspace.Config(dir), []byte(`<?php
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;

return function ($config): void {
    $config->paths('src')->exclude('src/Generated');
    $config->disable(ArrayBag::class);
    $config->configure(fn (NamespaceDependencyDetector $d) => $d->layer('App\Domain')->layer('App\Http', mayUse: ['App\Domain']));
};
`), 0o644))

	before, err := ReadPHP(workspace.Config(dir))
	must(t, err)

	if migrated, err := Migrate(dir); !migrated || err != nil {
		t.Fatalf("%v %v", migrated, err)
	}

	after, err := ReadJSON(workspace.JSONConfig(dir))
	if err != nil || !reflect.DeepEqual(after, before.Positional()) {
		t.Errorf("migrated\n%#v\nfrom\n%#v\n%v", after, before, err)
	}

	if _, err := os.Stat(filepath.Join(dir, ".commandments", Backup)); err != nil {
		t.Error("config.php was not kept as a backup")
	}

	if _, err := os.Stat(workspace.Config(dir)); err == nil {
		t.Error("config.php is still read")
	}

	if again, err := Migrate(dir); again || err != nil {
		t.Errorf("migrated twice: %v %v", again, err)
	}
}

func TestAConfigPHPTheTreeCannotReadStaysWhereItIs(t *testing.T) {
	dir := t.TempDir()
	must(t, os.MkdirAll(filepath.Join(dir, ".commandments"), 0o755))
	must(t, os.WriteFile(workspace.Config(dir), []byte("<?php\nreturn function ($config) {\n    $config->exclude(getenv('X'));\n};\n"), 0o644))

	_, err := Migrate(dir)

	var invalid *cli.InvalidConfiguration
	if !errors.As(err, &invalid) {
		t.Errorf("err %v", err)
	}

	if _, err := os.Stat(workspace.JSONConfig(dir)); err == nil {
		t.Error("wrote a config.json from a config it could not read")
	}
}

func TestAProjectWithNothingToMigrateIsLeftAlone(t *testing.T) {
	if migrated, err := Migrate(t.TempDir()); migrated || err != nil {
		t.Errorf("%v %v", migrated, err)
	}
}

func TestTheSchemaIsWrittenOnceForEachVersionOfIt(t *testing.T) {
	dir := t.TempDir()

	if written, err := WriteSchema(dir); !written || err != nil {
		t.Fatalf("%v %v", written, err)
	}

	if again, err := WriteSchema(dir); again || err != nil {
		t.Errorf("rewrote an unchanged schema: %v %v", again, err)
	}
}
