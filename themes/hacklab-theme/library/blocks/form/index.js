import { applyMasks } from '../../../assets/javascript/shared/masks'

window.setTimeout(() => {
    applyMasks()

    const searchParams = new URLSearchParams(window.location.search)

    if (searchParams.has('tab')) {
        const initialTab = searchParams.get('tab')

        document.querySelectorAll('.tabs-nav .tab-title[data-title-tab-id]').forEach((tabEl) => {
            if (tabEl.dataset.titleTabId === initialTab) {
                tabEl.classList.add('active')
            } else {
                tabEl.classList.remove('active')
            }
        })

        document.querySelectorAll('.tabs-content .single-tab[data-tab-id]').forEach((tabEl) => {
            if (tabEl.dataset.tabId === initialTab) {
                tabEl.classList.add('active')
                tabEl.style.display = 'block'
            } else {
                tabEl.classList.remove('active')
                tabEl.style.display = 'none'
            }
        })
    }
}, 100)
