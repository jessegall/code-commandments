package python_test

import (
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestForwardedKeywordArgumentsAreNoDictBag holds the rule to records: a mapping every caller fills with its own
// **kwargs is a generic funnel's keyword arguments handed on unchanged, and reading a field from it is no bag; the
// same read of a dict a caller builds by hand still is.
func TestForwardedKeywordArgumentsAreNoDictBag(t *testing.T) {
	kept := "def kept(n: int, fields: dict) -> bool:\n    return bool(fields.get(\"key\"))\n"
	forwarded := kept + "\n\ndef update(n: int, **fields) -> bool:\n    return kept(n, fields)\n"
	built := kept + "\n\ndef update(n: int) -> bool:\n    return kept(n, {\"key\": n})\n"

	if found := (pydetectors.DictBagDetector{}).Find(pythontest.FromSource(t, map[string]string{"phones.py": forwarded})); len(found) != 0 {
		t.Errorf("forwarded keyword arguments were flagged at %v", found[0].Location())
	}
	dispatched := "class Guard:\n    def kept(self, n: int, fields: dict) -> dict:\n        return fields\n\n\n" +
		"class VaultGuard(Guard):\n    def kept(self, n: int, fields: dict) -> dict:\n        return {} if fields.get(\"key\") else fields\n\n\n" +
		"class Phones:\n    def guard(self) -> Guard:\n        return VaultGuard()\n\n    def update(self, n: int, **fields) -> dict:\n        return self.guard().kept(n, fields)\n"
	if found := (pydetectors.DictBagDetector{}).Find(pythontest.FromSource(t, map[string]string{"phones.py": dispatched})); len(found) != 0 {
		t.Errorf("an override the funnel reaches through its base was flagged at %v", found[0].Location())
	}
	if found := (pydetectors.DictBagDetector{}).Find(pythontest.FromSource(t, map[string]string{"phones.py": built})); len(found) == 0 {
		t.Error("a hand-built dict read by a fixed key was not flagged")
	}
}
