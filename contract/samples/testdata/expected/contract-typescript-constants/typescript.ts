// @contract fact/*/effective on type = {"visibility":"private"}
enum Suit { Hearts = 'H', Spades = 'S' }
// @label limit binding
const LIMIT = 3

export class Box<T extends object = {}> {
    readonly size = 2
}

export type Pair<T extends string = 'a'> = [T, T]

// @contract fact/*/effective on function = {"visibility":"public"}
export function describe(suit: Suit, run?: () => number): string {
    // @contract fact/call/nullConditional = true
    run?.()
    // @contract fact/*/refers on reference -> limit
    const doubled = LIMIT * 2
    // @contract fact/dispatch/exhaustive = true
    switch (suit) {
        // @contract fact/*/constantValue on access = "H"
        // @contract fact/access/form = "static"
        case Suit.Hearts:
            return 'red' + doubled
        case Suit.Spades:
            return 'black'
    }
}

// @contract fact/*/effective on function = {"visibility":"public"}
function shared(): number {
    return 1
}

export { shared }
