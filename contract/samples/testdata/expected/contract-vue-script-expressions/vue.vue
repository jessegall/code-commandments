<script lang="ts">
interface Item {
    name?: string
    tags: string[]
}

export class Registry {
    add(item: Item): void {}
}

declare function identity<T>(value: T): T
declare function maybe(): (() => Item) | undefined
declare function spread(...values: number[]): number

export function expressions(
    items: Item[] | null,
    registry: Registry,
    factory: () => new () => Item,
    extra?: number,
    ...more: string[]
): string {
    let total: number = 0
    const first = items?.[0]
    const made = new (factory())()
    const lookup = new Map<string, number>()
    const same = identity<string>('x')
    registry.add(made)
    maybe()?.()
    const forced = first as Item
    const kind = forced instanceof Registry ? 'registry' : 'item'
    const label = `item ${kind}`
    const [head, ...tail] = items ?? []
    const { name = 'unnamed', ...others } = forced
    const negated = !label
    const copy = { ...others, tags: [...tail] }
    total += (extra ?? 0) + more.length + spread(...[1, 2])

    return label + same + head + negated + copy + lookup.size + name + total
}
</script>
