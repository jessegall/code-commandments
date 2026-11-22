declare const Token: symbol
declare function sealed(target: unknown): void
declare function tracked(target: object, key: string): void
declare function memo(target: object, key: string, descriptor: PropertyDescriptor): void
declare function inject(token: symbol): (target: object, key: string | undefined, index: number) => void

// @contract kind/alias
// @contract role/alias/typeParameters -> pairtp
// @contract role/alias/underlyingType -> pairtuple
// @contract kind/type-parameter
// @contract role/type-parameter/default -> pairdef
// @contract kind/type-expression at 22
// @contract fact/type-expression/form at 22 = "keyword"
// @contract kind/tuple-type
// @contract role/tuple-type/elements -> [elfirst, elsecond]
// @contract kind/tuple-element at 33
// @contract fact/tuple-element/label at 33 = "first"
// @contract role/tuple-element/type at 33 -> firstt
// @label pairtp type-parameter
// @label pairtuple tuple-type
// @label pairdef type-expression 22
// @label elfirst tuple-element 33
// @label elsecond tuple-element 43
// @label firstt type-expression 40
export type Pair<T = string> = [first: T, second: T]

// @contract role/type-parameter/constraint -> hobj
// @contract role/type-parameter/default -> hempty
// @contract fact/type-expression/form at 40 = "object"
// @contract fact/type-expression/form at 46 = "function"
// @contract role/type-expression/parameters at 46 -> [hev, hrest]
// @contract role/type-expression/returnType at 46 -> hvoid
// @contract fact/parameter/collects at 57 = "rest"
// @label hobj type-expression 31
// @label hempty type-expression 40
// @label hev parameter 47
// @label hrest parameter 57
// @label hvoid type-expression 80
export type Handler<E extends object = {}> = (event: E, ...rest: unknown[]) => void

// @contract fact/type/form = "interface"
// @contract role/type/typeParameters -> namedtp
// @contract role/type/writtenMembers -> namedname
// @label namedtp type-parameter
export interface Named<T extends object = {}> {
    // @label namedname field
    name: T
}

// @contract fact/type/form = "enum"
// @contract role/type/writtenMembers -> [casered, casegreen]
export enum Color {
    // @contract kind/enum-case
    // @contract role/enum-case/value -> redone
    // @label casered enum-case
    // @label redone literal
    Red = 1,
    // @label casegreen enum-case
    Green,
}

class Base<T> {
    protected value?: T
}

// @contract kind/type
// @contract fact/type/form = "class"
// @contract role/type/attributes -> sealedanno
// @contract role/type/bases -> basety
// @contract role/type/interfaces -> namedty
// @contract role/type/typeParameters -> boxtp
// @contract role/type/writtenMembers -> [fcount, fctor, fmethod, ffallback]
// @contract link/type/implicitMembers -> svcparam
// @label sealedanno annotation
@sealed
// @label basety type-expression 51
// @label namedty type-expression 70
// @label boxtp type-parameter
export class Box<T extends Named = Named> extends Base<T> implements Named<T> {
    // @contract kind/field
    // @contract role/field/attributes -> trackanno
    // @contract role/field/declarators -> countdecl
    // @contract role/field/declaredType -> counttype
    // @label trackanno annotation
    // @label fcount field
    @tracked
    // @contract kind/declarator
    // @contract role/declarator/binding -> countbind
    // @contract role/declarator/initializer -> countzero
    // @label countdecl declarator
    // @label counttype type-expression
    // @label countbind binding
    // @label countzero literal
    count: number = 0

    // @contract fact/function/form = "constructor"
    // @contract role/function/parameters -> svcparam
    // @contract role/function/body -> ctorbody
    // @contract role/parameter/attributes -> injanno
    // @contract role/parameter/binding -> svcbind
    // @contract role/parameter/declaredType -> svctype
    // @contract kind/annotation
    // @contract role/annotation/callee -> injref
    // @contract role/annotation/arguments -> tokarg
    // @label fctor function
    // @label svcparam parameter
    // @label ctorbody block
    // @label injanno annotation
    // @label svcbind binding
    // @label svctype type-expression
    // @label injref reference 18
    // @label tokarg argument
    constructor(@inject(Token) private readonly service: Named) {
        super()
    }

    // @contract fact/function/form = "method"
    // @contract role/function/attributes -> memoanno
    // @contract role/function/typeParameters -> methodtp
    // @contract role/function/parameters -> valparam
    // @contract role/function/returnType -> methodret
    // @contract role/function/body -> methodbody
    // @label memoanno annotation
    // @label fmethod function
    @memo
    // @contract role/parameter/binding -> valbind
    // @contract role/parameter/declaredType -> valtype
    // @contract role/parameter/default -> valdefault
    // @contract kind/call
    // @contract role/call/callee -> fallbackaccess
    // @contract kind/access
    // @label methodtp type-parameter
    // @label valparam parameter
    // @label methodret type-expression 44
    // @label methodbody block
    // @label valbind binding
    // @label valtype type-expression 22
    // @label valdefault call
    // @label fallbackaccess access
    method<U>(value: U = this.fallback()): U {
        return value
    }

    // @label ffallback function
    private fallback(): never {
        // @contract kind/throw
        // @contract role/throw/value -> noneerror
        // @contract kind/construction
        // @contract role/construction/type -> errortype
        // @contract role/construction/arguments -> noneargument
        // @label noneerror construction
        // @label errortype type-expression
        // @label noneargument argument
        throw new Error('none')
    }
}
