package shop // @contract role/file/modules -> [line(1,module)]

func kind(code int) string {
	// @contract kind/dispatch
	// @contract fact/dispatch/form = "switch"
	// @contract role/dispatch/subject -> codeRef
	// @label codeRef reference
	// @contract role/dispatch/arms -> [armA, armB, armC]
	switch code {
	// @label armA arm
	// @contract kind/arm
	// @contract fact/arm/fallsThrough = true
	// @contract role/arm/patterns -> [patOne, patTwo]
	// @label patOne pattern 7
	// @label patTwo pattern 10
	// @contract kind/pattern at 7
	// @contract fact/pattern/form at 7 = "value"
	// @contract role/pattern/value at 7 -> litOne
	// @label litOne literal 7
	case 1, 2:
		fallthrough
	// @label armB arm
	// @contract role/arm/body -> bodyB
	case 3:
		return "low" // @label bodyB block
	// @label armC arm
	// @contract fact/arm/default = true
	default:
		return "other"
	}
}

func shape(value any) int {
	// @contract kind/dispatch
	// @contract fact/dispatch/form = "type-switch"
	// @contract role/dispatch/subject -> tswitch
	// @label tswitch type-test
	// @contract role/dispatch/arms -> [shapeInt, shapeStr]
	// @contract kind/type-test
	// @contract fact/type-test/form = "type-switch"
	// @contract role/type-test/subject -> valRef
	// @label valRef reference 18
	// @contract role/type-test/binding -> typedB
	// @label typedB binding 9
	switch typed := value.(type) {
	// @label shapeInt arm
	// @contract kind/pattern
	// @contract fact/pattern/form = "type"
	// @contract role/pattern/constrainedType -> intT
	// @label intT type-expression 7
	case int:
		return typed
	// @label shapeStr arm
	case string:
		return len(typed)
	}
	return 0
}
