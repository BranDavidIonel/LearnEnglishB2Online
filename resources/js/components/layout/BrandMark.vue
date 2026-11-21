<template>
    <div class="flex items-center gap-2.5">
        <span
            class="grid size-10 shrink-0 place-items-center rounded-xl bg-linear-to-br from-[#6366f1] to-[#a855f7] font-extrabold text-white"
        >
            EN
        </span>
        <span class="text-left leading-tight">
            <span class="block font-semibold text-[#e9edfb]">
                {{ $page.props.name }}
            </span>
            <span class="block text-xs text-[#97a3c9]">A1 → B2</span>
        </span>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

/** The "EN" logo with the app name, like in the sidebar of the first version. */
export default defineComponent({
    name: 'BrandMark',
});
</script>
