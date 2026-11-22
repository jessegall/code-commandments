export interface Shape {
    area(): number
}

export class Square implements Shape {
    // @contract kind/field
    // @contract fact/*/effective on field = {"visibility":"private","readonly":true}
    // @contract role/field/declarators -> sidedecl
    // @contract role/field/declaredType -> sidetype
    // @label sidedecl declarator
    // @label sidetype type-expression
    private readonly side: number = 2
    // @contract fact/*/effective on field = {"visibility":"public","static":true,"readonly":true}
    static readonly UNITS = 'cm'

    // @contract fact/function/form = "method"
    // @contract fact/*/effective on function = {"visibility":"public"}
    // @contract fact/*/symbol on function -> areafn
    // @label areafn function
    // @contract fact/*/obligations on function = [{"origin":"project"}]
    area(): number {
        // @contract kind/binary
        // @contract fact/binary/operator = "multiply"
        // @contract role/binary/left -> leftaccess
        // @contract role/binary/right -> rightaccess
        // @contract kind/access at 16
        // @contract fact/access/form at 16 = "instance"
        // @contract role/access/subject at 16 -> thisref
        // @contract role/access/member at 16 -> sideref
        // @contract kind/reference at 16
        // @contract fact/reference/form at 16 = "self"
        // @contract fact/*/name on reference at 21 = "side"
        // @label leftaccess access 16
        // @label rightaccess access 28
        // @label thisref reference 16
        // @label sideref reference 21
        return this.side * this.side
    }
}

export enum Level {
    Low = 1,
    High = Low + 1,
}

// @contract fact/*/constantValue on access = 2
export const top = Level.High
// @contract fact/*/resolvedType on binary = {"kind":"keyword","name":"number"}
export const ratio: number = 1 / 2
