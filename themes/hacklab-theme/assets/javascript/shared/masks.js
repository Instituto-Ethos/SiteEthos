import IMask from 'imask/holder'
import 'imask/masked/dynamic'
import 'imask/masked/number'
import 'imask/masked/pattern'

const CURRENCY_MASK = {
    mask: 'R$ num',
    blocks: {
        num: {
            mask: Number,
            mapToRadix: ['.'],
            normalizeZeros: true,
            padFractionalZeros: true,
            radix: ',',
            scale: 2,
            thousandsSeparator: '.',
        },
    },
}

function parseMask (mask) {
    const parts = mask.split('|')
    if (parts.length === 1) {
        if (parts[0] === '__currency__') {
            return CURRENCY_MASK
        } else {
            return { mask: parts[0] }
        }
    } else {
        return { mask: parts.map((part) => ({ mask: part })) }
    }
}

export function applyMasks () {
    document.querySelectorAll('input[data-mask]').forEach((maskedEl) => {
        const unmaskedEl = document.querySelector(`input#${maskedEl.id.replace('__mask', '')}`)

        const maskPattern = parseMask(maskedEl.dataset.mask)

        const mask = IMask(maskedEl, maskPattern)
        unmaskedEl._mask = mask
        mask.unmaskedValue = unmaskedEl.value

        maskedEl.addEventListener('change', () => {
            unmaskedEl.value = mask.unmaskedValue

            const unmaskEvent = new Event('hacklabr:change')
            unmaskEvent.data = mask.unmaskedValue
            unmaskedEl.dispatchEvent(unmaskEvent)
        })
    })
}
