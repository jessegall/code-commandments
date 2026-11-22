<script lang="ts">
enum Suit { Hearts = 'H', Spades = 'S' }
const LIMIT = 3

export class Box<T extends object = {}> {
    readonly size = 2
}

export type Pair<T extends string = 'a'> = [T, T]

export function describe(suit: Suit, run?: () => number): string {
    run?.()
    const doubled = LIMIT * 2
    switch (suit) {
        case Suit.Hearts:
            return 'red' + doubled
        case Suit.Spades:
            return 'black'
    }
}
</script>
