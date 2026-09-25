import { __ } from '@wordpress/i18n'
import { applyMasks } from '../shared/masks'
import { getAddressByCep, applyAddressToForm } from '../shared/address'

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

let cepHintTimeout = null

document.addEventListener('DOMContentLoaded', () => {
    applyMasks()

    const cepHint = document.querySelector('#end_cep__hint')
    if (cepHint) {
        cepHint.dataset.default = cepHint.textContent
    }

    document.querySelector('#cnpj')?.addEventListener('hacklabr:change', async (event) => {
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

    document.querySelector('#end_cep')?.addEventListener('hacklabr:change', async (event) => {
        const cep = String(event.data ?? '').replace(/\D/g, '')

        if (cep.length !== 8) {
            return
        }

        const hint = document.querySelector('#end_cep__hint')

        try {
            if (hint) {
                clearTimeout(cepHintTimeout)
                hint.textContent = __('Searching address...', 'hacklabr')
            }

            const address = await getAddressByCep(cep)
            applyAddressToForm(address)

            if (hint) {
                hint.textContent = hint.dataset.default ?? ''
            }
        } catch (err) {
            if (err.name === 'AbortError') {
                return
            }

            if (hint) {
                hint.textContent = __('Address not found for this ZIP code. Please fill it in manually.', 'hacklabr')
                cepHintTimeout = setTimeout(() => {
                    hint.textContent = hint.dataset.default ?? ''
                }, 6000)
            }
        }
    })
})
