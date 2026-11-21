<template>
    <component
        :is="href ? 'Link' : 'div'"
        :href="href || undefined"
        class="brand"
    >
        <span class="brand-logo">EN</span>
        <span>
            <strong>{{ $page.props.name }}</strong>
            <small>A1 → B2</small>
        </span>
    </component>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';

/** The "EN" logo with the app name, at the top of the menu and above the login form. */
export default defineComponent({
    name: 'BrandMark',

    components: { Link },

    props: {
        /** Makes it a link (the menu links it to the home page). */
        href: { type: String, default: '' },
    },
});
</script>
