package jsonfile

import (
	"os"
	"path/filepath"
	"testing"
)

// TestAFileIsWrittenBackAsThePHPToolWritesIt holds a read and write-back to what the PHP tool wrote for the same
// document, recorded in testdata/written.json.
func TestAFileIsWrittenBackAsThePHPToolWritesIt(t *testing.T) {
	document := `{"name":"acme/app","autoload":{"psr-4":{"App\\":"src/"}},"extra":{},"scripts":{"test":"phpunit","post-install-cmd":["@php artisan x"]},` +
		`"config":{"allow-plugins":{"a/b":true},"platform":{"php":"8.3"},"sort-packages":false,"process-timeout":0},"note":"ünïcode / slash","none":null,"list":[]}`

	golang := filepath.Join(t.TempDir(), "composer.json")
	if err := os.WriteFile(golang, []byte(document), 0o644); err != nil {
		t.Fatal(err)
	}

	object, read := Read(golang)
	if !read {
		t.Fatal("not read")
	}

	if err := Write(golang, object); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile("testdata/written.json")
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
