// What the word pages do when a save fails: the learner is told, and nothing on the page
// pretends the change was made.
import { getLocale, trans } from '@/i18n';
import { HttpError } from '@/utils/http';
import { toast } from '@/utils/toast';

/**
 * Tells the learner a save was refused by the server. A network failure needs nothing more:
 * send() has already shown the same message after its last try.
 */
export function reportNotSaved(error: unknown): void {
    if (error instanceof HttpError) {
        toast(
            trans(
                'Your progress could not be saved. Check the connection.',
                {},
                getLocale(),
            ),
            'warn',
        );
    }
}
