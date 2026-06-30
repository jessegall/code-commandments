<script setup lang="ts">
import type { Order, OrderBadge } from '@/types';
import OrderBadgeBar from './OrderBadgeBar.vue';

defineProps<{ order: Order; badge: OrderBadge }>();
</script>

<template>
  <div class="order-summary">
    <!-- A Card compound welded inline — Card + Card* parts with a populated body.
         It is its own OrderSummaryCard. -->
    <!-- @sin CompoundInlineComponent -->
    <Card class="summary">
      <CardHeader>
        <CardTitle>Order summary</CardTitle>
        <CardDescription>Placed and awaiting fulfilment.</CardDescription>
      </CardHeader>
      <CardContent>
        <dl class="lines">
          <dt>Reference</dt>
          <dd>{{ order.reference }}</dd>
          <dt>Placed</dt>
          <dd>{{ order.placedAt }}</dd>
        </dl>
        <ul class="totals">
          <li class="total"><span>Subtotal</span><span>{{ order.subtotal }}</span></li>
        </ul>
      </CardContent>
      <CardFooter>
        <Button variant="ghost">Print</Button>
      </CardFooter>
    </Card>

    <!-- `badge` is read nowhere here, and OrderBadgeBar just pipes it on — a drilling chain. -->
    <!-- @sin PropDrilling -->
    <OrderBadgeBar :badge="badge" />
  </div>
</template>
