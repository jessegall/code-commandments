// The one place the shop's stock screens ask the server for stock: the path, the header and the parsing live here.
export async function stockLevels(sku: string): Promise<number[]> {
    // @righteous RawFetchInComponent
    const response = await fetch(`/api/stock/${sku}`, { headers: { Accept: 'application/json' } })

    return response.json()
}
