package vue_test

import (
	"bytes"
	"encoding/json"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine/vue"
)

// Each script read here and by the PHP tool's Script, every read an extraction makes of it.
func TestAScriptReadsAsThePHPToolsScriptReadsIt(t *testing.T) {
	scripts := []string{
		`
import type { Order, Customer } from '@/types';
import { computed, ref } from 'vue';
import Badge from './Badge.vue';
import * as utils from '@/utils'
import X = App.Http.View.Page;
interface Props { order: Order; customer?: Customer; go(x: number): void; [key: string]: unknown }
type Row = { id: number; label: string }
type Id = string | number
const props = defineProps<Props>();
const emit = defineEmits<{ save: [id: number] }>();
const count = ref(0);
const total = computed(() => props.order.total > 0);
const names = ref<string[]>([]);
const doubled: number = count.value * 2;
const LIMIT = 50;
const LABELS = ['a', 'b'];
let flag = true
const { data, error } = useFetch('/x');
const onSave = (id: number): void => emit('save', id);
function format(value: number, unit?: string): string { return value + unit }
async function load() { const rows = ref<Row[]>([]); const busy = ref(false); return { rows, busy, other: 1 } }
const form = useForm<ProductForm>({ name: '' });
const plain = useForm({ name: '', age: 0, tags: [] });
export default {}
`,
		`
const props = withDefaults(defineProps<{ title: string; count?: number }>(), { count: 0 });
defineEmits(['close'])
const open = ref(false)
const label = computed(() => props.title)
class Helper {}
`,
		`
import { useThing } from './useThing';
interface Base { id: number }
interface Item<T> extends Base { value: T; readonly tags: string[]; 'quoted-key'?: boolean }
defineProps<{ item: Item<string>; rows: Row[] }>()
if (x) { const hidden = 1 }
for (const a of b) {}
`,
	}
	names := []string{"props", "emit", "count", "total", "names", "doubled", "LIMIT", "LABELS", "flag", "data", "error", "onSave", "format", "load", "form", "plain", "open", "label", "hidden", "missing"}
	probe := `require $argv[1];
use JesseGall\CodeCommandments\Vue\Script;
$in = json_decode($argv[2], true); $out = [];
foreach ($in['scripts'] as $source) {
    $s = new Script($source);
    $reads = ['imports' => array_map(fn ($i) => [$i->names, $i->statement], $s->imports()), 'locals' => $s->localNames(),
        'props' => $s->propsVariable(), 'emit' => $s->emitName(), 'propTypes' => $s->propTypes(),
        'localTypes' => $s->localTypes(['Props', 'Row', 'Item', 'Id']), 'fields' => $s->typeFields('Props'),
        'returned' => $s->inferredReturnFields('load'), 'specifier' => [$s->importSpecifier('Badge'), $s->importSpecifier('useThing'), $s->importSpecifier('utils')]];
    foreach ($in['names'] as $name) {
        $reads['name:' . $name] = [$s->declaredType($name), $s->staticConst($name), $s->destructuredCall($name), $s->returnTypeName($name), $s->declaratorValue($name)];
    }
    $reads['fieldType'] = [$s->fieldType('Props', 'order'), $s->fieldType('Row', 'id'), $s->fieldType('Props', 'nope')];
    $out[] = $reads;
}
echo json_encode($out);`
	input, _ := json.Marshal(map[string]any{"scripts": scripts, "names": names})
	root, _ := filepath.Abs(filepath.Join("..", ".."))
	out, err := exec.Command("php", "-r", probe, filepath.Join(root, "vendor", "autoload.php"), string(input)).Output()
	if err != nil {
		t.Fatal(err)
	}
	var want []map[string]json.RawMessage
	if err := json.Unmarshal(out, &want); err != nil {
		t.Fatal(err)
	}
	for index, source := range scripts {
		s := vue.ReadScript(source)
		orNull := func(value string, ok bool) any {
			if !ok {
				return nil
			}

			return value
		}
		var imports [][]any
		for _, imported := range s.Imports() {
			imports = append(imports, []any{imported.Names, imported.Statement})
		}
		got := map[string]any{
			"imports": orEmpty(imports), "locals": orEmpty(s.LocalNames()), "props": orNull(s.PropsVariable()), "emit": orNull(s.EmitName()),
			"propTypes": fields(s.PropTypes()), "localTypes": fields(s.LocalTypes([]string{"Props", "Row", "Item", "Id"})),
			"fields": fields(s.TypeFields("Props")), "returned": fields(s.InferredReturnFields("load")),
			"specifier": []any{orNull(s.ImportSpecifier("Badge")), orNull(s.ImportSpecifier("useThing")), orNull(s.ImportSpecifier("utils"))},
		}
		for _, name := range names {
			got["name:"+name] = []any{orNull(s.DeclaredType(name)), orNull(s.StaticConst(name)), orNull(s.DestructuredCall(name)), orNull(s.ReturnTypeName(name)), orNull(s.DeclaratorValue(name))}
		}
		for key, ordered := range map[string]typescriptFields{"propTypes": s.PropTypes(), "localTypes": s.LocalTypes([]string{"Props", "Row", "Item", "Id"}), "fields": s.TypeFields("Props"), "returned": s.InferredReturnFields("load")} {
			var order []string
			for _, pair := range ordered.Pairs() {
				order = append(order, pair[0])
			}
			if want := keysInOrder(want[index][key]); !slices.Equal(order, want) {
				t.Errorf("script %d %s order: go %v, php %v", index, key, order, want)
			}
		}
		got["fieldType"] = []any{orNull(s.FieldType("Props", "order")), orNull(s.FieldType("Row", "id")), orNull(s.FieldType("Props", "nope"))}
		for key, value := range got {
			rawGot, _ := json.Marshal(value)
			var normalGot any
			_ = json.Unmarshal(rawGot, &normalGot)
			gotJSON, _ := json.Marshal(normalGot)
			var normal any
			_ = json.Unmarshal(want[index][key], &normal)
			wantJSON, _ := json.Marshal(normal)
			if string(gotJSON) != string(wantJSON) {
				t.Errorf("script %d %s:\n  go  %s\n  php %s", index, key, gotJSON, wantJSON)
			}
		}
	}
}

