// Loads a product's reviews. The question feed's component loads its questions with the same
// steps — only the endpoint and the names of the locals differ — so the two are one loader
// waiting for a parameter.

interface Entry {
    id: number
    published: boolean
}

// @sin NearDuplicateFunction
export async function loadReviews(productId: number): Promise<number[]> {
    const response = await fetch(`/api/products/${productId}/reviews`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const reviews: Entry[] = await response.json()
    const shown = reviews.filter((review) => review.published)
    return shown.map((review) => review.id)
}
