package shop // @contract kind/module

// @label larger function
// @contract fact/*/name on function = "Larger"
// @contract fact/*/symbol on function = "contract_go_generic_call.Larger"
// @contract fact/*/effective on function = {"visibility":"public"}
func Larger[T int | float64](a, b T) T {
	if a > b {
		return a
	}
	return b
}

func Use() int {
	// @contract kind/call
	// @contract role/call/callee -> larRef
	// @label larRef reference
	// @contract role/call/typeArguments -> [argInt]
	// @label argInt type-expression
	// @contract role/call/arguments -> [argOne, argTwo]
	// @label argOne argument 21
	// @label argTwo argument 24
	// @contract kind/argument at 21
	// @contract role/argument/value at 21 -> litOne
	// @label litOne literal 21
	// @contract fact/*/refers on reference -> larger
	// @contract fact/*/target on call -> larger
	return Larger[int](1, 2)
}
