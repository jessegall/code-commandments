package php_test

import (
	"encoding/json"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
)

// Docblock retyping and type mentions, against what PHP's Docblock answered for the same text, recorded in
// testdata/docblock-retype.json.
func TestDocblockRetypingAnswersAsPHPsDocblock(t *testing.T) {
	from := `Spatie\LaravelData\DataCollection`
	cases := []struct{ text, name string }{
		{"/** @var DataCollection<int, NodeData> $nodes */", "nodes"},
		{"/**\n * @param DataCollection<int, NodeData> $nodes the nodes\n * @param ?\\Spatie\\LaravelData\\DataCollection $others\n */", "nodes"},
		{"/**\n * @param ?\\Spatie\\LaravelData\\DataCollection ...$others\n */", "others"},
		{"/** @var DataCollection */", "rows"},
		{"/** @var array<string, DataCollection<string, X>> $rows */", "rows"},
		{"/**\r\n * @var DataCollection<int, Row>\r\n */", "rows"},
		{"/** @var MyDataCollection $rows */", "rows"},
	}
	mentions := []string{"DataCollection", "a DataCollection<int>", "MyDataCollection", `Foo\DataCollection`, "DataCollections", "(DataCollection)", "x_DataCollection"}

	out, err := os.ReadFile("testdata/docblock-retype.json")
	if err != nil {
		t.Fatal(err)
	}
	var want []string
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}

	var got []string
	for _, c := range cases {
		got = append(got, php.DocblockRetype(c.text, c.name, from, "array"))
	}
	for _, text := range mentions {
		answer := "no"
		if php.DocblockMentionsType(text, from) {
			answer = "yes"
		}
		got = append(got, answer)
	}
	for index := range want {
		if got[index] != want[index] {
			t.Errorf("case %d: got %q, PHP answers %q", index, got[index], want[index])
		}
	}
}
