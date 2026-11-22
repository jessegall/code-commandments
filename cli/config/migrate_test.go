package config

import "testing"

func TestTheSchemaIsWrittenOnceForEachVersionOfIt(t *testing.T) {
	dir := t.TempDir()

	if written, err := WriteSchema(dir); !written || err != nil {
		t.Fatalf("%v %v", written, err)
	}

	if again, err := WriteSchema(dir); again || err != nil {
		t.Errorf("rewrote an unchanged schema: %v %v", again, err)
	}
}
