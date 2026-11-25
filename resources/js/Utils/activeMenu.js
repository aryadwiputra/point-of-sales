export function pathOf(url = "") {
    const path = String(url).split(/[?#]/)[0];
    if (path.length > 1 && path.endsWith("/")) {
        return path.replace(/\/+$/, "");
    }
    return path || "/";
}

export function matchesHref(path, href) {
    const target = pathOf(href);
    if (!target || target === "/") return false;
    if (target === "/dashboard") return path === "/dashboard";
    return path === target || path.startsWith(`${target}/`);
}

export function resolveActiveHref(items, url) {
    const path = pathOf(url);
    let best = null;

    items.forEach((item) => {
        const href = item?.href;
        if (!href || !matchesHref(path, href)) return;
        if (!best || pathOf(href).length > pathOf(best).length) {
            best = href;
        }
    });

    return best;
}