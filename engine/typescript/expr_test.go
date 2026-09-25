package typescript_test

import (
	"encoding/json"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// Each expression read here and by the PHP tool's own expression parser, every read the extraction makes of it.
func TestExpressionsReadAsThePHPToolsParserReadsThem(t *testing.T) {
	expressions := []string{
		"order.customer.fullName", "a.b?.c", "items[i].name", "copy(json, 1)", "$emit('save', x)", "go(a.b, 'x', 2)",
		"go(f(x))", "a && b || c ?? d", "x = y + 1", "count++", "!open", "-1", "typeof x", "a ? 'x' : 'y'",
		"a ? 1 : 'y'", "[1, 2, 3]", "['a', 1]", "[]", "{ name: '', email: '', age: 0, ok: true }", "{ a, b: c }",
		"() => a > b", "(x: number) => x * 2", "async () => { await go() }", "function (e) { return e }",
		"x => x.id", "($event.target as HTMLInputElement).value", "a.b.c.greet(d.e.f)", "useForm<ProductForm>({ a: 1 })",
		"useForm({ name: '', tags: [] })", "null", "undefined", "true", "1e3", "1_000", "0x1f", "'s'", "`t`",
		"obj[key]", "list.filter(i => i.on).length", "a | b", "x !== null && x.y", "a in b", "a instanceof B",
		"{ 'quoted-key': 1, 2: 'x' }", "form.errors.name", "new Date()", "a ?? 'd'", "n % 2 === 0",
	}
	fors := []string{"item in items", "(item, i) in list.rows", "{ id, name } in people", "n in 10", "[a, b] of pairs"}
	probe := `require $argv[1];
use JesseGall\CodeCommandments\Ts\Expr\Parser;
$in = json_decode($argv[2], true); $out = [];
foreach ($in['expressions'] as $source) {
    $e = Parser::parse($source);
    $call = $e->asCall();
    $out[] = [$e->roots(), $e->calledFunctions(), $e->chains(), $e->asChain(), $e->callee(), $e->objectShape(),
        $call === null ? null : [$call->name, $call->trailingArguments(), $call->arity()], $e->inferType(), $e->returnType(), $e->source(),
        $e->is(JesseGall\CodeCommandments\Ts\Expr\ExprKind::Assign) ? $e->get('target')->roots() : null, $e->argument(0)?->objectShape()];
}
foreach ($in['fors'] as $source) { $f = Parser::parseFor($source); $out[] = [$f->get('aliases'), $f->get('iterable')->roots(), $f->get('iterable')->source()]; }
echo json_encode($out);`
	input, _ := json.Marshal(map[string][]string{"expressions": expressions, "fors": fors})
	root, _ := filepath.Abs(filepath.Join("..", ".."))
	out, err := exec.Command("php", "-r", probe, filepath.Join(root, "vendor", "autoload.php"), string(input)).Output()
	if err != nil {
		t.Fatal(err)
	}
	var want []json.RawMessage
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}
	orNull := func(value string, ok bool) any {
		if !ok {
			return nil
		}

		return value
	}
	list := func(values []string) []string {
		if values == nil {
			return []string{}
		}

		return values
	}
	for index, source := range expressions {
		e := typescript.ParseExpression(source)
		chains := e.Chains()
		if chains == nil {
			chains = [][]string{}
		}
		var chain any
		if segments, ok := e.AsChain(); ok {
			chain = segments
		}
		var call any
		if found, ok := e.AsCall(); ok {
			call = []any{found.Name, found.TrailingArguments(), found.Arity()}
		}
		var target any
		if e.Is(typescript.AssignExpr) {
			target = list(e.Target().Roots())
		}
		var argumentShape any
		if argument, ok := e.Argument(0); ok {
			argumentShape = orNull(argument.ObjectShape())
		}
		callee, calls := e.Callee()
		got := []any{list(e.Roots()), list(e.CalledFunctions()), chains, chain, orNull(callee, calls), orNull(e.ObjectShape()), call,
			orNull(e.InferType()), orNull(e.ReturnType()), e.Source(), target, argumentShape}
		compare(t, source, got, want[index])
	}
	for index, source := range fors {
		loop := typescript.ParseFor(source)
		compare(t, source, []any{list(loop.Aliases), list(loop.Iterable().Roots()), loop.Iterable().Source()}, want[len(expressions)+index])
	}
}

func compare(t *testing.T, source string, got any, want json.RawMessage) {
	t.Helper()
	gotJSON, _ := json.Marshal(got)
	var normal any
	_ = json.Unmarshal(want, &normal)
	wantJSON, _ := json.Marshal(normal)
	if string(gotJSON) != string(wantJSON) {
		t.Errorf("%q:\n  go  %s\n  php %s", source, gotJSON, wantJSON)
	}
}
