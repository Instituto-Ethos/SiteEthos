import { applyMasks } from '../shared/masks'

const { baseUrl } = globalThis.hl_event_registration_data

async function restPost (endpointUrl, args) {
    const url = new URL(endpointUrl, baseUrl)
    const res = await fetch(url, {
        method: 'POST',
        body: new URLSearchParams(args),
    })
    if (res.ok) {
        return res.json()
    } else {
        throw res.json()
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyMasks()

    document.querySelector('#cnpj').addEventListener('hacklabr:change', async (event) => {
        const cnpj = event.data
        if (cnpj.length === 14) {
            try {
                const res = await restPost('events/cnpj', { cnpj })
                if (res) {
                    document.querySelector('#nome_fantasia').value = res.nome_fantasia ?? ''
                }
            } catch (err) {
                console.error(err)
            }
        }
    })
})
