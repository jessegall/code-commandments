package shop

// @contract kind/alias
// @contract role/alias/underlyingType -> metersBase
// @label metersBase type-expression
type Meters = float64

// @contract role/alias/typeParameters -> [setParam]
// @contract role/type-parameter/constraint -> setBound
// @label setParam type-parameter
// @label setBound type-expression 12
type Set[T comparable] = map[T]struct{}
