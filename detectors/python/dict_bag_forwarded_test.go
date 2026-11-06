package python_test

import (
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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

func TestKeywordsForwardedThroughAClassAttributeAreNoDictBag(t *testing.T) {
	resources := "class Resource:\n    @classmethod\n    def unfilled(cls, data: dict) -> list:\n        return []\n\n\n" +
		"class Trigger(Resource):\n    @classmethod\n    def unfilled(cls, data: dict) -> list:\n        return [\"fact\"] if data.get(\"when\") == \"state\" else []\n\n\n"
	controllers := "class Triggers(Controller):\n    resource = Trigger\n"
	for name, controller := range map[string]string{
		"assigned":  "class Controller:\n    resource = Resource\n\n    def create(self, title: str, **data) -> list:\n        return self.resource.unfilled(data)\n\n\n",
		"annotated": "class Controller:\n    resource: type[Resource]\n\n    def create(self, title: str, **data) -> list:\n        return self.resource.unfilled(data)\n\n\n",
	} {
		source := resources + controller + controllers
		if found := (pydetectors.DictBagDetector{}).Find(pythontest.FromSource(t, map[string]string{"controllers.py": source})); len(found) != 0 {
			t.Errorf("%s: a hook reached through the class attribute was flagged at %v", name, found[0].Location())
		}
	}
	placeholder := "class Resource:\n    pass\n\n\n" +
		"class Shape:\n    @classmethod\n    def unfilled(cls, data: dict) -> list:\n        return []\n\n\n" +
		"class Trigger(Shape, Resource):\n    @classmethod\n    def unfilled(cls, data: dict) -> list:\n        return [\"fact\"] if data.get(\"when\") == \"state\" else []\n\n\n" +
		"class Controller:\n    resource = Resource\n\n    def create(self, title: str, **data) -> list:\n        return self.resource.unfilled(data)\n\n\n" +
		controllers
	if found := (pydetectors.DictBagDetector{}).Find(pythontest.FromSource(t, map[string]string{"controllers.py": placeholder})); len(found) != 0 {
		t.Errorf("a hook only the subclass's held class declares was flagged at %v", found[0].Location())
	}
}
