<template>
    <div class="empty">
        <div class="empty-icon">{{ icon }}</div>
        <h2>{{ title }}</h2>
        <p v-if="text" class="muted">{{ text }}</p>
        <div v-if="$slots.actions" class="row center wrap">
            <slot name="actions" />
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

/** Big emoji, a title, a line of text and buttons: for "nothing here yet" and "all done" screens. */
export default defineComponent({
    name: 'EmptyState',

    props: {
        /** An emoji. */
        icon: { type: String, required: true },
        title: { type: String, required: true },
        text: { type: String, default: '' },
    },
});
</script>