// fields is a Fields as PHP writes an array keyed by name: an object in order, or an empty list.
func fields(f interface{ Pairs() [][2]string }) any {
	pairs := f.Pairs()
	if len(pairs) == 0 {
		return []any{}
	}
	ordered := make(orderedObject, 0, len(pairs))
	for _, pair := range pairs {
		ordered = append(ordered, pair)
	}

	return ordered
}

type orderedObject [][2]string

func (o orderedObject) MarshalJSON() ([]byte, error) {
	out := []byte{'{'}
	for index, pair := range o {
		if index > 0 {
			out = append(out, ',')
		}
		key, _ := json.Marshal(pair[0])
		value, _ := json.Marshal(pair[1])
		out = append(append(append(out, key...), ':'), value...)
	}

	return append(out, '}'), nil
}

type typescriptFields interface{ Pairs() [][2]string }

// keysInOrder is an object's keys in the order PHP wrote them; none for a list.
func keysInOrder(raw json.RawMessage) []string {
	decoder := json.NewDecoder(bytes.NewReader(raw))
	if token, err := decoder.Token(); err != nil || token != json.Delim('{') {
		return nil
	}
	var keys []string
	for decoder.More() {
		key, _ := decoder.Token()
		keys = append(keys, key.(string))
		var skip json.RawMessage
		_ = decoder.Decode(&skip)
	}

	return keys
}

func orEmpty[T any](values []T) []T {
	if values == nil {
		return []T{}
	}

	return values
}
