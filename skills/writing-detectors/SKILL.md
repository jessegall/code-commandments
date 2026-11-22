---
name: commandments-writing-detectors
description: "How to write a commandment of your OWN — a project-local rule that judges every file from then on. Read this BEFORE writing or changing a rule in `.commandments/custom/`, when the user asks for a new rule/check/detector, when `commandments make` points you here, or when you are about to reach for a regex to inspect code."
---

# Writing your own commandments

> The shipped rules are not the ceiling. A convention you keep restating in review is a rule you
> haven't written yet. Write it once, and it judges every file from then on.

## A rule is a question the engine answers

A rule of your own is a `.json` file in `.commandments/custom/`, in that folder or any folder under it, so a
project with rules for several parts of its code groups them by what they protect (`custom/core/`,
`custom/viewer/`). It is written in the same format as every rule the tool ships, `commandments:neutral-rule/v2`,
and judged by the same engine. A rule is named by its FILE wherever it sits, so moving one into a folder leaves
every mention of it in the config standing, and two files of one name are both refused rather than one of them
quietly winning. A file in an older format is refused, naming the file and the format to rewrite it in.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["php", "python", "go"],
    "sin": {
        "name": "too-many-parameters",
        "skill": "small-signatures",
        "description": "A function declares five parameters or more.",
        "rule": "Give the values that travel together a type of their own, and pass that."
    },
    "find": {
        "select": "function",
        "as": "f",
        "where": [">=", ["count", "$f.parameters"], 5],
        "emit": "$f"
    }
}
```

**`engine`** is the engine the rule judges, or a list of them: `php`, `python`, `csharp`, `typescript`, `vue`,
`go`. **`sin`** names what it finds: its `name` (the `--sin=` id), the `skill` that teaches the fix (your own, or
a shipped one such as `php/absence`), the `description` (the symptom) and the `rule` (the directive the fix
follows).

**`find`** is the question. `select` names the kind of node a match starts from, `as` the name it is bound to,
`where` the check it must pass, and `emit` the node the finding points at. A check is a list whose first item is
an operator and whose other items are its operands: `["=", "$f.name", "handle"]`, `["and", check, check]`,
`["some", list, "x", check]`. `$f.parameters` reads a ROLE of the node bound to `f`, `$f.name` one of its FACTS.

**The kinds** are the generic tree's, the same in every language: `function`, `parameter`, `type`, `field`,
`call`, `construction`, `access`, `assignment`, `if`, `dispatch`, `loop`, `try`, `handler`, `throw`, `return`,
`literal`, `comparison`, `logical`, `fallback`, `conditional`, `import`, `block`, `reference`, `attribute`, and the
rest the contract lists. `["is", "$n", "exit"]` asks for a whole category: `exit`, `choice`, `statement`,
`expression`, `declaration`, `function-like`. `commandments rule explain <file> --line=N` prints the kind, roles
and facts of every node on a line, which is exactly what a `find` can see.

**More than one step.** `let` binds a value once so the checks after it read it by name, and `having` checks
what `let` bound:

```json
"find": {
    "select": "function",
    "as": "f",
    "let": [["depth", ["count", ["filter", ["ownDescendants", "$f"], "d", ["is", "$d", "choice"]]]]],
    "having": [">", "$depth", 10],
    "emit": "$f"
}
```

**Name a check once.** `definitions` holds checks the `find` calls by name, with their arguments:

```json
"definitions": {
    "isTodo": {"args": ["k"], "expr": ["matchesText", "$k.text", "(?i)\\b(TODO|FIXME)\\b"]}
},
"find": {"select": "function", "as": "f", "where": ["some", ["commentsOf", "$f"], "k", ["isTodo", "$k"]], "emit": "$f"}
```

**A rule is built from other rules.** Another rule's definition is called as `<Rule>.<definition>`:
`["ActionClass.isAction", "$t"]` passes when the definition `isAction` of the rule `ActionClass` does. A name
nothing declares and a chain that comes back to itself are refused when the rules are read, before anything is
judged.

**Tune it from the config.** `parameters` declares a value the project may change, with its default, and the
`find` reads it as `$param.<name>`:

```json
"parameters": {"limit": {"type": "number", "default": 5, "description": "How many parameters are too many."}},
"find": {"select": "function", "as": "f", "where": [">=", ["count", "$f.parameters"], "$param.limit"], "emit": "$f"}
```

**A check's operators**, every one there is, generated from the tool itself:

<!-- BEGIN: rule-checks (auto-generated, run `composer sins`) -->
| Operator | Operands | Gives | What it says |
|---|---|---|---|
| `!=` | value, value | truth | whether two values differ; unknown when either is unresolved |
| `<` | number, number | truth | whether the first number is less than the second |
| `<=` | number, number | truth | whether the first number is at most the second |
| `=` | value, value | truth | whether two values are equal; unknown when either is unresolved |
| `>` | number, number | truth | whether the first number is greater than the second |
| `>=` | number, number | truth | whether the first number is at least the second |
| `abs` | number | number | the number without its sign |
| `accessMode` | node | text | whether a reference reads, writes, or reads and writes the name: read, write or readwrite |
| `add` | number, number, … | number | the sum of the numbers |
| `after` | node | list | the nodes after the node in the same role of its parent, in order |
| `ancestors` | node | list | every node above the node, nearest first, up to the file, across functions |
| `and` | truth, truth, … | truth | true when every operand is true; false when one is false; unknown otherwise |
| `annotated` | node, text | list | the attributes, annotations or decorators on a declaration that name the symbol given, by what they resolve to |
| `append` | list, list, … | list | the lists one after another |
| `assignable` | value, text | truth | whether a resolved type, a node's type or a type named by its symbol is assignable to the type named; unknown when it is unresolved |
| `assignedOnce` | node | node | the value the local a reference names is given, when its function gives it exactly one; none when it gives it none or several |
| `at` | list, number | value | the item of a list at a position, counted from 0; no value past its end |
| `average` | list | number | the mean of a list of numbers; unknown for an empty one |
| `baseName` | text | text | a path's last segment without its extension: config/app.php reads app |
| `before` | node | list | the siblings ahead of the node, in order |
| `between` | number, number, number | truth | whether a number lies from the low to the high one, both included |
| `buildsText` | node | truth | whether an expression writes text on the spot: a text literal, an interpolation, or a joining or format call with a text part |
| `carriesCause` | node | truth | whether a throw inside a handler carries the failure it caught: a rethrow, a raise from, or a new failure built from the caught one |
| `case` | value, arms, value | value | the arm the value names, evaluated alone; the last operand when no arm names it |
| `caseStyle` | text | text | how a name is written: camel, pascal, snake, screaming-snake, kebab, lower, upper or mixed; a one-word lower-case name is lower |
| `catchesAll` | node | truth | whether a handler catches every failure: a catch-all, or one whose caught type is the language's root failure type |
| `ceil` | number | number | the smallest whole number not below it |
| `childToward` | node, node | node | the node the ancestor holds directly on the way down to the node, whose role says which arm it lies in; none when the first is not above the second |
| `children` | node | list | every node the node holds directly, in source order |
| `childrenIn` | node, text | list | the nodes the role named holds; unknown for a role the node's kind does not have, or one the parser recovered from |
| `cleanPath` | list | text | the segments joined into one path by slashes |
| `coalesce` | value, value, … | value | the first value that is known and not absent; unknown when none is |
| `commentsOf` | node | list | the comments of a node, the ones above it and inside it, each a comment record as select comment reads them |
| `commonAncestor` | node, node | node | the nearest node that holds both nodes, or is one of them; unknown for nodes of two files |
| `commonPrefix` | list | text | the words every name of a list starts with, joined in lower case by spaces; empty when they share none |
| `completes` | node | truth | whether control can leave a statement by running off its end: false when every path through it returns, throws, breaks, continues or jumps, and for a loop that never ends without a break; unknown under a node the parser recovered from |
| `complexity` | node, text | number | the node's cyclomatic complexity (one plus each decision: if, loop, case, handler, ternary, and, or) or cognitive complexity (each break in the flow, weighed by how deeply it is nested) |
| `components` | list | list | the cycles of a graph of edges, each a list of the values that reach one another, a value alone only when it has an edge to itself |
| `concat` | text, text, … | text | the texts written one after another |
| `constant` | node | value | the value a literal writes or the compiler folded an expression to; unknown where neither says |
| `consumer` | node | value | how an expression's value is used, as a record of use and node: discarded, returned, assigned, argument, receiver, null-safe-receiver, called, condition, compared-to-nothing, coalesced, asserted-present, thrown, yielded or operand |
| `contains` | text, text | truth | whether a text holds another |
| `contentWords` | text | list | the stems of a text's content words, the small words left out |
| `count` | list | number | how many items a list holds; unknown when the list is |
| `countOf` | text, value | number | how many rows of one of the find's relations have fields equal to a record's |
| `declarationBySymbol` | text | node | the project's declaration, of any kind, whose symbol is the text; none when the project declares none |
| `declarationOf` | node | node | the declaration a call's target, a reference or an access's member resolves to, or the local binding a name is linked to; unknown when neither is known |
| `declarationsOf` | value | list | every declaration of a symbol, or of what a node names: partial classes and overloads each once |
| `declaredIn` | node | list | the bindings written directly in a node, not inside a block, function, type, loop, handler or comprehension written in it |
| `defaultOf` | node | node | the value an expression falls back to when its subject is absent: the right side of ?? or ?:, the default a library's lookup is given; no node when it has none |
| `descendants` | node, text | list | every node under the node in source order, into the functions and types written inside it, only those of the kind or category named when one is; unknown under a node the parser recovered from |
| `diff` | node, node, text | list | the pairs of corresponding nodes where two subtrees differ in the fingerprint mode named, the shallowest first, each a record of left and right |
| `difference` | list, list | list | the items of the first list the second does not hold; unknown when an item of either is |
| `dirName` | text | text | a path without its last segment |
| `disjoint` | list, list | truth | whether no item of the first list is in the second |
| `dispatchArms` | node | list | the arms of a choice, each a record of tests, body and isDefault: a dispatch's arms, or an if with each of its else-ifs and its final else |
| `distance` | text, text | number | how many characters must be added, removed or changed to turn one text into the other |
| `distinct` | list | list | the first of each equal item, in order |
| `distinctBy` | list, name, value | list | the first item of each key the body gives, in order; unknown when a key is |
| `distinctCount` | list | number | how many different values a list holds; unknown when one of them is |
| `divide` | number, number | number | the first number divided by the second; unknown for a zero divisor |
| `docTags` | node | list | the tags of the doc comments that document the node: name, text, type and subject of each |
| `dominant` | list, number | value | the value at least the share given of a list's items hold; unknown when none does |
| `dominates` | node, node | truth | whether every path to the second node passes the first: the first runs on every path through what holds both, before the part that holds the second; unknown for nodes of two functions |
| `drop` | list, number | list | the list without its first n items |
| `elementType` | value | value | the type of what a list, set, array, iterator or map holds; no value for a type that holds nothing |
| `emptyValue` | node, text | truth | whether a literal is empty in the sense named: nothing (null or undefined), blank-string, zero, false, empty-collection, or empty-scalar (any of the blank string, zero and false) |
| `encloses` | text, text | truth | whether the first module holds the second: the second goes on from the first past a separator |
| `enclosing` | node, text, text | node | the nearest node above that is of the kind or category named, across functions; none when nothing above is, or when a node of the stop kind or category comes first |
| `endsWith` | text, text | truth | whether the text ends with the part |
| `equivalent` | node, node, text | truth | whether two subtrees are the same in the fingerprint mode named |
| `every` | list, name, truth | truth | whether the body is true for every item |
| `exclusive` | node, node | truth | whether no run executes both nodes: they lie in different arms of one if, ternary or dispatch whose arms do not fall through, or in two handlers of one try |
| `exemptedBy` | text, node | truth | whether a library the project holds excuses the node under the exemption tag named, by its resolved symbol |
| `exists` | value | truth | whether a role, link or fact holds something |
| `exitsOf` | node | list | the returns and throws under a node, and the breaks, continues and gotos under it that leave it, its own functions left out |
| `extension` | text | text | the part of a path's last segment after its last dot, without the dot; empty when there is none |
| `failureType` | value, text | truth | whether a type is one of the language's failure types of the class named: root (Throwable, BaseException, System.Exception, Error) or generic (Exception, RuntimeError, ApplicationException and the like); unknown for a type the compiler did not resolve |
| `fieldType` | value, text | value | the type of the field named; no value when there is no such field |
| `fieldsOf` | value | list | the fields of an object type or a type declaration, each a record of name, type and optional |
| `filter` | list, name, truth | list | the items for which the body, with the name bound to each, is true |
| `fingerprint` | node, text, list | text | a subtree's structure hashed, in a mode: exact, normalized, renamed, shape, calls, signature or text; with the options literals (a literal read as its kind) and commutative (operands of an order-free operator sorted); unknown where a mode cannot be taken |
| `first` | list | value | the first item of a list; no value for an empty one |
| `flatten` | list | list | the items of a list of lists, as one list |
| `floor` | number | number | the largest whole number not above it |
| `follow` | node, text, text | list | the node and each node its role or link holds in turn, while it is of the kind or category named when one is: an if with its else-ifs, a call with its receivers |
| `freeVariables` | node | list | the bindings a function reads or writes that are declared outside it, each once |
| `functionRole` | node | list | the roles a function plays: constructor, named-constructor, getter, setter, override, test, sole-statement |
| `gap` | node, node | number | how many blank lines lie between the end of the first node and the start of the second; unknown for nodes of two files |
| `get` | value, text | value | a record's field by its name; none when it has no such field |
| `groupItems` | list, name, value | list | the items grouped by the key the body gives, each group a record of key and items, in the order each key first appears; unknown when a key is |
| `has` | list, value | truth | whether a list holds a value equal to the one given |
| `hasEffect` | node, text | truth | whether the library a call's resolved target belongs to states the effect named for it, at the version the project's lock file holds; unknown when the target or the version is. The effects are those the package files under rules/packages state: executes-query, database-read, deferred-query, mutates-receiver, iterates-receiver, builds-mutable, fresh-every-call, live-view, tests-membership, decodes-untyped, cast-result-type, blocks-thread, blocks-on-task, completed-task, runs-on-thread, reads-with-default, reads-key and gives-empty-collection |
| `hasField` | value, text | truth | whether a type has the field named |
| `hasRow` | text, value | truth | whether one of the find's relations holds a row whose fields equal a record's |
| `hasStartingWith` | list, text | truth | whether a list holds a text that begins with the one given |
| `height` | node | number | how many nodes lie on the longest path down from the node to a leaf, the node included |
| `identities` | text | list | every type or member name the libraries list under the identity given; empty when none does |
| `identity` | text | text | the type a library names by the identity given, such as "option"; unknown when no library names it |
| `in` | value, value, … | truth | whether the first value equals any of the values written after it; for a list held in a value, use has |
| `inCycle` | list, value | truth | whether a value lies on a cycle of the edges |
| `indexOf` | value, value | number | the position of the first item equal to the value, or of the first place a text holds the part; no value when there is none |
| `intersect` | list, list | list | the items of the first list the second holds too |
| `is` | node, text | truth | whether a node is of the kind, or in the category, named |
| `isCase` | text, text | truth | whether a name is written in the style named, a one-word name counting where the style allows it |
| `isCollection` | value | truth | whether a type's category is list, set, map, tuple or iterator |
| `isConstant` | node | truth | whether a value is fixed before the program runs: a literal, a folded expression, or a reference to an enum case or a constant; false only where the compiler ran and folded nothing |
| `isElseIf` | node | truth | whether an if continues another as its else-if: else if, elif, elseif |
| `isNullable` | value | truth | whether a type admits absence: a nullable type, a union holding null, or one the compiler states is nullable; false only where the compiler says so |
| `isOdd` | number | truth | whether a whole number is odd |
| `isSelf` | node | truth | whether a node is the instance the method runs on: this, self, $this, or a Go method's receiver |
| `isSubtype` | value, value | truth | whether the first type is the second or reaches it through what it extends, implements or mixes in; unknown past a type the codebase does not declare |
| `isUnknown` | value | truth | whether the value is one the tree cannot state; always known |
| `jaccard` | list, list | number | how many items two lists share against how many they hold together, from 0 to 1 |
| `join` | list, text | text | the texts of a list written one after another with the separator between each two |
| `keyType` | value | value | the type of a map's keys; no value for a type that is no map |
| `keyedStoreRead` | node | value | the store and key an index or a library's keyed read reads, as a record of store and key; no value for another expression |
| `keys` | value | list | the field names of a record, such as a map parameter, sorted |
| `knownSupertypes` | value | list | the symbols of every type a type declaration extends, implements or mixes in, nearest first, as far as the codebase declares them: a type it does not declare is listed and not walked past |
| `last` | list | value | the last item of a list; no value for an empty one |
| `layerOf` | text | text | the layer the project's configuration places a module in; unknown for a module in no layer |
| `leaves` | node, text, text | list | what a run of nested nodes of the kind named holds that is not of that kind, only through the role named when one is: the parts of a && b && c |
| `length` | text | number | how many characters a text holds |
| `lexLess` | text, text | truth | whether the first text sorts before the second |
| `lineCount` | node | number | how many lines the node spans |
| `lines` | text | list | each line of the text |
| `list` | value, … | list | the values given, as one list |
| `literalEquals` | value, value | truth | whether a node is a literal whose value is the value given |
| `literalIs` | value, text | truth | whether a node is a literal of the kind named: null, undefined, boolean, integer, float, string |
| `literalValue` | node | value | the value a wholly literal expression writes: a literal's value, a list for a list literal, a record for a keyed one; unknown where any part is computed |
| `lower` | text | text | the text in lower case |
| `map` | list, name, value | list | the body's value for each item, with the name bound to it |
| `matchesShape` | node, arms | value | the captures of a pattern the node matches, as a record; no value when it does not match. A pattern names a kind and the roles and facts it requires: a nested pattern for a role holding one node, positions for a role holding many, ?name to capture what is there (twice: the same subtree twice), _ for anything |
| `matchesText` | text, text | truth | whether the text matches a pattern, written in Go's regular expressions and checked when the rule loads |
| `max` | value, … | number | the largest of a list of numbers, or of the numbers given |
| `maxBy` | list, name, number | value | the item whose body gives the largest number; no value for an empty list |
| `median` | list | number | the middle of a list of numbers, the mean of the two middle ones for an even count; unknown for an empty one |
| `memberGroup` | node | text | the group a member belongs to in a type's layout: enum-case, constant, static-field, field-public, field-protected, field-private, field-hooked, constructor or method |
| `membershipTest` | node | value | what a membership test compares, as a record of subject and candidates: in, a library's contains or includes, case a, b:, and an or of equalities over one subject; no value for another expression, unknown where a candidate is computed |
| `min` | value, … | number | the smallest of a list of numbers, or of the numbers given |
| `minBy` | list, name, number | value | the item whose body gives the smallest number; no value for an empty list |
| `moduleOf` | value | text | the module of a node, or of the declaration a symbol names: its namespace, package or folder; no value for a symbol the project does not declare |
| `mood` | text | text | what the prose package reads a name's leading word as: imperative, third-person, question, state or non-entity; unknown for a word it does not know |
| `multiply` | number, number, … | number | the product of the numbers |
| `next` | node | node | the node right after the node in the same role of its parent; none at the end |
| `none` | list, name, truth | truth | whether the body is true for no item |
| `nonempty` | value | truth | whether a value holds something: a non-empty text, list or node |
| `not` | truth | truth | the operand's truth turned over; unknown stays unknown |
| `nullForgiving` | node | truth | whether an expression only tells the checker a value is there: x! or a TypeScript assertion cast |
| `occurs` | node, node, text | truth | whether a subtree the same as the second, in the fingerprint mode named, lies under the first, the second itself left out |
| `or` | truth, truth, … | truth | true when one operand is true; false when every operand is false; unknown otherwise |
| `origin` | value | text | where a node's declaration lies: project, library or unknown |
| `originRank` | value | number | how much a type's origin is to be trusted: compiler 4, written 3, attribute 2, documented 1, inferred 0 |
| `overriders` | node | list | every function in the program whose obligations name the function: the members that override or implement it |
| `ownAncestors` | node | list | every node above the node, nearest first, up to the function it is written in |
| `ownDescendants` | node, text | list | every node under the node, leaving out what a function, type or comprehension written inside it holds, only those of the kind or category named when one is; empty for no node, unknown for a node the parser recovered from |
| `pair` | value, value | list | the two values as one, to compare or sort together |
| `parameterOf` | node | node | the parameter of the called declaration an argument binds to, by its label or its position; unknown where the call's target is not resolved |
| `pathConditions` | node | list | the conditions that must hold for a node to run, each a record of condition and holds (true or false): the if, else, loop, ternary, and and or around it, and each guard before it that returns when its condition holds; unknown in a function with a goto |
| `pathFits` | text, text | truth | whether a path fits a route template, a {name} segment standing for any one segment |
| `pathSegments` | text | list | the segments of a path, . left out and .. taking the segment before it away |
| `paths` | value, text | list | the path of every value a record holds nested, each key joined by the separator: app.name for {app: {name: …}} |
| `position` | node | number | the node's place among the nodes in the same role of its parent, counted from 0 |
| `possible` | truth | truth | true unless the truth is proven false: unknown reads as true, the opposite of proven |
| `precedes` | node, node | truth | whether on some path the first node runs before the second: written before it and not in another arm of one choice, or both inside one loop; unknown for nodes of two functions |
| `prefixes` | text, text | list | each part of a text that ends before one of its separators, shortest first: a.b.c with . reads a and a.b |
| `previous` | node | node | the node right before the node in the same role of its parent; none at the start |
| `programEnums` |  | list | the case values of every enum the program declares, one list per enum of two or more cases |
| `programTexts` |  | list | the value of every string literal the program writes, once each |
| `proven` | truth | truth | true only when the truth is proven true: an unknown reads as false, for an exclusion that must not hide what it cannot prove |
| `range` | number, number | list | the whole numbers from the first up to the second, both included |
| `reachable` | node | truth | whether some path from the start of its function reaches the node: false after a statement that never completes, and in the arm of an if whose condition is a literal that rules it out |
| `reachableFrom` | list, value, number | list | every value the edges lead to from the one given, in at most the steps given (0 for no limit), nearest first |
| `reached` | node | list | the symbols a function or type calls, constructs and names, each once: the resources it reaches |
| `reaches` | list, value, value | truth | whether edges, each a record of from and to, lead from the first value to the second |
| `reads` | text, text | truth | whether a name or text reads as the judgement named: third-person, question, bare-state, bare-verb, relational-compound, history, strawman, fixture-marker, non-entity (a word that names no thing of its own) |
| `receiverChain` | node | list | the links of a chain a.b().c, the root first, each a record of node, member, owner (the type declaring the member) and nullConditional |
| `record` | value, value, … | value | a record of the fields named and their values, written name, value, name, value |
| `referrers` | node | list | every node in the program that refers to the declaration: its calls and reads |
| `remainder` | number, number | number | what is left of the first number after dividing it by the second; unknown for a zero divisor |
| `replace` | text, text, text | text | the text with every occurrence of the second replaced by the third |
| `reportable` | node | truth | whether a finding on the node can be reported: it is in code the project can change |
| `rest` | list | list | the list without its first item |
| `reusableInterface` | node | value | the contract an interface could reuse instead: one of its own language with no signature it lacks, more than half of its signatures, that it may depend on under the project's layers, as a record of contract and exact (whether the signatures are the same); no value when there is none |
| `reverse` | list | list | the list's items, last first |
| `round` | number | number | the nearest whole number, a half rounded away from zero |
| `rowOf` | text, value | value | the one row of a relation whose fields equal a record's; unknown when none or several do |
| `rowsOf` | text, value | list | the rows of one of the find's relations whose fields equal a record's: every row when no record is given |
| `sameItems` | list, list | truth | whether two lists hold the same items, order and repeats aside |
| `sameModule` | value, value | truth | whether two nodes or symbols lie in one module |
| `sameSymbol` | value, value | truth | whether two nodes or symbols name the same declaration: for local names by the binding they reach, never by spelling |
| `sameType` | value, value | truth | whether two types are the same by their shape, however each is spelled and wherever it came from |
| `selfMember` | node | node | the member declaration a node reads or writes through the instance the method runs on; none when it reads none, unknown when the member is not resolved |
| `siblings` | node | list | every node in the same role of the node's parent, the node too, in order; empty for no node, unknown under a parent the parser recovered from |
| `similarity` | node, node | number | how much of the larger subtree a common run of the kinds and roles of both matches, from 0 to 1 |
| `slice` | value, number, number | value | the items or characters from the first position up to the second, a negative one counted from the end, positions past the end clamped |
| `snakeCase` | text | text | a camelCase name in snake_case: postalCode reads postal_code |
| `some` | list, name, truth | truth | whether the body is true for at least one item |
| `sort` | list | list | the list's items in order: numbers by size, anything else by its written form |
| `sortBy` | list, name, value | list | the items ordered by the key the body gives: numbers by size, anything else by its written form |
| `spelledFolder` | value | value | the folder a path expression or an argument writes, or a list of them joined by slashes: a record of base (the written text of the computed parts it starts from, empty when it starts with text), start (the first of those parts) and folder (the segments written up to the next computed part, the last one only when a slash follows it); no value when the text does not read as a relative path |
| `split` | text, text | list | the pieces of the text between each separator |
| `startsWith` | text, text | truth | whether a text begins with another |
| `stem` | text | text | the stem the prose package compares a word by |
| `stripPrefix` | text, text | text | the text without the part at its start; unchanged when it does not start with it |
| `stripSuffix` | text, text | text | the text without the part at its end; unchanged when it does not end with it |
| `subset` | list, list | truth | whether every item of the first list is in the second, order and repeats aside |
| `subtract` | number, number | number | the first number less the second |
| `subtypes` | value | list | every type declaration of the codebase that extends, implements or mixes in the type, at any depth |
| `sum` | list | number | the sum of a list of numbers |
| `supertypes` | value | list | the symbols of every type a type declaration extends, implements or mixes in, nearest first, through the declarations the codebase holds |
| `symbolOf` | node | text | the symbol a node is or names: its own symbol, what it refers to, or its call's target; unknown where none is resolved |
| `syntaxWeight` | node | number | how many nodes a subtree holds, no-op statements left out |
| `take` | list, number | list | the first n items of a list |
| `testsType` | node | value | what a type test tests, as a record of subject, types and negated: instanceof, is, isinstance, a type switch's case; no value for another expression |
| `text` | value | text | a text, number, truth or no value written as text: 3 reads "3", for building a message |
| `toCase` | text, text | text | a name written again in the style named: camel, pascal, snake, screaming-snake or kebab |
| `toNumber` | text | number | a number written as text, in decimal, hexadecimal, octal or binary, with digit separators; unknown for text that is no number |
| `trailingCount` | list, name, truth | number | how many items at the end of the list the body holds for, back to the first it does not |
| `trim` | text | text | the text without the white space around it |
| `typeArgument` | value, number | value | the type argument at a position, counted from 0; no value past the end |
| `typeArguments` | value | list | the type arguments of a generic type, in order; empty for one with none |
| `typeCategory` | value | text | the neutral category of a type, the same in every language: text, integer, float, boolean, null, void, never, any, bytes, list, set, map, tuple, function, iterator, object or named; unknown for an unresolved or opaque type |
| `typeDeclaration` | text | node | the project's declaration of the type a symbol or resolved name names; none when the project declares no such type |
| `typeKind` | node | text | what a type declaration declares: class, interface, enum, record, struct, trait, protocol, object or alias |
| `typeMembers` | value | list | the alternatives of a union or intersection, nested ones flattened, a nullable type as its type and null; the type itself for any other |
| `typeOf` | node | value | a node's type: an expression's resolved type, a declaration's declared type, a function's return type; unknown where the compiler gave none |
| `union` | list, list | list | every item of either list once, in order: values by equality, nodes by identity |
| `upper` | text | text | the text in upper case |
| `valueKind` | value | text | what a known value is: text, number, truth, list, node, record, type or null |
| `versionAtLeast` | text, text | truth | whether a version is the one given or later: 10.2.1 is at least 10.2 |
| `width` | node | number | how many bytes the longest line the node spans holds |
| `windows` | list, number | list | every run of n items in a row, each a list |
| `within` | node, node | truth | whether the first node is the second or lies under it |
| `withinModule` | text, text | truth | whether a module is the one named or inside it, whatever separator the language spells modules with |
| `words` | text | list | the words of a name in lower case, split at camel case, snake case, kebab case and acronyms: HTTPServerError reads http, server, error |
| `writtenPath` | node | value | the path a text literal, or a joining of text literals, writes, as a record of root (a slash or nothing) and segments; unknown where a part is computed |
| `zip` | list, list | list | each item paired with the item at its position in the other list, as a record of left and right, for as long as the shorter list |
<!-- END: rule-checks -->

**A list of words, named once.** Several rules often check against the same words, and editing that list in four
files is how the fifth word ends up in three of them. Declare it once in a `lists.json` under
`.commandments/custom/` — the one file name there that is not a rule, read wherever it sits — and name it where
the words would go:

```json
{ "poetry": ["ephemeral", "sublime", "liminal"] }
```

```json
["in", "$f.name", "${poetry}"]
["matchesText", "$f.name", "(?i)^(${poetry})$"]
```

An item that is exactly `${name}` is the whole list, word by word; inside a pattern each word is quoted, so a dot
is a dot. A rule naming a list the project has not declared is refused by name, with the lists it does have,
rather than running against nothing and passing everything.

**Unknown is not false.** A fact the compiler could not give (a type it did not resolve, a declaration outside the
scan) reads as unknown, and a check over it is unknown too: `and` is false only when one part is false, `not`
of unknown is unknown, and a finding needs its `where` to be TRUE. So a rule never fires on what the tool could
not see.

**Paths** — `$n.file.path` — are read from the folder the scan was pointed at, its own name included
(`src/Cart.php` for `judge src`), never from where the project sits on the disk. `$n.file.test` is true in a
file the language's test runner collects: PHP's `*Test.php`, Python's `test_*.py`, `*_test.py` and
`conftest.py`, a `*.test.*` or `*.spec.*` script, Go's `*_test.go`, a C# test project's file, and in every
language a file under a `tests` or `test` folder.

A regular expression is Go's (RE2) and matches anywhere unless it is anchored. A rule the binary cannot run says
why when `judge` starts — the file, and which part is wrong.

## Scaffold it — don't hand-write the plumbing

```bash
vendor/bin/commandments make NoRawSql                    # a PHP rule and a skill of its own
vendor/bin/commandments make NoRawSql --engine=python     # another engine
vendor/bin/commandments make NoRawSql --skill=absence     # taught by a skill that already exists
vendor/bin/commandments make NoDebug --from=no-debug-calls # start from a ready rule and adapt it
```

The ready rules are `no-debug-calls`, `no-todo-comments`, `max-parameters`, `max-function-length`,
`max-nesting` and `no-sql-in-controllers`, each written for every engine it makes sense in. One is a
starting point, not a verdict: read its query, change the numbers and names to the project's, and prove
it on samples of your own.

That writes `NoRawSqlDetector.json` and, when the skill is new, `skills/no-raw-sql/SKILL.md` into
`.commandments/custom/`, turns the rule on under `detectors` in `.commandments/config.json`, and prints
the rest of the process. The folder is kept out of the `.commandments/` gitignore: they are your rules,
so commit them.

## Let one rule into a folder the project excludes

An excluded folder is excluded for every rule, which is usually right and sometimes exactly wrong: a project
excludes `tests` because the rules about naming read a test's name as a sentence, which is what a test name is,
and one project measured 9,773 findings in 85 files that way. But a rule written ABOUT tests belongs there.

**Write it in the rule's own file.** The folders a rule of yours judges belong with the rule, in the file you
wrote and no install rewrites:

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": "python",
    "judges": ["tests"],
    "sin": {"name": "tests-drive-the-real-path", "skill": "tests-drive-the-real-path"},
    "find": { "...": "..." }
}
```

