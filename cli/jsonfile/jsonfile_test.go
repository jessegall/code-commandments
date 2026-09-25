package jsonfile

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestAFileIsWrittenBackAsThePHPToolWritesIt(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("no php to write with the PHP tool")
	}

	document := `{"name":"acme/app","autoload":{"psr-4":{"App\\":"src/"}},"extra":{},"scripts":{"test":"phpunit","post-install-cmd":["@php artisan x"]},` +
		`"config":{"allow-plugins":{"a/b":true},"platform":{"php":"8.3"},"sort-packages":false,"process-timeout":0},"note":"ünïcode / slash","none":null,"list":[]}`

	php, golang := filepath.Join(t.TempDir(), "composer.json"), filepath.Join(t.TempDir(), "composer.json")

	for _, path := range []string{php, golang} {
		if err := os.WriteFile(path, []byte(document), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	repo, _ := filepath.Abs("../..")
	script := `require '` + repo + `/vendor/autoload.php';
$file = new \JesseGall\CodeCommandments\Support\JsonFile($argv[1]);
$file->write($file->read());`

	if out, err := exec.Command("php", "-r", script, "--", php).CombinedOutput(); err != nil {
		t.Fatalf("php: %v\n%s", err, out)
	}

	object, read := Read(golang)
	if !read {
		t.Fatal("not read")
	}

	if err := Write(golang, object); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile(php)
	got, _ := os.ReadFile(golang)

	if string(got) != string(want) {
		t.Errorf("wrote\n%s\nthe PHP tool wrote\n%s", got, want)
	}
}

func TestAKeyKeepsItsPlaceAndANewOneGoesLast(t *testing.T) {
	object := NewObject()
	object.Set("a", 1)
	object.Set("b", 2)
	object.Set("a", 3)
	object.Set("c", 4)
	object.Delete("b")

	if keys := object.Keys(); len(keys) != 2 || keys[0] != "a" || keys[1] != "c" {
		t.Errorf("%v", keys)
	}
}
