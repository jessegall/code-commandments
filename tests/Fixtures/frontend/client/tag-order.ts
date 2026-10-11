type OrderedTag = { name: string; rank: number }

const TAGS: OrderedTag[] = [
  { name: 'reply', rank: 1 },
  { name: 'log', rank: 2 },
  { name: 'todo', rank: 3 },
]

// @righteous SideTableTrait
const VISIBLE_ORDER = ['reply', 'log']

export function orderedNames(): string[] {
  const result: string[] = []
  for (const name of VISIBLE_ORDER) result.push(name)
  return result
}
