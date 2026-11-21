// Saving what the learner did in a session (a lesson, a test, a speaking round, a verb drill)
// often takes several requests, made while the session runs or at its end. When one fails (no
// network, a server error), the learner must be able to send the rest again without losing the
// answers and without sending twice what already reached the server.

/** A request to the progress API, made again when it has to be sent again. */
export type SaveRequest = () => Promise<unknown>;

export interface SaveQueue {
    /** Sends the request after the ones before it, or keeps it for flush() while one is failing. */
    add(request: SaveRequest): void;
    /** Sends whatever is still waiting; false when a request failed (it is kept for the next try). */
    flush(): Promise<boolean>;
}

/** A queue of saves, starting with `requests` (sent at once). */
export function saveQueue(requests: SaveRequest[] = []): SaveQueue {
    const waiting: SaveRequest[] = [];
    let failed = false;
    let running: Promise<boolean> | null = null;

    const sendWaiting = async (): Promise<boolean> => {
        while (waiting.length) {
            try {
                await waiting[0]();
            } catch {
                failed = true;

                return false;
            }

            waiting.shift();
        }

        return true;
    };

    // One run at a time: a flush() while requests are on their way waits for them instead of
    // sending again what has not answered yet.
    const drain = (): Promise<boolean> => {
        if (!running && waiting.length) {
            running = sendWaiting().finally(() => {
                running = null;

                if (waiting.length && !failed) {
                    void drain();
                }
            });
        }

        return running ?? Promise.resolve(true);
    };

    const queue: SaveQueue = {
        add(request) {
            waiting.push(request);

            // After a failure the learner is told once, when the session ends, not for every answer.
            if (!failed) {
                void drain();
            }
        },
        flush() {
            failed = false;

            return drain();
        },
    };

    requests.forEach((request) => queue.add(request));

    return queue;
}
