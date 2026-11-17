// The app may live under a sub-path (e.g. https://golden.msu.ac.th/skdiit). The server writes that
// path into <meta name="base-path"> (empty on localhost), and every root-relative URL in the code
// ("/admin/...", "/img/...") goes through url() so it gets the prefix. Safe to call twice.
export const basePath = (document.querySelector('meta[name="base-path"]')?.content ?? '').replace(/\/+$/, '');

const hasBase = (path) => path === basePath || path.startsWith(`${basePath}/`) || path.startsWith(`${basePath}?`);

/** Prefix a root-relative path with the base path. Leaves absolute, protocol and relative URLs alone. */
export function url(path) {
    if (!basePath || typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//') || hasBase(path)) {
        return path;
    }
    return basePath + path;
}

/** Same as url() for a full URL string on this origin (Inertia sends absolute URLs). */
export function urlAbsolute(href) {
    if (!basePath || typeof href !== 'string') return href;
    try {
        const parsed = new URL(href, window.location.origin);
        if (parsed.origin !== window.location.origin || hasBase(parsed.pathname)) return href;
        parsed.pathname = basePath + parsed.pathname;
        return /^https?:/i.test(href) ? parsed.href : parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return href;
    }
}

/** The path without the base path, for comparing against app routes ("/admin/..."). */
export function stripBase(path) {
    if (!basePath || typeof path !== 'string' || !hasBase(path)) return path;
    return path.slice(basePath.length) || '/';
}
