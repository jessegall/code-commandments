<script setup lang="ts">
defineProps<{ media: { kind: string; url: string; title: string; pages: string[]; artist: string; tracks: { name: string; length: string }[] } }>();
</script>

<template>
  <!-- @sin InlineCaseViews -->
  <SwitchCase :value="media.kind">
    <template #image><img :src="media.url" :alt="media.title" /></template>
    <template #video><VideoPlayer :src="media.url" /></template>
    <template #document>
      <figure class="document">
        <figcaption>{{ media.title }}</figcaption>
        <nav>
          <template v-for="page in media.pages" :key="page">
            <a :href="page"><small>{{ page }}</small></a>
          </template>
        </nav>
        <footer><a :href="media.url">Download</a></footer>
      </figure>
    </template>
    <template #audio>
      <div class="album">
        <h4>{{ media.artist }}</h4>
        <table>
          <tbody>
            <template v-for="track in media.tracks" :key="track.name">
              <tr><td>{{ track.name }}</td><td>{{ track.length }}</td></tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
    <template #default><a :href="media.url">{{ media.title }}</a></template>
  </SwitchCase>
</template>
