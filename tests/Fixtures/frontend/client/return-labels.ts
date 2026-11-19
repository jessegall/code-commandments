// Asks the server to print a return's shipping label and reads back where the label was stored.
export async function printReturnLabel(returnNumber: number, copies: number): Promise<string> {
    // @sin HardCodedServerURL
    const response = await fetch(`/returns/${returnNumber}/label`, {
        method: 'POST',
        body: JSON.stringify({ copies }),
    })

    return (await response.json()).location
}
