<script setup lang="ts">
defineProps<{ account: { name: string; email: string; sections: string[]; devices: { id: number; name: string; seen: string }[] } }>();
</script>

<template>
  <!-- @righteous InlineCaseViews -->
  <TwoColumnLayout>
    <template #aside>
      <nav class="sections">
        <h2>{{ account.name }}</h2>
        <ul>
          <template v-for="section in account.sections" :key="section">
            <li><a :href="`#${section}`"><span>{{ section }}</span></a></li>
          </template>
        </ul>
      </nav>
    </template>
    <template #main>
      <section class="devices">
        <h3>Signed in on</h3>
        <ol>
          <template v-for="device in account.devices" :key="device.id">
            <li><strong>{{ device.name }}</strong><time>{{ device.seen }}</time></li>
          </template>
        </ol>
        <p>{{ account.email }}</p>
      </section>
    </template>
  </TwoColumnLayout>
</template>
