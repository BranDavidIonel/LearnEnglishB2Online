// @vitest-environment happy-dom
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    canListen,
    isMicError,
    listen,
    listenStream,
    micError,
    speechEngine,
    streamTranscript,
} from './recognition';
import { startRecording } from './recording';

/** A stand-in for Chrome's SpeechRecognition that the tests drive by hand. */
class FakeRecognition {
    static instances: FakeRecognition[] = [];
    static available = vi.fn(async () => 'available');
    static install = vi.fn(async () => true);

    lang = '';
    interimResults = false;
    maxAlternatives = 1;
    continuous = false;
    processLocally?: boolean;
    onresult: ((event: { results: unknown }) => void) | null = null;
    onerror: ((event: { error: string }) => void) | null = null;
    onstart: (() => void) | null = null;
    onend: (() => void) | null = null;
    started = false;
    stop = vi.fn(() => this.onend?.());
    abort = vi.fn(() => this.onend?.());

    constructor() {
        FakeRecognition.instances.push(this);
    }

    start(): void {
        this.started = true;
        this.onstart?.();
    }

    /** Several results at once, as a continuous recognition delivers them. */
    sayAll(transcripts: string[], isFinal = true): void {
        this.onresult?.({
            results: transcripts.map((transcript) =>
                Object.assign([{ transcript }], { isFinal }),
            ),
        });
    }

    say(alternatives: string[], isFinal = true): void {
        const result = Object.assign(
            alternatives.map((transcript) => ({ transcript })),
            { isFinal },
        );
        this.onresult?.({ results: [result] });
    }

    fail(error: string): void {
        this.onerror?.({ error });
        this.onend?.();
    }
}

const last = (): FakeRecognition =>
    FakeRecognition.instances[FakeRecognition.instances.length - 1];

beforeEach(() => {
    FakeRecognition.instances = [];
    localStorage.clear();
    vi.stubGlobal('webkitSpeechRecognition', FakeRecognition);
    // No microphone of our own: listen() goes straight to the recogniser.
    vi.stubGlobal('navigator', { mediaDevices: undefined });
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('listen', () => {
    it('resolves with the alternatives of the final result', async () => {
        const states: string[] = [];
        const session = listen((...update) => states.push(update[0]));
        await vi.waitFor(() => expect(last()?.started).toBe(true));

        expect(last().lang).toBe('en-US');
        expect(last().maxAlternatives).toBe(3);
        last().say(['I am fine', 'I am find'], false);
        last().say(['I am fine', 'I am find']);
        last().onend?.();

        await expect(session.done).resolves.toEqual(['I am fine', 'I am find']);
        expect(states).toEqual(['ready', 'interim']);
    });

    it('keeps what was heard when only interim results came', async () => {
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['good morning'], false);
        session.stop();

        await expect(session.done).resolves.toEqual(['good morning']);
    });

    it('explains that nothing was understood', async () => {
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('no-speech');

        const error: unknown = await session.done.catch((e: unknown) => e);
        expect(isMicError(error) && error.code).toBe('no-speech');
        expect(isMicError(error) && error.text).toContain(
            'no English words were heard',
        );
    });

    it('switches to on-device recognition when the online service is unreachable', async () => {
        const states: string[] = [];
        const session = listen((...update) => states.push(update[0]));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('network');
        await vi.waitFor(() =>
            expect(FakeRecognition.instances).toHaveLength(2),
        );

        expect(last().processLocally).toBe(true);
        expect(states).toContain('retry');
        expect(speechEngine()).toBe('local');
        last().say(['hello']);
        last().onend?.();
        await expect(session.done).resolves.toEqual(['hello']);
    });

    it('gives up on "network" when on-device recognition is unavailable', async () => {
        FakeRecognition.available.mockResolvedValueOnce('unavailable');
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('network');

        await expect(session.done).rejects.toMatchObject({ code: 'network' });
        expect(speechEngine()).toBe('cloud');
    });

    it('cancels and frees the microphone when stopped while it opens', async () => {
        const track = { label: 'USB mic', stop: vi.fn() };
        const stream = {
            getAudioTracks: () => [track],
            getTracks: () => [track],
        };
        let open: (value: typeof stream) => void = () => {};
        vi.stubGlobal('navigator', {
            mediaDevices: {
                getUserMedia: () => new Promise((resolve) => (open = resolve)),
            },
        });
        const session = listen();
        session.stop();
        open(stream);

        await expect(session.done).rejects.toMatchObject({ code: 'aborted' });
        expect(FakeRecognition.instances).toHaveLength(0);
        await vi.waitFor(() => expect(track.stop).toHaveBeenCalled());
    });

    it('explains a blocked microphone', async () => {
        vi.stubGlobal('navigator', {
            mediaDevices: {
                getUserMedia: () =>
                    Promise.reject(
                        Object.assign(new Error('denied'), {
                            name: 'NotAllowedError',
                        }),
                    ),
            },
        });

        await expect(listen().done).rejects.toMatchObject({
            code: 'not-allowed',
        });
    });
});

describe('listenStream', () => {
    it('passes on what is heard and starts again after each pause until stopped', async () => {
        vi.useFakeTimers();
        const heard: string[] = [];
        const stream = listenStream((text) => heard.push(text));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['apple']);
        last().onend?.();
        await vi.advanceTimersByTimeAsync(120);

        expect(heard).toEqual(['apple']);
        expect(FakeRecognition.instances).toHaveLength(2);

        stream.stop();
        await vi.advanceTimersByTimeAsync(1000);
        expect(FakeRecognition.instances).toHaveLength(2);
    });

    it('reports an error it cannot recover from', async () => {
        const errors: string[] = [];
        listenStream(
            () => {},
            (...update) => {
                if (update[0] === 'error') {
                    errors.push(update[1].code);
                }
            },
        );
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('not-allowed');
        await vi.waitFor(() => expect(errors).toEqual(['not-allowed']));
    });
});

