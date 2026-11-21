<template>
    <Head :title="$t('🎭 Role-play')" />
    <ConversationRun
        v-if="played"
        :key="run"
        :conversation="played"
        :back="speaking({ mode: 'role', arg: unit.id }).url"
        @again="run++"
    />
    <template v-else>
        <PageHeader
            :title="$t('🎭 Role-play')"
            :sub="`${unit.emoji} ${unit.title} · ${unit.dialogue.titleRo}`"
            :back="show(unit.id).url"
        />
        <EmptyState
            icon="🎭"
            :title="$t('Which character do you want to be?')"
            :text="
                $t(
                    'The app says the other character’s lines and you say yours aloud.',
                )
            "
        >
            <template #actions>
                <button
                    v-for="who in speakers"
                    :key="who"
                    type="button"
                    class="btn primary big"
                    @click="me = who"
                >
                    {{ who }}
                </button>
            </template>
        </EmptyState>
        <div class="center">
            <label class="check">
                <input v-model="hard" type="checkbox" />
                {{
                    $t(
                        'Hard mode: I do not see my line in English, only the translation',
                    )
                }}
            </label>
        </div>
    </template>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import ConversationRun from '@/components/modules/speaking/ConversationRun.vue';
import { speaking } from '@/routes';
import { show } from '@/routes/units';
import type { Dialogue } from '@/types';
import type { Conversation } from '@/utils/speaking';
import { dialogueSpeakers, roleConversation } from '@/utils/speaking';

interface RoleUnit {
    id: string;
    emoji: string;
    title: string;
    dialogue: Dialogue;
}

/** Role-play: the learner picks a character of a unit dialogue and says its lines; the app says the others. */
export default defineComponent({
    name: 'SpeakingRole',

    components: { ConversationRun, EmptyState, Head, PageHeader },

    props: {
        unit: { type: Object as PropType<RoleUnit>, required: true },
    },

    data() {
        return {
            // The character the learner plays; null while choosing.
            me: null as string | null,
            hard: false,
            run: 0,
        };
    },

    computed: {
        speakers(): string[] {
            return dialogueSpeakers(this.unit.dialogue.lines);
        },

        played(): Conversation | null {
            if (this.me === null) {
                return null;
            }

            return roleConversation(
                this.unit.id,
                this.unit.dialogue,
                this.me,
                this.hard,
                this.hard
                    ? this.$t(
                          'Hard mode: say the line in English from its translation.',
                      )
                    : this.$t('Read your line aloud, as naturally as you can.'),
            );
        },
    },

    methods: {
        speaking,
        show,
    },
});
</script>
