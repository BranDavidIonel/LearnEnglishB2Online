<template>
    <div class="card video-card">
        <h3>{{ $t('🎬 Recommended video') }}</h3>
        <div v-if="playing" class="video-frame">
            <iframe
                :src="embedUrl"
                :allow="permissions"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
                :title="video.title"
            />
        </div>
        <div v-else class="video-thumb" @click="playing = true">
            <img :src="thumbnailUrl" :alt="video.title" loading="lazy" />
            <span class="play">▶</span>
        </div>
        <div class="row between wrap">
            <span>{{ video.title }}</span>
            <a :href="watchUrl" target="_blank" rel="noopener">
                {{ $t('Open on YouTube ↗') }}
            </a>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { Video } from '@/types';

// What the YouTube player may use, as in YouTube's own embed code.
const PERMISSIONS =
    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';

/**
 * A unit's recommended video. Only the thumbnail is shown at first: the (privacy-enhanced)
 * YouTube player is loaded after a click, so nothing is sent to YouTube before that.
 */
export default defineComponent({
    name: 'VideoCard',

    props: {
        video: { type: Object as PropType<Video>, required: true },
    },

    data() {
        return {
            playing: false,
            permissions: PERMISSIONS,
        };
    },

    computed: {
        embedUrl(): string {
            return `https://www.youtube-nocookie.com/embed/${this.video.youtubeId}?autoplay=1&rel=0&cc_load_policy=1&cc_lang_pref=en`;
        },

        thumbnailUrl(): string {
            return `https://i.ytimg.com/vi/${this.video.youtubeId}/hqdefault.jpg`;
        },

        watchUrl(): string {
            return `https://www.youtube.com/watch?v=${this.video.youtubeId}`;
        },
    },

    watch: {
        // Another unit's video: back to its thumbnail.
        'video.youtubeId'(): void {
            this.playing = false;
        },
    },
});
</script>
