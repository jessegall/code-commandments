type Courier = { code: string; overnight: boolean; depot: string }

const COURIERS: Courier[] = [
  { code: 'swift', overnight: true, depot: 'north' },
  { code: 'local', overnight: false, depot: 'east' },
  { code: 'freight', overnight: false, depot: 'west' },
]

// @sin SideTableTrait
const INSURED = ['swift', 'freight']

export function canInsure(courier: Courier): boolean {
  return INSURED.includes(courier.code)
}
