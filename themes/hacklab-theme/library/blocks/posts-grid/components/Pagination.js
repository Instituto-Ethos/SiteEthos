import React from 'react';

function buildPageItems({ page, total, siblingCount = 1, boundaryCount = 1 }) {
    if (total <= 0) return []

    const inRange = (n) => n >= 1 && n <= total
    const set = new Set()

    for (let i = 1; i <= boundaryCount; i++) if (inRange(i)) set.add(i)

    for (let i = total - boundaryCount + 1; i <= total; i++)
        if (inRange(i)) set.add(i);

    for (let i = page - siblingCount; i <= page + siblingCount; i++)
        if (inRange(i)) set.add(i);

    set.add(Math.min(Math.max(page, 1), total))

    const nums = Array.from(set).sort((a, b) => a - b)

    const items = []
    let prev = null

    for (const n of nums) {
        if (prev !== null && n - prev > 1) items.push('ellipsis')
        items.push(n)
        prev = n
    }

    return items
}

export default function Pagination({
    page,
    total,
    onGo,
    siblingCount = 1,
    boundaryCount = 1,
    className = 'hacklabr-posts-grid__pagination',
}) {
    if (!total || total <= 1) return null

    const safeGo = (n) => onGo(Math.max(1, Math.min(total, n)))
    const items = buildPageItems({ page, total, siblingCount, boundaryCount })

    return (
        <nav className={className}>
            <button
                className={`${className}__prev`}
                type="button"
                onClick={() => safeGo(page - 1)}
                disabled={page <= 1}
            >
                <svg viewBox="0 0 24 24" width="1em" height="1em">
                    <path d="M15 18L9 12l6-6" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
            </button>

            {items.map((it, idx) => {
                if (it === 'ellipsis') {
                    return (
                        <span key={`ell-${idx}`} className={`${className}__ellipsis`} aria-hidden="true">
                            …
                        </span>
                    );
                }
                const n = it;
                const isCurrent = n === page;
                return (
                    <button
                        type="button"
                        key={`p-${n}`}
                        onClick={() => safeGo(n)}
                        disabled={isCurrent}
                        aria-current={isCurrent ? 'page' : undefined}
                    >
                        {n}
                    </button>
                );
            })}

            <button
                className={`${className}__next`}
                type="button"
                onClick={() => safeGo(page + 1)}
                disabled={page >= total}
            >
                <svg viewBox="0 0 24 24" width="1em" height="1em">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
            </button>
        </nav>
    )
}
