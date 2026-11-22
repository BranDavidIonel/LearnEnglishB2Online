// The microphone on phones and tablets.
//
// Desktop browsers share the microphone: speech recognition, our own level meter and a recording
// can all listen at once. Android (and iOS) give it to one user at a time, and whoever opened it
// first keeps it: a recogniser started while we hold a getUserMedia() stream hears nothing. So on
// these devices the speech code never opens the microphone itself while recognising, and the
// recogniser and the "record yourself" recorder take turns (claimMicrophone()).

/** The parts of navigator that tell a phone or tablet apart. */
export interface DeviceHints {
    userAgent: string;
    platform?: string;
    maxTouchPoints?: number;
    userAgentData?: { mobile?: boolean; platform?: string };
}

declare global {
    interface Navigator {
        /** User-Agent Client Hints (Chromium); not in TypeScript's DOM types yet. */
        userAgentData?: { mobile?: boolean; platform?: string };
    }
}

/**
 * True on a phone or tablet (Android, iPhone, iPad). Decided from the user agent because what
 * matters is the operating system's audio policy, which no feature test can reveal:
 * - Client Hints first (Chromium): `mobile` is true on Android phones, and `platform` is "Android"
 *   on Android tablets too, where Chrome reports `mobile: false`;
 * - then the user agent string: "Android", "iPhone", "iPad", "iPod";
 * - iPadOS Safari calls itself a Mac ("MacIntel"); only the touch screen gives it away.
 */
export function isMobileDevice(
    hints: DeviceHints | undefined = typeof navigator === 'undefined'
        ? undefined
        : navigator,
): boolean {
    if (!hints) {
        return false;
    }

    const data = hints.userAgentData;

    if (data?.mobile === true || /android/i.test(data?.platform ?? '')) {
        return true;
    }

    if (/Android|iPhone|iPad|iPod/i.test(hints.userAgent)) {
        return true;
    }

    return hints.platform === 'MacIntel' && (hints.maxTouchPoints ?? 0) > 1;
}

// Stops whoever holds the microphone now (a recogniser or a recording), on phones only.
let holder: (() => void) | null = null;

/** True on a phone or tablet while a recogniser or a recording holds the microphone. */
export function microphoneInUse(): boolean {
    return holder !== null;
}

/**
 * Takes the microphone for a new user on a phone or tablet: the previous user is stopped first.
 * Returns the function to call when the new user is done. On a desktop it does nothing.
 */
export function claimMicrophone(stop: () => void): () => void {
    if (!isMobileDevice()) {
        return () => {};
    }

    const previous = holder;
    holder = stop;

    if (previous && previous !== stop) {
        previous();
    }

    return () => {
        if (holder === stop) {
            holder = null;
        }
    };
}
