/**
 * Returns a debounced version of the given function.
 * The returned function delays invoking `fn` until after `delayMs` milliseconds
 * have elapsed since the last invocation.
 */
function debounce<T extends (...args: Parameters<T>) => void>(
    fn: T,
    delayMs: number,
): (...args: Parameters<T>) => void {
    let timerId: ReturnType<typeof setTimeout> | undefined;

    return (...args: Parameters<T>): void => {
        clearTimeout(timerId);
        timerId = setTimeout(() => {
            fn(...args);
        }, delayMs);
    };
}

export { debounce };
