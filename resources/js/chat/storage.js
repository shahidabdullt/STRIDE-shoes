/**
 * Browser storage that never throws (private windows and blocked storage throw on access).
 * Only per-browser conveniences live here: the current chat id and whether the panel was open.
 */
export function safeStorage(getStore) {
    const store = () => {
        try {
            return getStore();
        } catch {
            return null;
        }
    };

    return {
        get(key) {
            try {
                return store()?.getItem(key) ?? null;
            } catch {
                return null;
            }
        },
        set(key, value) {
            try {
                store()?.setItem(key, value);
            } catch {
                // Storage full or blocked: the chat still works, it just won't survive a reload.
            }
        },
        remove(key) {
            try {
                store()?.removeItem(key);
            } catch {
                // Ignore, as above.
            }
        },
    };
}
