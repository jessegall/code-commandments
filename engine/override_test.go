package engine

import (
	"strings"
	"testing"
)

func TestAMethodWhoseBridgeNamesWhatItOverridesIsAnOverrideWithoutItsSupertypeRead(t *testing.T) {
	file := `{"file": {"path": "/src/tests/fixtures/reader.py", "language": "python", "errors": 0, "module": "app.reader",
"resolver": {"tool": "mypy", "ran": true},
"root": {"id": 0, "kind": "file", "span": [0, 90, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 90, 1], "name": "app.reader", "parts": {"statements": [
  {"id": 2, "kind": "type", "span": [0, 90, 1], "name": "Reader", "form": "class", "symbol": "app.reader.Reader", "parts": {"writtenMembers": [
    {"id": 3, "kind": "function", "span": [20, 50, 2], "name": "initialize", "form": "method", "symbol": "app.reader.Reader.initialize", "obligations": [{"symbol": "vendor.Base.initialize", "origin": "external"}]},
    {"id": 4, "kind": "function", "span": [51, 89, 3], "name": "close", "form": "method", "symbol": "app.reader.Reader.close"}]}}]}}]}},
"comments": []}}`
	codebase := versionFiveCodebase(t, strings.TrimSpace(file))
	methods := codebase.WhereKind("function").Get()

	if !methods[0].IsOverride() {
		t.Error("a method its bridge says overrides vendor.Base.initialize is no override")
	}
	if methods[1].IsOverride() {
		t.Error("a method with no obligation and no supertype is an override")
	}
}
