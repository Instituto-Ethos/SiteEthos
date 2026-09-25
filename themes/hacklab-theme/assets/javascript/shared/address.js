const VIA_CEP_URL = 'https://viacep.com.br/ws/'

let currentRequest = null

export async function getAddressByCep (cep) {
    const digits = String(cep).replace(/\D/g, '')

    if (digits.length !== 8) {
        throw new Error('invalid_cep')
    }

    currentRequest?.abort()
    currentRequest = new AbortController()

    let response

    try {
        response = await fetch(`${VIA_CEP_URL}${digits}/json/`, { signal: currentRequest.signal })
    } catch (err) {
        if (err.name === 'AbortError') {
            throw err
        }
        throw new Error('request_failed')
    }

    if (!response.ok) {
        throw new Error('request_failed')
    }

    const address = await response.json()

    if (address.erro) {
        throw new Error('not_found')
    }

    return address
}

function setFieldValue (selector, value, { overwrite = true } = {}) {
    const field = document.querySelector(selector)

    if (!field) {
        return null
    }

    if (overwrite || !field.value) {
        field.value = value
        field.dispatchEvent(new Event('change', { bubbles: true }))
    }

    return field
}

export function applyAddressToForm (address) {
    setFieldValue('input#end_logradouro', address.logradouro ?? '')
    setFieldValue('input#end_bairro', address.bairro ?? '')
    setFieldValue('input#end_cidade', address.localidade ?? '')
    setFieldValue('select#end_estado', address.uf ?? '')
    setFieldValue('input#end_complemento', address.complemento ?? '', { overwrite: false })

    const numberField = document.querySelector('input#end_numero')
    numberField?.focus()
}
