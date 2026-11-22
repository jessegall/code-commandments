package shop

type Point struct{ X, Y int }

const Limit = 3

func consume(bool) {}

// @contract kind/parameter
// @contract fact/parameter/collects = "rest"
func apply(values ...int) int {
	// @contract kind/collection
	// @contract role/collection/entries -> [firstEntry, secondEntry]
	// @label firstEntry entry 16
	// @label secondEntry entry 19
	list := []int{1, 2}
	// @contract kind/collection
	// @contract fact/collection/form = "map"
	// @contract kind/entry
	// @contract role/entry/key -> mapKey
	// @contract role/entry/value -> mapValue
	// @label mapKey literal 26
	// @label mapValue literal 31
	// @contract fact/literal/literalKind at 26 = "string"
	// @contract fact/literal/value at 26 = "a"
	// @contract fact/literal/value at 31 = "1"
	table := map[string]int{"a": 1}
	// @contract kind/construction
	// @contract role/construction/type -> pointType
	// @contract role/construction/initializer -> pointInit
	// @label pointType type-expression
	// @label pointInit collection
	point := Point{X: 1}
	// @contract kind/index
	// @contract role/index/subject -> listRef
	// @contract role/index/key -> zero
	// @label listRef reference
	// @label zero literal
	first := list[0]
	// @contract fact/*/constantValue on reference = 3
	count := Limit
	// @contract kind/cast
	// @contract fact/cast/form = "checked"
	// @contract role/cast/operand -> firstRef
	// @contract role/cast/targetType -> floatType
	// @label firstRef reference 19
	// @label floatType type-expression
	// @contract fact/*/resolvedType on reference at 19 = {"kind":"keyword","name":"int"}
	ratio := float64(first)
	// @label flagBinding binding 2
	// @contract kind/logical at 10
	// @contract fact/logical/operator at 10 = "or"
	// @contract role/logical/left at 10 -> notFlag
	// @contract role/logical/right at 10 -> bothTests
	// @label notFlag unary
	// @label bothTests logical 26
	// @contract fact/logical/operator at 26 = "and"
	// @contract kind/unary
	// @contract fact/unary/operator = "not"
	// @contract role/unary/operand -> firstGreater
	// @label firstGreater comparison 12
	flag := !(first > 0) || first < count && ratio > 1
	// @contract kind/expression-statement
	// @contract role/expression-statement/expression -> consumeCall
	// @label consumeCall call
	// @contract kind/reference at 10
	// @contract fact/reference/form at 10 = "name"
	// @contract link/reference/binding at 10 -> flagBinding
	// @contract kind/argument
	consume(flag)
	// @contract kind/call at 9
	// @contract role/call/callee at 9 -> applyRef
	// @contract role/call/arguments at 9 -> [spreadArg]
	// @label applyRef reference 9
	// @label spreadArg argument 15
	// @contract kind/argument at 15
	// @contract fact/argument/spread at 15 = "positional"
	// @contract role/argument/value at 15 -> spreadList
	// @label spreadList reference 15
	return apply(list...) + table["a"] + point.Y + len(values)
}
