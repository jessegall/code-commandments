package typescript_test

import (
	"encoding/json"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// Each type string printed, its references listed and its ref unwrapped here and by the PHP tool's own grammar.
func TestTypesReadAsThePHPToolsGrammarReadsThem(t *testing.T) {
	types := []string{
		"string", "Order", "Order[]", "Ref<number>", "ComputedRef<Order | null>", "Ref<Ref<string>>",
		"Record<string,   Order[]>", "{ a: string; b?: number }", "{a:string,b:number,}", "{ readonly a: string }",
		"{ go(x: number, y?: string): void; }", "(a: string, ...rest: number[]) => void", "() => Promise<void>",
		"'draft' | 'live'", "-1 | 2", "true", "typeof config", "typeof a.b", "keyof Order", "Order['customer']",
		"Order[number]", "[string, number]", "(string | number)[]", "A & B & { c: C }", "| A | B",
		"T extends string ? A : B", "{ [key: string]: Order }", "{ [K in Keys]: V }", "Array<{ id: number }>",
		"InertiaForm<{ name: string; email: string }>", "Map<string, Set<number>>", "unknown", "Ref<A> | Ref<B> | A",
		"MaybeRefOrGetter<string>", "x is Foo", "", "`a${B}`", "Foo<keyof Bar>", "{ 'quoted-key': string }",
	}
	probe := `require $argv[1];
$out = [];
foreach (json_decode($argv[2], true) as $source) {
    $type = JesseGall\CodeCommandments\Ts\Parser::type($source);
    $out[] = [$type->render(), $type->references(), $type->unwrapRef()->render()];
}
echo json_encode($out);`
	input, _ := json.Marshal(types)
	root, _ := filepath.Abs(filepath.Join("..", ".."))
	out, err := exec.Command("php", "-r", probe, filepath.Join(root, "vendor", "autoload.php"), string(input)).Output()
	if err != nil {
		t.Fatal(err)
	}
	var want [][3]any
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}
	for index, source := range types {
		typed := typescript.ParseType(source)
		references := typed.References()
		if references == nil {
			references = []string{}
		}
		got := [3]any{typed.Render(), references, typed.UnwrapRef().Render()}
		gotJSON, _ := json.Marshal(got)
		wantJSON, _ := json.Marshal(want[index])
		if string(gotJSON) != string(wantJSON) {
			t.Errorf("%q:\n  go  %s\n  php %s", source, gotJSON, wantJSON)
		}
	}
}
