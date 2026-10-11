type StockSource = { id: string; quantity: number; replenishes: boolean }

const SOURCES: StockSource[] = [
  { id: 'shelf', quantity: 8, replenishes: true },
  { id: 'reserve', quantity: 30, replenishes: false },
  { id: 'returns', quantity: 2, replenishes: false },
]

// @sin SideTableTrait
const HIDDEN = new Set(['reserve', 'returns'])

export function visibleSources(): StockSource[] {
  return SOURCES.filter((source) => source.quantity > 0 && !HIDDEN.has(source.id))
}
