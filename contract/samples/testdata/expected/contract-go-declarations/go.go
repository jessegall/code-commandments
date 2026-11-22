package shop

type Celsius float64

// @contract kind/type-parameter
// @contract fact/*/name on type-parameter = "T"
// @contract role/type-parameter/constraint -> anyC
// @label anyC type-expression 13
type List[T any] []T

type Number interface {
	// @label firstMember type-expression 2 inner
	// @label secondMember type-expression 8
	// @contract role/type-expression/members at 2 -> [firstMember, secondMember]
	// @contract kind/type-expression at 8
	int | float64
}

type Reader interface{ Read() string }
type Closer interface{ Close() }
// @contract role/type/bases -> [baseR, baseC]
type ReadCloser interface {
	Reader // @label baseR type-expression
	Closer // @label baseC type-expression
}

// @contract kind/constant
// @contract role/constant/declarators -> [limD]
// @contract role/constant/declaredType -> limT
// @label limD declarator
// @label limT type-expression
// @contract kind/binding
// @contract kind/declarator
// @contract role/declarator/binding -> limB
// @contract role/declarator/initializer -> limV
// @label limB binding
// @label limV literal
// @contract fact/literal/literalKind = "integer"
// @contract fact/literal/value = "3"
const Limit int = 3

// @contract role/type/typeParameters -> [boxT]
// @label boxT type-parameter
// @contract role/type/writtenMembers -> [valueF]
// @label valueF field
// @contract kind/field
// @contract role/field/declarators -> [valueD]
// @label valueD declarator
// @contract role/field/declaredType -> valueT
// @label valueT type-expression 34
// @contract role/declarator/binding -> valueB
// @label valueB binding 28
type Box[T Number] struct{ value T }

type File struct{}

// @contract role/function/receiver -> recvF
// @label recvF parameter 7
// @contract kind/parameter
// @contract role/parameter/declaredType -> recvT
// @label recvT type-expression 7
// @contract role/function/returnType -> retS
// @label retS type-expression 20
// @contract role/function/body -> bodyF
// @label bodyF block
func (File) Read() string { return "" }
func (File) Close()       {}

// @contract kind/function
// @contract role/function/typeParameters -> [sumT]
// @label sumT type-parameter
// @contract role/function/parameters -> [pVals, pLook, pHand]
// @label pVals parameter 20
// @label pLook parameter 32
// @label pHand parameter 55
// @contract role/function/returnType -> results
// @label results tuple-type
// @contract role/function/body -> sumBody
// @label sumBody block
// @contract kind/tuple-type
// @contract role/tuple-type/elements -> [rTotal, rErr]
// @label rTotal tuple-element 81
// @label rErr tuple-element 90
// @contract kind/tuple-element at 81
// @contract fact/tuple-element/label at 81 = "total"
// @contract role/tuple-element/type at 81 -> totT
// @label totT type-expression 87
// @contract role/parameter/binding at 20 -> valsB
// @label valsB binding 20
// @contract role/parameter/declaredType at 20 -> valsT
// @label valsT type-expression 27
// @contract fact/type-expression/form at 27 = "array"
// @contract role/type-expression/element at 27 -> elemT
// @label elemT type-expression 29
// @contract fact/type-expression/form at 39 = "map"
// @contract fact/type-expression/form at 63 = "function"
// @contract role/type-expression/parameters at 63 -> [unnP]
// @label unnP parameter 68
// @contract role/type-expression/returnType at 63 -> errT
// @label errT type-expression 73
func Sum[T Number](values []T, lookup map[string]int, handler func(int) error) (total T, err error) {
	for _, each := range values {
		// @contract role/assignment/targets -> [totalT]
		// @contract role/assignment/values -> [eachV]
		// @label totalT reference 3
		// @label eachV reference 12
		// @contract fact/assignment/mode = "compound"
		// @contract fact/assignment/operator = "add"
		total += each
	}
	// @contract fact/return/namedResults = true
	return
}

func Convert(value any) (int, bool) {
	// @contract kind/variable
	// @contract fact/variable/form = "var"
	// @contract role/variable/declarators -> [cntD]
	// @label cntD declarator
	// @contract role/variable/declaredType -> cntT
	// @label cntT type-expression 12
	// @contract role/declarator/binding -> cntB
	// @label cntB binding
	// @contract role/declarator/initializer -> cntI
	// @label cntI binary
	// @contract fact/type-expression/form at 12 = "keyword"
	// @contract fact/*/name on type-expression at 12 = "int"
	// @contract fact/*/constantValue on reference at 18 = 3
	var count int = Limit + 1
	number, ok := value.(int)
	return number + count + int(float64(count)), ok
}
