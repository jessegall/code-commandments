export function describe(code: number, flag: boolean): string {
    switch (code) {
        case 0:
        case 1:
            return 'low'
        case 2:
            flag = !flag
            break
        default:
            return 'high'
    }

    return flag ? 'flagged' : 'plain'
}
