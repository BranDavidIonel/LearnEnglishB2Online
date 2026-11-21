<template>
    <div class="card">
        <h3>{{ $t('💾 Your data') }}</h3>
        <p class="muted">
            {{
                $t(
                    'Your progress is saved automatically in your account. Download a copy to keep it safe, or restore one you saved earlier.',
                )
            }}
        </p>
        <div class="row wrap">
            <a class="btn" :href="exportUrl()" download @click="downloaded">
                {{ $t('⬇ Download my progress') }}
            </a>
            <button class="btn" :disabled="busy" @click="pickFile">
                {{ $t('⬆ Restore from a file') }}
            </button>
            <input
                ref="file"
                class="hidden"
                type="file"
                accept=".json,application/json"
                @change="restore"
            />
            <button class="btn danger-outline" :disabled="busy" @click="reset">
                {{ $t('🗑 Delete all progress') }}
            </button>
        </div>
    </div>
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import { exportUrl, importProgress, resetProgress } from '@/api/progress';
import { home } from '@/routes';
import { HttpError } from '@/utils/http';
import { toast } from '@/utils/toast';

/**
 * The learner's data: download the progress as a progress.json (the format of the original app),
 * restore one (it replaces everything, so it asks first) or delete everything (asked twice).
 */
export default defineComponent({
    name: 'ProgressBackup',

    data() {
        return {
            busy: false,
        };
    },

    methods: {
        exportUrl,

        // A network failure was already shown by send(); a refusal (not a progress file) is shown here.
        refused(error: unknown): void {
            if (error instanceof HttpError) {
                toast(error.message, 'warn');
            }
        },

        downloaded(): void {
            toast(this.$t('Your progress was saved to a file.'));
        },

        pickFile(): void {
            (this.$refs.file as HTMLInputElement).click();
        },

        async restore(event: Event): Promise<void> {
            const input = event.target as HTMLInputElement;
            const file = input.files?.[0];
            // The same file can be chosen again after a refused attempt.
            input.value = '';

            if (
                !file ||
                !confirm(
                    this.$t(
                        'Replace ALL your progress with the one in this file? What you have now will be lost.',
                    ),
                )
            ) {
                return;
            }

            this.busy = true;

            try {
                await importProgress(file);
                toast(this.$t('Your progress was restored from the file.'));
                router.visit(home().url);
            } catch (error) {
                this.refused(error);
            } finally {
                this.busy = false;
            }
        },

        async reset(): Promise<void> {
            if (
                !confirm(
                    this.$t(
                        'Really delete ALL your progress? This cannot be undone.',
                    ),
                ) ||
                !confirm(this.$t('Last check: delete everything?'))
            ) {
                return;
            }

            this.busy = true;

            try {
                await resetProgress();
                router.visit(home().url);
            } catch (error) {
                this.refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
});
</script>
