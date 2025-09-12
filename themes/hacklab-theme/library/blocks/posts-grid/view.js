import React, { useEffect, useMemo, useState } from 'react'
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

    const paginationEnabled = (config?.attributes?.enablePagination ?? true) === true

    useEffect(() => {
        let cancel = false;

        (async () => {
            setLoading(true)

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
                !cancel && setLoading(false)
            }
        })()

        return () => { cancel = true }
    }, [page])

    return (
        <div className="hacklabr-posts-grid__app">
            {loading && <div className="hacklabr-posts-grid__loading" aria-live="polite">Carregando…</div>}
            <div className="hacklabr-posts-grid__content" dangerouslySetInnerHTML={{ __html: html }} />

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
