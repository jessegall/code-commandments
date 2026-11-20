// A composable that loads each map library with the same guard and the same call, only the library's name
// differing: one loader taking the name. Each body is just heavy enough to count, a thrown `new Error` included.

declare function importLibrary(name: string): Promise<unknown>
declare function ensureOptions(): void

export function useMapLoaders(): MapLoaders {
    // @sin NearDuplicateFunction
    async function loadMaps(): Promise<unknown> {
        if (typeof window === 'undefined') {
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Maps load only in the browser')
        }
        ensureOptions()
        return await importLibrary('maps')
    }

    // @sin NearDuplicateFunction
    async function loadPlaces(): Promise<unknown> {
        if (typeof window === 'undefined') {
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Maps load only in the browser')
        }
        ensureOptions()
        return await importLibrary('places')
    }

    // @sin NearDuplicateFunction
    async function loadMarker(): Promise<unknown> {
        if (typeof window === 'undefined') {
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Maps load only in the browser')
        }
        ensureOptions()
        return await importLibrary('marker')
    }

    return { loadMaps, loadPlaces, loadMarker }
}

interface MapLoaders {
    loadMaps(): Promise<unknown>
    loadPlaces(): Promise<unknown>
    loadMarker(): Promise<unknown>
}