A shipped rule carries no such list, because you could not change one inside a rule the package ships. Name its
folders in the config instead, under that one rule, beside its other `configure` steps:

```json
"configure": {"php/FacadeCallDetector": [{"judges": ["tests"]}]}
```

The two are read as one answer, so a folder named in both counts once, and `judges` is the config's own step
rather than part of the rule file, which is why it reads the same under a rule of yours (keyed by its bare
name) as under a shipped one (keyed `engine/Name`).

The folder stays excluded for every other rule. An edit inside it raises that rule's marks live, as any other
edit does, instead of waiting for somebody to run a judge over the folder by hand. A run GIVEN a path is never
widened by it: a whitelist says which rule may look inside an excluded folder, not that a run should reach past
what it was asked for. A folder you merely left out of `paths`, rather than naming under `exclude`, is reached
the same way.

They are named as yours wherever they fire: `judge` prints `[NoRawSqlDetector (custom)]` in the
console and in `sins.md`, `judge --list` lists them so, and `report --detector` refuses them — nothing
upstream can fix a rule the package does not ship.

## The anatomy — a rule, a sin, a skill

**The skill teaches.** `skills/<slug>/SKILL.md` is what a finding sends the reader to, published as you
write it into the project's skill library as `commandments-<slug>`. Its front matter says when to load
it (`description`), what the briefing lists (`summary`), how often it comes up (`tier: mandatory` or
`keep-in-mind`) and which languages it teaches (`languages: [php]`).

