// Saving the end of a session (a test result, mistakes, XP) takes several requests in a row.
// When one fails (no network), the learner must be able to send the rest again without losing
// the answers, and without sending twice what already reached the server.

export interface ResumableSave {
    /** Sends the requests not sent yet, in order; false when one failed (it is sent again next time). */
    run(): Promise<boolean>;
}

export function resumableSave(
    requests: (() => Promise<unknown>)[],
): ResumableSave {
    let next = 0;

    return {
        async run(): Promise<boolean> {
            while (next < requests.length) {
                try {
                    await requests[next]();
                } catch {
                    return false;
                }

                next++;
            }

            return true;
        },
    };
}
