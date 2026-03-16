document.addEventListener('DOMContentLoaded', () => {
    const { plan } = globalThis.hl_my_plan_data

    const spotColumn = (id) => {
        document.querySelectorAll(`table td:nth-child(${id}), table th:nth-child(${id})`).forEach((td) => {
            td.classList.add('my-plan__featured-column')
        })
    }

    switch ( plan ) {
        case 'institucional':
            spotColumn(5)
            break
        case 'vivencia':
            spotColumn(4)
            break
        case 'essencial':
            spotColumn(3)
            break
        case 'conexao':
        default:
            spotColumn(2)
            break
    }
})