**The sin names.** Inside the rule: a `name` (the `--sin=` id), the `skill` it points at by slug — your
own, or a shipped one such as `php/absence` — the `description` (the symptom) and the `rule` (the
positive directive the fix follows).

**The find finds.** Nothing else. It has no fix logic, and it knows nothing about the report.

## Classify by what the code IS, never by what it is CALLED

The cardinal rule. Derive the answer from what a node is and what its name resolves to — its kind, its role
in its parent, the type it has, the declaration a call reaches. **Never** from a class, method or variable
name, a suffix, or a hardcoded list. `$n.name` and `matchesText` exist because a call has to be named at some
point; reach for them last, and never as a list of the exceptions you happened to meet. Names lie, and a rule
built on one fires on the wrong code the first time somebody renames well.

## Ask about the TYPE, not the name it was reached by

A name tells you what a reader typed; a type tells you what the value IS. The difference shows the moment
someone copies the value into a variable:

```json
"find": {
    "select": "access",
    "as": "a",
    "where": ["and",
        ["matchesText", "$a.member.name", "^_"],
        ["proven", ["isSubtype", ["typeOf", "$a.subject"], "shell.ShellLine"]],
        ["not", ["proven", ["=", "$a.subject.form", "self"]]]],
    "emit": "$a"
}
```