describe('errors', () => {
    it('names the page address in the microphone help', () => {
        expect(micError('not-allowed').text).toContain(window.location.host);
    });

    it('falls back to the code for unknown errors', () => {
        expect(micError('bad-grammar').text).toBe(
            'Speech recognition returned an error (bad-grammar).',
        );
    });

    it('knows when the browser can listen', () => {
        expect(canListen()).toBe(true);
    });
});

// ---------- phones and tablets ----------

const ANDROID =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';
const WINDOWS =
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

/** A browser with a microphone of its own; returns the getUserMedia spy and the track. */
function browser(userAgent: string) {
    const track = { label: 'Built-in mic', stop: vi.fn() };
    const stream = { getAudioTracks: () => [track], getTracks: () => [track] };
    const getUserMedia = vi.fn(async () => stream);
    vi.stubGlobal('navigator', {
        userAgent,
        mediaDevices: { getUserMedia },
    });

    return { getUserMedia, track };
}

describe('listen on a phone', () => {
    it('lets the recogniser open the microphone itself', async () => {
        const { getUserMedia } = browser(ANDROID);
        const states: string[] = [];
        const session = listen((...update) => states.push(update[0]));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['hello there']);
        last().onend?.();

        await expect(session.done).resolves.toEqual(['hello there']);
        expect(getUserMedia).not.toHaveBeenCalled();
        // 'ready' once the recogniser has started, and never a loudness level
        expect(states).toEqual(['ready']);
    });

    it('still opens the microphone first on a desktop', async () => {
        const { getUserMedia } = browser(WINDOWS);
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['hello']);
        last().onend?.();

        await expect(session.done).resolves.toEqual(['hello']);
        expect(getUserMedia).toHaveBeenCalledTimes(1);
    });

    it('explains the permission error of the recogniser with phone help', async () => {
        browser(ANDROID);
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('not-allowed');

        const error: unknown = await session.done.catch((e: unknown) => e);
        expect(isMicError(error) && error.code).toBe('not-allowed');
        expect(isMicError(error) && error.text).toContain(
            'Permissions → Microphone',
        );
    });

    it('ends a recording before the recogniser listens', async () => {
        const { track } = browser(ANDROID);
        vi.stubGlobal('MediaRecorder', FakeRecorder);
        vi.stubGlobal('URL', { createObjectURL: () => 'blob:x' });
        const onDone = vi.fn();
        await startRecording(onDone);

        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));

        expect(track.stop).toHaveBeenCalled();
        expect(onDone).toHaveBeenCalledWith('blob:x');
        session.stop();
        await session.done.catch(() => {});
    });
});

/** A MediaRecorder that records nothing. */
class FakeRecorder {
    state = 'inactive';
    ondataavailable: ((e: { data: Blob }) => void) | null = null;
    onstop: (() => void) | null = null;

    start(): void {
        this.state = 'recording';
    }

    stop(): void {
        this.state = 'inactive';
        this.onstop?.();
    }
}

describe('listenStream on a phone', () => {
    it('merges the growing final results of Android', async () => {
        browser(ANDROID);
        const heard: string[] = [];
        const stream = listenStream((text) => heard.push(text));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().sayAll(['apple']);
        last().sayAll(['apple', 'apple banana']);
        last().sayAll(['apple', 'apple banana', 'apple banana cherry']);
        stream.stop();

        expect(heard).toEqual(['apple', 'apple banana', 'apple banana cherry']);
    });

    it('keeps joining separate phrases on a desktop', async () => {
        browser(WINDOWS);
        const heard: string[] = [];
        const stream = listenStream((text) => heard.push(text));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().sayAll(['apple', 'banana']);
        stream.stop();

        expect(heard).toEqual(['apple banana']);
    });
});

describe('streamTranscript', () => {
    it('replaces a result by the next one that extends it', () => {
        expect(streamTranscript(['one', 'one two', 'One two three '])).toBe(
            'One two three',
        );
    });

    it('drops a result that only repeats the one before', () => {
        expect(streamTranscript(['one two', 'one', ''])).toBe('one two');
    });

    it('joins separate phrases', () => {
        expect(streamTranscript(['apple', 'banana', 'banana split'])).toBe(
            'apple banana split',
        );
        expect(streamTranscript([])).toBe('');
    });
});
