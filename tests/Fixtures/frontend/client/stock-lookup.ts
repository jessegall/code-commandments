// Reads stock levels for the storefront. The loader is copied, under another name, into the
// restock notice's script — the same request and the same failure handling written twice.

interface StockLevel {
    sku: string
    available: number
}

// @sin DuplicateFunction
export async function loadStockLevels(sku: string): Promise<number[]> {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

// @righteous DuplicateFunction
export function isSoldOut(levels: number[]): boolean {
    return levels.length === 0
}
