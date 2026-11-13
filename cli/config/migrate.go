package config

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const Backup = "config.php.bak"

func Migrate(dir string) (bool, error) {
	dir = workspace.SettingsRoot(dir)
	php, json := workspace.Config(dir), workspace.JSONConfig(dir)

	if walk.Exists(json) {
		return false, nil
	}

	if !walk.Exists(php) {
		return false, nil
	}

	config, err := ReadPHP(php)
	if err != nil {
		return false, err
	}

	if err := (jsonConfig{json}).write(config); err != nil {
		return false, err
	}

	return true, os.Rename(php, filepath.Join(filepath.Dir(php), Backup))
}

func WriteSchema(dir string) (bool, error) {
	path := filepath.Join(filepath.Dir(workspace.JSONConfig(dir)), SchemaFile)
	schema := Schema(dir)

	if current, err := os.ReadFile(path); err == nil && string(current) == string(schema) {
		return false, nil
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o777); err != nil {
		return false, err
	}

	return true, atomic.Write(path, string(schema))
}
