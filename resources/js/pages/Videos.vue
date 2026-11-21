<template>
    <Head :title="$t('Videos')" />
    <PageHeader
        :title="$t('🎬 Videos')"
        :sub="
            $t(
                'Short clips from English teachers, picked for each unit. Turn on English subtitles!',
            )
        "
        :back="homeUrl"
    />
    <template v-for="item in levels" :key="item.level">
        <h2>
            <LevelChip :level="item.level" />
            {{ title(item.level) }}
        </h2>
        <div class="video-grid">
            <div
                v-for="unit in item.units"
                :key="unit.id"
                class="video-item"
                @click="playing = unit"
            >
                <div class="video-thumb">
                    <img :src="thumbnail(unit)" alt="" loading="lazy" />
                    <span class="play">▶</span>
                </div>
                <strong>{{ unit.video.title }}</strong>
                <small class="muted"
                    >U{{ unit.order }} · {{ unit.grammar }}</small
                >
            </div>
        </div>
    </template>
    <EmptyState
        v-if="!levels.length"
        icon="🎬"
        :title="$t('No videos yet')"
        :text="$t('Videos will appear here as they are added to units.')"
    />
    <AppModal :open="playing !== null" wide @close="playing = null">
        <div v-if="playing">
            <VideoCard :video="playing.video" />
            <p class="muted">
                {{
                    $t(':level · Unit :n · :title', {
                        level: playing.level,
                        n: playing.order,
                        title: playing.title,
                    })
                }}
            </p>
        </div>
    </AppModal>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import VideoCard from '@/components/content/VideoCard.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import LevelChip from '@/components/general/LevelChip.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import AppModal from '@/components/modals/AppModal.vue';
import type { VideoLevel, VideoUnit } from '@/components/modules/home/types';
import { home } from '@/routes';
import type { LevelId } from '@/types';
import { levelInfo } from '@/utils/levels';

/** Every unit's recommended video, by level; a click opens the player in a window. */
export default defineComponent({
    name: 'VideosPage',

    components: {
        AppModal,
        EmptyState,
        Head,
        LevelChip,
        PageHeader,
        VideoCard,
    },

    props: {
        levels: { type: Array as PropType<VideoLevel[]>, required: true },
    },

    data() {
        return {
            /** The unit whose video is open. */
            playing: null as VideoUnit | null,
        };
    },

    computed: {
        homeUrl(): string {
            return home().url;
        },
    },

    methods: {
        thumbnail(unit: VideoUnit): string {
            return `https://i.ytimg.com/vi/${unit.video.youtubeId}/mqdefault.jpg`;
        },

        title(level: LevelId): string {
            const info = levelInfo(level);

            return info ? this.$t(info.title) : '';
        },
    },
});
</script>
