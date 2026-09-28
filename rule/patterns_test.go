package rule_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/rule"
)

// found runs the query written in a backend rule over the codebase and says the lines it matched.
func found(t *testing.T, engineName, query string, codebase *engine.Codebase) string {
	t.Helper()

	written := `{"engine": "` + engineName + `", "sin": {"name": "x", "description": "x", "skill": "backend/absence"}, "find": ` + query + `}`

	parsed, err := rule.Parse("XDetector", []byte(written), shipped)
	if err != nil {
		t.Fatalf("%s: %v", query, err)
	}

	return fmt.Sprint(lines(parsed.Find(codebase)))
}

func TestARuleMatchesNamesAndTextByPattern(t *testing.T) {
	orders := codebase(t)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"nameLike": "c*"}]}`:                            "[19]",
		`{"select": "function", "where": [{"nameLike": "?ll"}]}`:                           "[7]",
		`{"select": "call", "where": [{"nameLike": "sel*"}]}`:                              "[9 15]",
		`{"select": "function", "where": [{"nameMatches": "^(all|each)$"}]}`:               "[7 12]",
		`{"select": "function", "where": [{"nameCase": "camel"}]}`:                         "[7 12 19]",
		`{"select": "type-declaration", "where": [{"nameCase": "pascal"}]}`:                "[5]",
		`{"select": "type-declaration", "where": [{"nameCase": "snake"}]}`:                 "[]",
		`{"select": "literal", "where": [{"textLike": "*where id*"}]}`:                     "[15]",
		`{"select": "literal", "where": [{"textMatches": "(?i)^SELECT \\*"}]}`:             "[9 15]",
		`{"select": "literal", "where": [{"textLike": "orders"}]}`:                         "[21]",
		`{"select": "function", "where": [{"namespaceLike": "App"}]}`:                      "[7 12 19]",
		`{"select": "function", "where": [{"namespaceLike": "\\App"}]}`:                    "[7 12 19]",
		`{"select": "function", "where": [{"namespaceLike": "Shop*"}]}`:                    "[]",
		`{"select": "call", "where": [{"resolvesLike": "App\\*", "of": "child:class"}]}`:   "[9 15 21]",
		`{"select": "call", "where": [{"resolvesLike": "*\\Cache", "of": "child:class"}]}`: "[21]",
	} {
		if got := found(t, "backend", query, orders); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestANameCaseTellsTheStylesApart(t *testing.T) {
	cart := pythontest.FromSource(t, map[string]string{"shop/__init__.py": "", "shop/cart.py": `MAX_ITEMS = 3

class Cart:
    def add_item(self, item):
        return item

    def _hidden(self):
        return 1

    def camelCase(self):
        return 2
`})

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"nameCase": "snake"}]}`:          "[4 7]",
		`{"select": "function", "where": [{"nameCase": "camel"}]}`:          "[7 10]",
		`{"select": "type-declaration", "where": [{"nameCase": "pascal"}]}`: "[3]",
		`{"select": "function", "where": [{"namespaceLike": "shop.cart"}]}`: "[4 7 10]",
		`{"select": "function", "where": [{"namespaceLike": "shop.*"}]}`:    "[4 7 10]",
	} {
		if got := found(t, "python", query, cart); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestAPatternTheToolCannotReadSaysWhy(t *testing.T) {
	for query, reason := range map[string]string{
		`{"select": "function", "where": [{"nameMatches": "("}]}`:                    "no regular expression",
		`{"select": "literal", "where": [{"textMatches": "[a-"}]}`:                   "no regular expression",
		`{"select": "function", "where": [{"nameCase": "title"}]}`:                   `nameCase "title"`,
		`{"select": "function", "where": [{"descendant": {"nameMatches": "("}}]}`:    "no regular expression",
		`{"select": "function", "where": [{"nameLike": "a*", "nameCase": "snake"}]}`: "makes 2",
	} {
		written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": ` + query + `}`

		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), reason) {
			t.Errorf("%s: %v, want %q", query, err, reason)
		}
	}
}

func TestALayerStepLooksItsNameUpInTheDeclaredLayers(t *testing.T) {
	orders := codebase(t)

	for query, want := range map[string]string{
		`{"select": "function", "where": [{"layer": "App"}]}`:                 "[7 12 19]",
		`{"select": "function", "where": [{"layer": "\\App"}]}`:               "[7 12 19]",
		`{"select": "function", "where": [{"layer": "App\\Domain"}]}`:         "[]",
		`{"select": "function", "reject": [{"layer": "App"}]}`:                "[]",
		`{"select": "function", "where": [{"descendant": {"layer": "App"}}]}`: "[7 12 19]",
	} {
		written := `{"engine": "backend", "sin": {"name": "x", "description": "x", "skill": "backend/absence"}, "find": ` + query + `}`

		parsed, err := rule.Parse("XDetector", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", query, err)
		}

		layered := parsed.WithLayers(map[string][]string{"App": nil, `App\Domain`: nil})
		if got := fmt.Sprint(lines(layered.Find(orders))); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

func TestALayerStepWithoutDeclaredLayersFindsNothing(t *testing.T) {
	if got := found(t, "backend", `{"select": "function", "where": [{"layer": "App"}]}`, codebase(t)); got != "[]" {
		t.Errorf("found %s with no layers declared, want []", got)
	}
}

func TestALayerStepOnAnEngineWithoutLayersIsRefused(t *testing.T) {
	written := `{"engine": "frontend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"layer": "App"}]}}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), "has none") {
		t.Errorf("a frontend layer step: %v", err)
	}
}

func TestASlipOfTheKeyboardIsNamedWithWhatWasMeant(t *testing.T) {
	for written, hint := range map[string]string{
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "function", "where": [{"nameLke": "a*"}]}}`:                 `did you mean "nameLike"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "function", "where": [{"isd": "loop"}]}}`:                   `did you mean "is"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "fucntion"}}`:                                               `did you mean "function"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"is": "lop"}]}}`:                         `did you mean "loop"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"is": "loop", "of": "parnt"}]}}`:         `did you mean "parent"?`,
		`{"engine": "bakend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call"}}`:                                                    `did you mean "backend"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "type-declaration", "where": [{"typeKind": "interfase"}]}}`: `did you mean "interface"?`,
		`{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "function", "where": [{"parameters": {"atLest": 3}}]}}`:     `did you mean "atLeast"?`,
	} {
		if _, err := rule.Parse("X", []byte(written), shipped); err == nil || !strings.Contains(err.Error(), hint) {
			t.Errorf("%s: %v, want %q", written, err, hint)
		}
	}

	written := `{"engine": "backend", "sin": {"name": "x", "skill": "backend/absence"}, "find": {"select": "call", "where": [{"zzzzzzzz": 1}]}}`
	if _, err := rule.Parse("X", []byte(written), shipped); err == nil || strings.Contains(err.Error(), "did you mean") {
		t.Errorf("a key near nothing is suggested nothing: %v", err)
	}
}
