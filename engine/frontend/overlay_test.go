package frontend_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend"
)

func TestAServedBridgeReadsDraftedTextInPlaceOfTheDisk(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	existing := filepath.Join(root, "Page.vue")
	if err := os.WriteFile(existing, []byte("<template>\n  <div>{{ a }}</div>\n</template>\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	created := filepath.Join(root, "components", "Card.vue")
	contents := map[string]string{
		existing: "<template>\n  <Card :a=\"a\" />\n</template>\n",
		created:  "<template>\n  <div>{{ a }}</div>\n</template>\n",
	}

	command, err := frontend.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	server, err := bridge.Serve(command)
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	stream, err := server.Ask(bridge.Request{Paths: []string{root}, Contents: contents})
	if err != nil {
		t.Fatal(err)
	}
	codebase := engine.New(engine.ReadThrough(contents), stream)

	files := codebase.Files()
	if len(files) != 2 || files[0].Path != existing || files[1].Path != created {
		t.Fatalf("got %d files", len(files))
	}
	var tags []string
	for _, element := range codebase.WhereKind("Element").Get() {
		span, err := element.Span()
		if err != nil {
			t.Fatal(err)
		}
		tags = append(tags, span.Text())
	}
	if len(tags) != 2 || tags[0] != `<Card :a="a" />` || tags[1] != "<div>{{ a }}</div>" {
		t.Fatalf("the elements span %q", tags)
	}
}
