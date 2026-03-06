const MILLISECONDS_PER_DAY = 86_400_000;

/**
 * Returns a normalized Date object set to midnight (local time) for the given date string.
 */
function toMidnight(date: string): Date {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

/**
 * Returns today's Date set to midnight (local time).
 */
function today(): Date {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
}

/**
 * Formats an ISO date string into a human-readable local date.
 * Example: "2026-03-06" → "6 March 2026"
 */
function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Returns true when the given ISO date string represents a date before today.
 */
function isOverdue(date: string): boolean {
    return toMidnight(date) < today();
}

/**
 * Returns true when the given ISO date string represents today's date.
 */
function isToday(date: string): boolean {
    return toMidnight(date).getTime() === today().getTime();
}

/**
 * Returns true when the given ISO date string falls within the current calendar week
 * (Monday–Sunday, local time), excluding today and past dates.
 */
function isThisWeek(date: string): boolean {
    const target = toMidnight(date);
    const now = today();

    const dayOfWeek = now.getDay();
    const distanceToMonday = (dayOfWeek === 0 ? -6 : 1 - dayOfWeek) * MILLISECONDS_PER_DAY;
    const weekStart = new Date(now.getTime() + distanceToMonday);
    const weekEnd = new Date(weekStart.getTime() + 6 * MILLISECONDS_PER_DAY);

    return target >= weekStart && target <= weekEnd;
}

/**
 * Returns a time-appropriate greeting string based on the current hour.
 */
function getGreeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

export { formatDate, isOverdue, isToday, isThisWeek, getGreeting };
