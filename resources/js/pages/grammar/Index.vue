<template>
    <Head :title="$t('Grammar')" />
    <PageHeader
        :title="$t('📐 Grammar')"
        :sub="
            $t(
                'All the rules from the course in one place. Read them any time.',
            )
        "
        :back="homeUrl"
    />
    <section
        v-for="item in levels"
        :key="item.level"
        class="card"
        :style="{ '--c': color(item.level) }"
    >
        <h2>
            <LevelChip :level="item.level" />
            {{ title(item.level) }}
        </h2>
        <div class="gram-list">
            <Link
                v-for="unit in item.units"
                :key="unit.id"
                class="gram-row"
                :href="lessonUrl(unit.id)"
            >
                <span class="gram-n">{{ unit.order }}</span>
                <span class="grow">
                    <strong>{{ unit.title }}</strong>
                    <small class="muted">{{ unit.titleRo }}</small>
                </span>
                <span v-if="unit.done">✅</span>
            </Link>
        </div>
    </section>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import LevelChip from '@/components/general/LevelChip.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import type { GrammarLevel } from '@/components/modules/home/types';
import { grammar, home } from '@/routes';
import type { LevelId } from '@/types';
import { levelInfo } from '@/utils/levels';

/** Every unit's grammar, level by level; ✅ marks the lessons already studied. */
export default defineComponent({
    name: 'GrammarIndexPage',

    components: { Head, LevelChip, Link, PageHeader },

    props: {
        levels: { type: Array as PropType<GrammarLevel[]>, required: true },
    },

    computed: {
        homeUrl(): string {
            return home().url;
        },
    },

    methods: {
        lessonUrl(unit: string): string {
            return grammar(unit).url;
        },

        color(level: LevelId): string {
            return levelInfo(level)?.color ?? '';
        },

        title(level: LevelId): string {
            const info = levelInfo(level);

            return info ? this.$t(info.title) : '';
        },
    },
});
</script>
