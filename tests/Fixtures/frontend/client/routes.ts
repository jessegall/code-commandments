// The route helper the server's route generator writes: it builds a named route's path from its parameters.
export function route(name: string, parameters: Record<string, string | number> = {}): string {
    return [name, ...Object.values(parameters)].join(':')
}
