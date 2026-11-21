// Pictures for vocabulary words: the thumbnail of the word's English Wikipedia article.

type ImageCache = Record<string, string | null>;

// Remembered in this browser: the URL of the picture, or null when the article has none.
const STORAGE_KEY = 'el-img';

let memory: ImageCache | null = null;

function cache(): ImageCache {
    if (!memory) {
        try {
            memory = JSON.parse(
                localStorage.getItem(STORAGE_KEY) || '{}',
            ) as ImageCache;
        } catch {
            memory = {};
        }
    }

    return memory;
}

interface PageSummary {
    thumbnail?: { source?: string };
}

/** The picture of a Wikipedia article, or null when there is none (or Wikipedia cannot be reached). */
export async function wikiImage(
    title: string | null | undefined,
): Promise<string | null> {
    if (!title) {
        return null;
    }

    const known = cache();

    if (title in known) {
        return known[title];
    }

    try {
        const response = await fetch(
            `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(title.replace(/ /g, '_'))}`,
        );

        // Busy or offline: try again another time. A 404 is an answer: no such article.
        if (!response.ok && response.status !== 404) {
            return null;
        }

        const page = response.ok
            ? ((await response.json()) as PageSummary)
            : null;
        known[title] = page?.thumbnail?.source || null;

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(known));
        } catch {
            // Storage full: the picture is simply fetched again next time.
        }

        return known[title];
    } catch {
        return null;
    }
}
