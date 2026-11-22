<script lang="ts">
// @contract role/module/imports -> [helpersImport, itemImport]
// @contract kind/import
// @label helpersImport import
// @contract fact/import/form = "module"
// @contract fact/import/source = "./helpers"
// @contract role/import/alias -> helpersAlias
// @label helpersAlias binding 13
import * as helpers from './helpers'
// @label itemImport import
// @contract fact/import/form = "names"
// @contract fact/import/typeOnly = true
// @contract role/import/bindings -> [itemName]
// @label itemName import-binding
// @contract kind/import-binding
// @contract fact/*/name on import-binding = "Item"
import type { Item } from './item'

// @contract role/variable/declaredType -> tokenType
// @label tokenType type-expression
// @contract fact/type-expression/form = "keyword"
declare const Token: symbol
// @label sealedFn function
declare function sealed(target: unknown): void
declare function tracked(target: object, key: string): void
declare function memo(target: object, key: string, descriptor: PropertyDescriptor): void
// @label injectFn function
// @contract role/type-expression/parameters at 41 -> [fnTarget, fnKey, fnIndex]
// @label fnTarget parameter 42
// @label fnKey parameter 58
// @label fnIndex parameter 83
declare function inject(token: symbol): (target: object, key: string | undefined, index: number) => void

// @contract fact/tuple-element/label at 33 = "first"
// @contract fact/tuple-element/label at 43 = "second"
export type Pair<T = string> = [first: T, second: T]

// @contract fact/parameter/collects at 57 = "rest"
export type Handler<E extends object = {}> = (event: E, ...rest: unknown[]) => void

// @contract kind/type
// @contract fact/type/form = "interface"
// @contract role/type/writtenMembers -> [nameField]
export interface Named<T extends object = {}> {
    // @contract kind/field
    // @label nameField field
    // @contract role/field/declaredType -> nameType
    // @label nameType type-expression
    name: T
}

export interface Shape {
    // @contract kind/function
    // @contract fact/function/form = "method"
    area(): number
}

export enum Color {
    Red = 1,
    Green,
}

export enum Level {
    Low = 1,
    High = Low + 1,
}

class Base<T> {
    protected value?: T
}

// @contract kind/type
// @label box type
// @contract fact/type/form = "class"
// @contract fact/*/name on type = "Box"
// @contract role/type/attributes -> [sealedAnn]
// @label sealedAnn annotation
// @contract kind/annotation
// @contract fact/*/name on annotation = "sealed"
// @contract fact/*/refers on annotation -> sealedFn
// @contract role/annotation/callee -> sealedRef
// @label sealedRef reference 2
// @contract role/type/typeParameters -> [boxT]
// @contract role/type/bases -> [baseType]
// @contract role/type/interfaces -> [namedType, shapeType]
// @contract link/type/implicitMembers -> [serviceParam]
// @contract role/type/writtenMembers -> [countField, sideField, unitsField, ctor, areaFn, methodFn, fallbackFn]
@sealed
// @label boxT type-parameter 18
// @label baseType type-expression 51
// @contract fact/type-expression/form at 51 = "named"
// @label namedType type-expression 70
// @label shapeType type-expression 80
export class Box<T extends Named = Named> extends Base<T> implements Named<T>, Shape {
    // @contract kind/field
    // @label countField field
    // @contract role/field/attributes -> [trackedAnn]
    // @label trackedAnn annotation
    // @contract role/field/declarators -> [countDecl]
    // @contract role/field/declaredType -> countType
    @tracked
    // @label countDecl declarator
    // @label countType type-expression
    // @contract role/declarator/initializer -> zeroInit
    // @label zeroInit literal
    count: number = 0

    // @label sideField field
    // @contract fact/*/modifiers on field = ["private","readonly"]
    // @contract fact/*/effective on field = {"visibility":"private","readonly":true}
    private readonly side: number = 2
    // @label unitsField field
    // @contract fact/*/modifiers on field = ["static","readonly"]
    // @contract fact/*/effective on field = {"visibility":"public","static":true,"readonly":true}
    static readonly UNITS = 'cm'

    // @contract kind/function
    // @label ctor function
    // @contract fact/function/form = "constructor"
    // @contract role/function/parameters -> [serviceParam]
    // @label serviceParam parameter
    // @contract kind/parameter
    // @contract fact/*/modifiers on parameter = ["private","readonly"]
    // @contract role/parameter/attributes -> [injectAnn]
    // @label injectAnn annotation
    // @contract kind/annotation
    // @contract fact/*/name on annotation = "inject"
    // @contract fact/*/refers on annotation -> injectFn
    // @contract role/annotation/callee -> injectRef
    // @label injectRef reference 18
    // @contract role/annotation/arguments -> [tokenArg]
    // @label tokenArg argument
    // @contract kind/argument
    constructor(@inject(Token) private readonly service: Named) {
        // @contract fact/reference/form = "parent"
        super()
    }

    // @label areaFn function
    area(): number {
        // @contract fact/access/form at 16 = "instance"
        // @contract fact/reference/form at 16 = "self"
        return this.side * this.side
    }

    // @contract kind/function
    // @label methodFn function
    // @contract fact/function/form = "method"
    // @contract role/function/attributes -> [memoAnn]
    // @label memoAnn annotation
    // @contract role/function/typeParameters -> [uParam]
    // @contract role/function/parameters -> [valueParam]
    @memo
    // @label uParam type-parameter
    // @label valueParam parameter
    // @contract role/parameter/default -> fallbackCall
    // @label fallbackCall call
    // @contract kind/call
    // @contract role/call/callee -> fallbackAccess
    // @label fallbackAccess access
    // @contract fact/access/form = "instance"
    method<U>(value: U = this.fallback()): U {
        return value
    }

    // @label fallbackFn function
    // @contract fact/*/modifiers on function = ["private"]
    // @contract fact/*/effective on function = {"visibility":"private"}
    private fallback(): never {
        // @contract kind/throw
        // @contract role/throw/value -> newError
        // @label newError construction
        // @contract kind/construction
        // @contract role/construction/type -> errorType
        // @label errorType type-expression
        // @contract fact/type-expression/form = "named"
        // @contract role/construction/arguments -> [noneArg]
        // @label noneArg argument
        // @contract role/argument/value -> noneLit
        // @label noneLit literal
        throw new Error('none')
    }
}

export const top = Level.High
export const ratio: number = 1 / 2
// @contract kind/function
// @contract fact/function/form = "arrow"
// @contract role/function/body -> helperCall
// @label helperCall call
// @contract role/call/callee -> helperAccess
// @label helperAccess access
// @contract role/call/arguments -> [itemArg]
// @label itemArg argument
export const run = (item: Item): string => helpers.name(item)

// @contract fact/function/async = true
// @contract fact/function/generator = true
export async function* pages(source: AsyncIterable<number[]>): AsyncGenerator<number> {
    // @contract fact/loop/async = true
    // @contract role/loop/bindings -> [pageBinding]
    // @label pageBinding binding 22
    for await (const page of source) {
        // @contract kind/yield
        // @contract fact/yield/delegates = true
        // @contract role/yield/value -> pageRef
        // @label pageRef reference
        yield* page
    }
}
</script>
