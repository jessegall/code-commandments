// Hand-written frontend types for the orders screens. `OrderData` restates the
// server `OrderData` payload field-for-field — a duplicated contract that drifts the
// moment the server adds or renames a field.

export type OrderChannel = 'web' | 'pos' | 'phone'

// @sin MirroredServerType
export interface OrderData {
  id: string
  total: number
  placedAt: string
  status: string
}

export function orderChannelLabel(channel: OrderChannel): string {
  switch (channel) {
    case 'web':
      return 'Online'
    case 'pos':
      return 'In store'
    case 'phone':
      return 'By phone'
  }
}
