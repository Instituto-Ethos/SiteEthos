import React, { useEffect, useMemo, useRef, useState } from 'react'
import { createRoot } from 'react-dom/client'
import Pagination from './components/Pagination'

async function fetchPage({ root, nonce, page, attributes }) {
    const res = await fetch(`${root}posts-grid`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce || '' },
        body: JSON.stringify({ page, attributes })
    })

    if (!res.ok) throw new Error('Fetch failed')
    return res.json()
}

function useConfig(element) {
    return useMemo(() => {
        try { return JSON.parse(element.getAttribute('data-config') || '{}') }
        catch { return {} }
    }, [element])
}

function PostsGrid({ element }) {

    const config = useConfig(element)

    const [page, setPage] = useState(1)
    const [html, setHtml] = useState(element.querySelector('.hacklabr-posts-grid-block')?.outerHTML || '')
    const [totalPages, setTotalPages] = useState(1)
    const [loading, setLoading] = useState(false)
    const [placeholderHeight, setPlaceholderHeight] = useState(0)

    const contentRef = useRef(null)

    const paginationEnabled = (config?.attributes?.enablePagination ?? true) === true

    useEffect(() => {
        let cancel = false

        const measure = () => {
            const contentEl = contentRef.current
            if (!contentEl) return 0
            const grid = contentEl.querySelector('.hacklabr-posts-grid-block')
            const h = (grid?.offsetHeight || contentEl.offsetHeight || 0)
            return h > 0 ? h : 160
        }
        setPlaceholderHeight(measure())
        setLoading(true);

        (async () => {
                try {
                    const data = await fetchPage({
                        root: config.rest.root,
                        nonce: config.rest.nonce,
                        page,
                        attributes: config.attributes
                    })
                    if (cancel) return
                    setHtml(data.html)
                    setTotalPages(data.totalPages || 1)
                } finally {
                    if (!cancel) {
                        setLoading(false)
                        setPlaceholderHeight(0)
                    }
                }
            })()

        return () => { cancel = true }
    }, [page])

    return (
        <div className="hacklabr-posts-grid__app">
            <div className="hacklabr-posts-grid__viewport">
                <div
                    ref={contentRef}
                    className={`${loading ? 'hacklabr-posts-grid__content hacklabr-posts-grid__content__loading' : 'hacklabr-posts-grid__content'}`}
                    style={loading && placeholderHeight ? { height: `${placeholderHeight}px` } : undefined}
                    dangerouslySetInnerHTML={{ __html: html }}
                />
                {loading && (
                    <div className="hacklabr-posts-grid__overlay">
                        <div className="loading-spinner" />
                    </div>
                )}
            </div>

            {paginationEnabled && totalPages > 1 && (
                <Pagination
                    page={page}
                    total={totalPages}
                    onGo={setPage}
                    siblingCount={1}
                    boundaryCount={1}
                />
            )}
        </div>
    )
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.hacklabr-posts-grid__root').forEach((element) => {
        createRoot(element).render(<PostsGrid element={element} />)
    })
})
