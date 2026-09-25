package php_test

import (
	"encoding/json"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
)

// Docblock retyping and type mentions, against PHP's Docblock on the same text.
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

	var texts []string
	for _, c := range cases {
		texts = append(texts, c.text, c.name)
	}
	probe := `require $argv[1];
$in = json_decode($argv[2], true); $out = [];
for ($i = 0; $i < count($in['retype']); $i += 2) { $out[] = JesseGall\CodeCommandments\Ast\Support\Docblock::retype($in['retype'][$i], $in['retype'][$i + 1], $in['from'], 'array'); }
foreach ($in['mentions'] as $text) { $out[] = JesseGall\CodeCommandments\Ast\Support\Docblock::mentionsType($text, $in['from']) ? 'yes' : 'no'; }
echo json_encode($out);`
	input, _ := json.Marshal(map[string]any{"retype": texts, "mentions": mentions, "from": from})
	root, _ := filepath.Abs(filepath.Join("..", ".."))
	out, err := exec.Command("php", "-r", probe, filepath.Join(root, "vendor", "autoload.php"), string(input)).Output()
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
