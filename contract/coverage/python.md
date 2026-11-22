# Python: what the bridge does not translate yet

Each row is a construct the Python bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `assert` | `unsupported`, construct `assert` | version 5 has no kind for an assertion statement |
| `del` | `unsupported`, construct `delete` | version 5 has no kind for deleting a name or an item |
| a slice `a[1:2]` | `index` whose key is `unsupported`, construct `slice` | version 5 has no kind for a slice |
| `type X = ...` (3.12) | `unsupported`, construct `typealias` | not translated to `alias` yet |
| `//` floor division | `binary` with operator `divide` | version 5 has no floor-division operator |
| `@` matrix multiplication | `binary` with operator `multiply` | version 5 has no matrix-multiplication operator |
| a field's `written` type structure | absent | the bridge writes each annotation as a `type-expression` tree; the `written` fact is not filled |
| a call's `target` | absent | mypy's resolved callee is not joined to the call yet |

## Not owed

| Item | Why |
|---|---|
| `fact/*/modifiers` | Python has no modifier keywords; `async` is a fact, the rest are decorators |
| `fact/access/nullConditional` | Python has no `?.` |
| `fact/call/nullConditional` | Python has no `?.` |
| `fact/index/nullConditional` | Python has no `?.` |
| `fact/argument/passing` | no by-reference, `out` or `in` passing |
| `fact/parameter/passing` | no by-reference, `out` or `in` passing |
| `fact/arm/fallsThrough` | a `case` never runs on into the next |
| `fact/assignment/prefix` | no `++i` or `--i` |
| `fact/break/depth` | `break` and `continue` take no level or label |
| `fact/continue/depth` | `break` and `continue` take no level or label |
| `role/break/label` | `break` and `continue` take no level or label |
| `role/continue/label` | `break` and `continue` take no level or label |
| `fact/directive/shorthand` | Vue markup; a Python file has no template |
| `fact/element/form` | Vue markup; a Python file has no template |
| `fact/element/resolves` | Vue markup; a Python file has no template |
| `fact/element/tag` | Vue markup; a Python file has no template |
| `kind/directive` | Vue markup; a Python file has no template |
| `kind/element` | Vue markup; a Python file has no template |
| `kind/embedded-expression` | Vue markup; a Python file has no template |
| `kind/attribute` | Vue markup; a Python file has no template |
| `role/attribute/value` | Vue markup; a Python file has no template |
| `role/directive/argument` | Vue markup; a Python file has no template |
| `role/directive/bindings` | Vue markup; a Python file has no template |
| `role/directive/iterable` | Vue markup; a Python file has no template |
| `role/directive/pattern` | Vue markup; a Python file has no template |
| `role/directive/value` | Vue markup; a Python file has no template |
| `role/element/attributes` | Vue markup; a Python file has no template |
| `role/element/children` | Vue markup; a Python file has no template |
| `role/embedded-expression/expression` | Vue markup; a Python file has no template |
| `link/directive/body` | Vue markup; a Python file has no template |
| `kind/fallback` | the contract spells `??` and PHP's `?:` as `fallback`; Python's `x or y` is `logical` with operator `or` (CONTRACT.md, "Constructs decided") |
| `fact/fallback/trigger` | the contract spells `??` and PHP's `?:` as `fallback`; Python's `x or y` is `logical` with operator `or` (CONTRACT.md, "Constructs decided") |
| `role/fallback/replacement` | the contract spells `??` and PHP's `?:` as `fallback`; Python's `x or y` is `logical` with operator `or` (CONTRACT.md, "Constructs decided") |
| `role/fallback/subject` | the contract spells `??` and PHP's `?:` as `fallback`; Python's `x or y` is `logical` with operator `or` (CONTRACT.md, "Constructs decided") |
| `fact/import/typeOnly` | no import is type-only (`if TYPE_CHECKING:` is a block, not a flag) |
| `fact/parameter/optional` | TypeScript's `x?`; a Python parameter with a default is `evaluated: definition` |
| `fact/pattern/operator` | Python has no relational pattern such as `> 0` |
| `fact/return/namedResults` | Go's named results |
| `kind/tuple-element` | tuple types for several results are Go's; `tuple[int, str]` is a generic named type with type arguments |
| `kind/tuple-type` | tuple types for several results are Go's; `tuple[int, str]` is a generic named type with type arguments |
| `fact/tuple-element/label` | tuple types for several results are Go's; `tuple[int, str]` is a generic named type with type arguments |
| `role/tuple-element/type` | tuple types for several results are Go's; `tuple[int, str]` is a generic named type with type arguments |
| `role/tuple-type/elements` | tuple types for several results are Go's; `tuple[int, str]` is a generic named type with type arguments |
| `kind/constant` | no constant declaration; `Final` is a checker annotation on a field |
| `role/constant/attributes` | no constant declaration; `Final` is a checker annotation on a field |
| `role/constant/declarators` | no constant declaration; `Final` is a checker annotation on a field |
| `role/constant/declaredType` | no constant declaration; `Final` is a checker annotation on a field |
| `kind/default-value` | C#'s `default` |
| `role/default-value/writtenType` | C#'s `default` |
| `kind/defer` | Go's `defer` |
| `role/defer/call` | Go's `defer` |
| `kind/goto` | no `goto` or labels |
| `kind/label` | no `goto` or labels |
| `link/goto/label` | no `goto` or labels |
| `role/goto/destination` | no `goto` or labels |
| `role/label/binding` | no `goto` or labels |
| `role/label/statement` | no `goto` or labels |
| `kind/select` | Go's `select` |
| `kind/select-arm` | Go's `select` |
| `role/select/arms` | Go's `select` |
| `role/select-arm/body` | Go's `select` |
| `role/select-arm/communication` | Go's `select` |
| `role/call/typeArguments` | a Python call writes no type arguments, no `new T` and no object initializer; `f[int](x)` is an index, then a call |
| `role/construction/type` | a Python call writes no type arguments, no `new T` and no object initializer; `f[int](x)` is an index, then a call |
| `role/construction/initializer` | a Python call writes no type arguments, no `new T` and no object initializer; `f[int](x)` is an index, then a call |
| `role/field/attributes` | a decorator can not be put on a class attribute or a parameter |
| `role/enum-case/attributes` | a decorator can not be put on a class attribute or a parameter |
| `role/parameter/attributes` | a decorator can not be put on a class attribute or a parameter |
| `role/function/captures` | PHP's `use (...)` list; a Python closure captures without a list |
| `role/handler/filter` | C#'s `when` |
| `role/if/initializer` | Go's `if x := f(); …`; a walrus is an expression inside the condition |
| `role/loop/initializer` | no C-style `for` |
| `role/loop/steps` | no C-style `for` |
| `role/type-test/binding` | `isinstance` binds no name (a `case` pattern does, as `pattern.bindings`) |
| `role/type/interfaces` | Python's bases are the one list, `bases`; there are no interfaces or traits apart from them |
| `role/type/mixins` | Python's bases are the one list, `bases`; there are no interfaces or traits apart from them |
| `role/type-expression/element` | no array, pointer or function type syntax; `list[int]` and `Callable[...]` are named types with type arguments |
| `role/type-expression/parameters` | no array, pointer or function type syntax; `list[int]` and `Callable[...]` are named types with type arguments |
| `role/type-expression/returnType` | no array, pointer or function type syntax; `list[int]` and `Callable[...]` are named types with type arguments |
| `link/field/hooks` | PHP and C# property hooks; `@property` is a decorated method |
| `link/type/implicitMembers` | a promoted constructor parameter or a record's positional parameter; a dataclass field is a written class attribute |
| `link/type/satisfies` | Go's implicit interfaces |
| `fact/*/incomplete` | Python's parser refuses a file with a syntax error whole, so there is no partial declaration to mark incomplete |
| `fact/field/optional` | TypeScript's `name?` on a property; a python field always exists, and a missing value is its nullable type |
