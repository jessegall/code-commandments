package shapes // @contract kind/file

// @contract kind/type
// @contract fact/type/form = "interface"
// @contract role/type/writtenMembers -> [areaSig]
type Shape interface {
	Area() int // @label areaSig function
}

// @contract kind/type
// @contract fact/type/form = "struct"
// @contract role/type/writtenMembers -> [sideF]
type Square struct {
	// @label sideF field
	// @contract kind/field
	// @contract role/field/declarators -> [sideD]
	// @label sideD declarator
	// @contract role/field/declaredType -> sideT
	// @label sideT type-expression
	// @contract role/declarator/binding -> sideB
	// @label sideB binding
	side int
}

// @contract fact/function/form = "method"
// @contract role/function/receiver -> recvP
// @label recvP parameter
// @contract role/function/returnType -> retI
// @label retI type-expression 25
// @contract role/function/body -> bodyP
// @label bodyP block
// @contract role/parameter/binding -> recvB
// @label recvB binding
// @contract role/parameter/declaredType -> ptrT
// @label ptrT type-expression 9
// @contract fact/type-expression/form at 9 = "pointer"
// @contract role/type-expression/element at 9 -> sqT
// @label sqT type-expression 10
func (s *Square) Area() int {
	// @contract kind/binary
	// @contract fact/binary/operator = "multiply"
	// @contract role/binary/left -> sideL
	// @label sideL access 9
	// @contract role/binary/right -> sideR
	// @label sideR access 18
	// @contract kind/access at 9
	// @contract fact/access/form at 9 = "instance"
	// @contract role/access/subject at 9 -> recvRef
	// @label recvRef reference 9
	// @contract role/access/member at 9 -> memRef
	// @label memRef reference 11
	// @contract link/reference/binding at 9 -> recvB
	// @contract fact/*/name on reference at 11 = "side"
	return s.side * s.side
}
