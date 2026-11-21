// Calendar days in the learner's own time zone.

/** The local date as "YYYY-MM-DD". */
export function today(d: Date = new Date()): string {
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${month}-${day}`;
}
