(function() {
    const input = document.getElementById('organization-search')
    const listWrapper = document.getElementById('organization-list-wrapper')
    const statusLive = document.getElementById('organization-search-status')
    if (!input || !listWrapper) return;

    const baseUrl = window.hl_search_organizations_data.baseUrl ?
        window.hl_search_organizations_data.baseUrl + 'organizations/' :
        '/wp-json/wp/v2/organizations';

    const nonce = window.hl_search_organizations_data?.nonce || null;

    let lastController = null

    const setStatus = (msg) => {
        if (statusLive) statusLive.textContent = msg || ''
    }

    const escapeHTML = (str) =>
        String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderList = (items) => {
        const ul = document.createElement('ul')
        ul.id = 'organization-list'
        ul.className = 'organization-list'

        if (!items || !items.length) {
            listWrapper.innerHTML = '<p id="organization-list">Nenhuma organização encontrada.</p>'
            return;
        }

        items.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'organization-card';
            const a = document.createElement('a');
            a.className = 'organization-card__link';
            a.href = item.link || (`/associados/boas-vindas/?organization=${item.id}`);
            a.innerHTML = escapeHTML(item.title && item.title.rendered ? item.title.rendered : item.title || '—');
            li.appendChild(a);
            ul.appendChild(li);
        });

        listWrapper.innerHTML = '';
        listWrapper.appendChild(ul);
    }

    const showLoading = () => {
        setStatus('Buscando…');
    }

    const clearLoading = () => {
        setStatus('');
    }

    const debounce = (fn, wait = 300) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(null, args), wait)
        };
    };

    const search = async (term) => {
        if (lastController) lastController.abort()
        lastController = new AbortController()
        const signal = lastController.signal

        const url = new URL(baseUrl, window.location.origin)
        url.searchParams.set('s', term)

        showLoading()
        try {
            const res = await fetch(url.toString(), {
                signal,
                credentials: 'same-origin',
                headers: nonce ? {
                    'X-WP-Nonce': nonce
                } : undefined
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`)
            const data = await res.json()
            const items = Array.isArray(data) ? data : []
            renderList(items)
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('[organization-search] erro:', err)
            listWrapper.innerHTML = '<p id="organization-list">Erro ao buscar. Tente novamente.</p>'
        } finally {
            clearLoading()
        }
    }

    const handleInput = debounce((ev) => {
        const term = ev.target.value.trim()
        if (term.length === 0) {
            setStatus('')
            return
        }
        search(term)
    }, 300)

    input.addEventListener('input', handleInput)
})()