That finds `line._source_line` AND `copied = line; copied._source_line`, because the receiver's type follows
the copy. Written with the variable's name instead, the second one walks straight past. **Leave out the owner
reading its own state**, as above: without that, the rule fires inside the class whose field it is, which is the
one place the read is nobody else's business.

`typeOf` reads the type the compiler resolved; `declarationOf` the declaration a call or a name reaches;
`knownSupertypes` and `isSubtype` what a type extends or implements, as far as the scan declares it. Where the
compiler resolved nothing they answer unknown; `proven` turns unknown into false, so a check you mean as "is
known to be" says so.

## One rule, every language it fits

`engine` takes a LIST, so a rule that asks only what every language answers is written once:

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["python", "go", "csharp", "php"],
    "sin": {"name": "loop-wrapped-in-if", "skill": "flat-loops", "description": "…"},
    "find": {
        "select": "loop",
        "as": "l",
        "where": ["=", ["count", "$l.body.statements"], 1],
        "let": [["only", ["at", "$l.body.statements", 0]]],
        "having": ["=", "$only.kind", "if"],
        "emit": "$l"
    }
}
```

That works because every language writes the same tree: a loop's `body` is a `block` whose `statements` are
its statements, whether the language writes braces, an indent or `end`. Where the languages genuinely differ, a
`case` over `$n.file.language` reads the one fact that differs, and never a whole second rule.

## See what a rule reads before you write it

A `find` names what the tree holds, so read the tree first:

```bash
vendor/bin/commandments rule explain app/Http/OrderController.php --line=42
```

prints every node starting on line 42 — its kind, the role it holds in its parent, its name, its text and what
it resolves to — which is exactly what `select` and each check can see. Then watch the rule while you write it,
without turning it on:

```bash
vendor/bin/commandments rule try NoRawSql app/
```

`--work` with `try` prints how many nodes and checks the rule cost, and `rule schema` prints the JSON Schema
an editor checks a rule file by.

## Prove it fires — a rule nobody has watched is a guess

A rule that parses is not a rule that works. **Write samples**: files under
`.commandments/custom/samples/` holding every form you mean to catch **and the near-misses you must NOT
catch**, each marked by a comment above it:

```php
// .commandments/custom/samples/RawSql.php
// @sin NoRawSqlDetector
DB::select('select * from orders');
// @sin NoRawSqlDetector
DB::unprepared($sql);
// @righteous NoRawSqlDetector
Order::query()->where('id', $id)->get();
// @righteous NoRawSqlDetector
$this->select($columns);
```

A rule whose finding is about a file itself, such as one selecting `file` or reading only `$n.file.path`,
reports on the file's first line, which no leading comment can mark. Mark that
sample with `@sin-file NoFormOutsideFormsDetector` anywhere in the file (`@righteous-file` and `@fixed-file`
likewise): it marks the whole file, wherever the comment stands.

**A comment marks the LINE below it, not the declaration it sits above.** This is the one trap that costs an
afternoon, because it reads as something else entirely. If the rule flags a member read inside a method, the
mark goes directly above THAT line:

```python
def through(tool: Tool) -> str:
    copied = tool.shell

    # @sin NoWholeLineRead
    return copied._source_line
