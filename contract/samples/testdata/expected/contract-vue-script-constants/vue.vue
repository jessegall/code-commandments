<script lang="ts">
// @contract kind/module
// @label mod module
// @contract role/module/declarations -> [suit, box, pair, describe]
// @contract role/module/statements -> [limitVar]
// @contract kind/type
// @label suit type
// @contract fact/type/form = "enum"
// @contract fact/*/name on type = "Suit"
// @contract fact/*/symbol on type -> suit
// @contract role/type/writtenMembers -> [hearts, spades]
// @label hearts enum-case 13
// @label spades enum-case 27
// @contract kind/enum-case at 13
// @contract role/enum-case/value at 13 -> hLit
// @label hLit literal 22
// @contract fact/literal/literalKind at 22 = "string"
// @contract fact/literal/value at 22 = "H"
enum Suit { Hearts = 'H', Spades = 'S' }
// @contract kind/variable
// @label limitVar variable
// @contract fact/variable/form = "const"
// @contract role/variable/declarators -> [limitDecl]
// @label limitDecl declarator
// @contract kind/declarator
// @contract role/declarator/binding -> limit
// @label limit binding
// @contract kind/binding
// @contract role/declarator/initializer -> three
// @label three literal
// @contract kind/literal
// @contract fact/literal/literalKind = "integer"
// @contract fact/literal/value = "3"
const LIMIT = 3

// @contract kind/type
// @label box type
// @contract fact/type/form = "class"
// @contract role/type/typeParameters -> [boxT]
// @label boxT type-parameter
// @contract kind/type-parameter
// @contract role/type-parameter/constraint -> objectType
// @label objectType type-expression 28
// @contract kind/type-expression at 28
// @contract fact/type-expression/form at 28 = "keyword"
// @contract role/type-parameter/default -> emptyShape
// @label emptyShape type-expression 37
// @contract fact/type-expression/form at 37 = "object"
// @contract role/type/writtenMembers -> [size]
export class Box<T extends object = {}> {
    // @contract kind/field
    // @label size field
    // @contract fact/*/modifiers on field = ["readonly"]
    // @contract fact/*/effective on field = {"visibility":"public","readonly":true}
    // @contract role/field/declarators -> [sizeDecl]
    // @label sizeDecl declarator
    readonly size = 2
}

// @contract kind/alias
// @label pair alias
// @contract fact/*/name on alias = "Pair"
// @contract role/alias/typeParameters -> [pairT]
// @label pairT type-parameter
// @contract role/type-parameter/default -> aType
// @label aType type-expression 37
// @contract fact/type-expression/form at 37 = "literal"
// @contract role/alias/underlyingType -> tuple
// @label tuple tuple-type
// @contract kind/tuple-type
// @contract role/tuple-type/elements -> [first, second]
// @label first tuple-element 45
// @label second tuple-element 48
// @contract kind/tuple-element at 45
// @contract role/tuple-element/type at 45 -> firstType
// @label firstType type-expression 45
export type Pair<T extends string = 'a'> = [T, T]

// @contract kind/function
// @label describe function
// @contract fact/function/form = "function"
// @contract fact/*/name on function = "describe"
// @contract role/function/parameters -> [suitParam, runParam]
// @label suitParam parameter 26
// @label runParam parameter 38
// @contract kind/parameter at 26
// @contract role/parameter/binding at 26 -> suitBinding
// @label suitBinding binding 26
// @contract role/parameter/declaredType at 26 -> suitType
// @label suitType type-expression 32
// @contract fact/parameter/optional at 38 = true
// @contract role/parameter/declaredType at 38 -> runType
// @label runType type-expression 44
// @contract fact/type-expression/form at 44 = "function"
// @contract role/type-expression/returnType at 44 -> fnReturn
// @label fnReturn type-expression 50
// @contract role/function/returnType -> stringType
// @label stringType type-expression 59
// @contract role/function/body -> describeBody
// @label describeBody block 66
// @contract kind/block at 66
export function describe(suit: Suit, run?: () => number): string {
    // @contract kind/expression-statement
    // @contract role/expression-statement/expression -> runCall
    // @label runCall call
    // @contract kind/call
    // @contract fact/call/nullConditional = true
    // @contract role/call/callee -> runRef
    // @label runRef reference
    // @contract kind/reference
    run?.()
    // @contract kind/binary
    // @contract fact/binary/operator = "multiply"
    // @contract role/binary/left -> limitRef
    // @label limitRef reference
    // @contract role/binary/right -> twoLit
    // @label twoLit literal
    // @contract fact/*/resolvedType on binary = {"kind":"keyword","name":"number"}
    // @contract fact/*/refers on reference -> limit
    // @contract link/reference/binding -> limit
    // @contract fact/*/constantValue on reference = 3
    const doubled = LIMIT * 2
    // @contract kind/dispatch
    // @contract fact/dispatch/form = "switch"
    // @contract fact/dispatch/exhaustive = true
    // @contract role/dispatch/subject -> suitSubject
    // @label suitSubject reference
    // @contract role/dispatch/arms -> [heartsArm, spadesArm]
    switch (suit) {
        // @contract kind/arm
        // @label heartsArm arm
        // @contract role/arm/patterns -> [heartsPattern]
        // @label heartsPattern pattern
        // @contract kind/pattern
        // @contract fact/pattern/form = "value"
        // @contract role/pattern/value -> heartsAccess
        // @label heartsAccess access
        // @contract kind/access
        // @contract fact/access/form = "static"
        // @contract role/access/subject -> suitEnum
        // @label suitEnum reference 14
        // @contract role/access/member -> heartsMember
        // @label heartsMember reference 19
        // @contract fact/*/constantValue on access = "H"
        // @contract role/arm/body -> heartsBody
        case Suit.Hearts:
            // @label heartsBody block
            // @contract kind/block
            // @contract role/block/statements -> [heartsReturn]
            // @label heartsReturn return
            // @contract kind/return
            // @contract role/return/values -> [concat]
            // @label concat binary 20
            // @contract fact/literal/value = "red"
            return 'red' + doubled
        // @label spadesArm arm
        case Suit.Spades:
            return 'black'
    }
}
</script>