```

Put the mark above the `def` instead and both halves of one mistake are reported as if they were two faults:
the mark is missed where it stands, and the finding is unmarked where it is. `rule prove` now says so when it
sees both in one file, but the mistake is easy and the reason is worth knowing: a sample marks a line in
every language, PHP included.

**A sample is judged by your other rules too.** It sits in the project, so a sample written to prove one rule
can be flagged by another, and then `rule prove` fails that other rule on code you wrote as a fixture. Write
samples that would pass review, or mark them `@righteous` for the rules they trip.

```bash
vendor/bin/commandments rule prove
```

holds every rule to its marks: it fails a rule that misses a `@sin`, flags a `@righteous` twin or a
`@fixed` fix, or flags anything unmarked. The samples stay — they are the rule's spec, and `rule prove`
runs again whenever the rule changes. A rule no sample marks with `@sin` fails too — a rule shown to flag
nothing has been shown nothing — and so does a mark naming no rule. A check that reads across files —
`referrers`, `layerOf`, a pipeline over the program's `calls` or `imports` — sees only the samples there, so
prove it on samples that hold both ends, and calibrate it on the real tree. The near-misses are the whole point: a rule that fires on
everything is not a rule.

## Calibrate on real code — before you trust it

A green probe proves it *can* fire; it does not prove it is *right*. Run it over your actual source —
scoped with `--changes` or `--branch` — and **read the hits by eye**, judging each **against the skill**,
never against what the code happens to do today.

- **Volume proves nothing.** 400 hits can be 400 real sins. A widespread pattern is not "convention"
  that excuses a finding. Do not soften a rule because it fires a lot.
- **Only a genuine false positive invalidates it** — code that is *correct under your architecture* yet
  gets flagged. Then tighten with a **principled check** (on what the code is), never a name
  list.
- **Some ideas die here.** If no check separates the sin from a legitimately valid look-alike — if the
  difference is only the author's intent — the rule is not viable. Cut it. That is a successful
  outcome, not a failure.

## The cadence, in order

1. **Load this skill** — before writing a line.
2. `commandments make <Name>` — scaffold the rule, and the skill when it is new.
3. **Write the teaching first.** If you can't state what good looks like, the rule doesn't know what
   it's looking for either.
4. Name the sin: the symptom, and the rule as a positive directive.
5. Write the `find` — a kind to select, then one check per line of its `where` — reading the tree with
   `rule explain` and watching it with `rule try`.
6. **Prove it.** Samples marking every form you mean to catch and the near-misses, then `rule prove`.
7. **Calibrate** on real code. Tighten, or cut.
8. `vendor/bin/commandments sync` — publishes your skill so the agent can load what the finding points
   at.

## Reference

<!-- BEGIN: commands:make,rule (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments make <Name>` | scaffold a PHP commandment and turn it on |
| `commandments make <Name> --engine=python` | scaffold a Python one instead |
| `commandments make <Name> --engine=php,python,go` | scaffold one rule that judges each engine named |
| `commandments make <Name> --engine=php,vue,typescript,python,csharp,go` | scaffold one that judges every language |
| `commandments make <Name> --skill=NAME` | point the sin at an EXISTING skill (shipped or your own) instead of writing a new one |
| `commandments make <Name> --from=<template>` | start from a ready rule to adapt: max-function-length, max-nesting, max-parameters, no-debug-calls, no-sql-in-controllers, no-todo-comments |
| `commandments rule explain <file> [--line=N]` | print the generic tree a rule reads in the file: every node's line, the role it holds in its parent, its kind, form, name, text and what it resolves to |
| `commandments rule try <Rule> <path>…` | run one rule over the paths without turning it on, and print each match with its line: a folder, or the files a change touched |
| `commandments rule prove [path]` | check every rule of the project flags exactly the code its samples mark, and nothing else |
| `commandments rule prove --all [path]` | check every shipped rule and every rule of the project against each fixture project under the path (tests/Fixtures by default): what each missed, flagged unmarked, flagged as righteous and flagged as fixed |
| `commandments rule trace <Rule> <file> --line=N [--lines=A-B] [--within=folder]` | ask a rule of every node it selects on that line, or in that range of lines (where only the nodes its where lets through are shown), and print each part of its where, let and having with what it answered, the folder read whole as its own project (the file's own folder by default) |
| `commandments rule schema` | print the JSON Schema of a rule file |
| `commandments rule export <folder>` | write the installed rules into a folder, to edit them: a file there at the same path replaces the installed one when COMMANDMENTS_RULES names the folder or it sits beside the binary as rules/ |

<!-- END: commands:make,rule -->

## Rules that work, one per kind of question

[`reference/examples.md`](reference/examples.md) holds a working rule for each shape of question: by what a
node IS, by the type a value HAS, one rule for several languages, by where the code sits, by how much there is
of it, by what only the resolver knows, and one rule built on another. Every one of them is read by a test,
so none of them can rot into a shape the tool no longer runs.
