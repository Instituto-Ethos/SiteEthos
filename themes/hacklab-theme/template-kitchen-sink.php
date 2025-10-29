<?php
/**
 * Template Name: Component Inventory
 */
get_header();
?>

<div class="container kitchen-sink">
    <code>Títulos</code>
    <h1>&lt;h1&gt; Título 1</h1>
    <h2>&lt;h2&gt; Título 2</h2>
    <h3>&lt;h3&gt; Título 3</h3>
    <h4>&lt;h4&gt; Título 4</h4>
    <h5>&lt;h5&gt; Título 5</h5>
    <h6>&lt;h6&gt; Título 6</h6>
    <p>&lt;p&gt; Parágrafo</p>

    <hr/>s
    <code>Ícones</code>
    <iconify-icon icon="fa6-brands:wordpress-simple"></iconify-icon>
    <iconify-icon icon="skill-icons:instagram"></iconify-icon>
    <iconify-icon icon="iconamoon:arrow-right-2-bold"></iconify-icon>

    <hr/>
    <code>Botões</code>
    <button type="button" class="button">Default</button>
    <button type="button" class="button button--solid">Solid</button>
    <button type="button" class="button button--outline">Outline</button>
    <button type="button" class="button button--solid button--large">Large</button>
    <button type="button" class="button button--solid button--small">Small</button>
    <button type="button" class="button button--solid button--primary">Primary</button>
    <button type="button" class="button button--solid button--secondary">Secondary</button>
    <button type="button" class="button button--solid button--highlight">Highlight</button>
    <button type="button" class="button button--outline button--warning">Warning</button>
    <button type="button" class="button button--solid button--helper">Helper</button>

    <hr/>
    <code>Site By Hacklab</code>
    <div class="site-by-hacklab">
        <div class="container">
            <a href="https://hacklab.com.br">site por <strong>hacklab<span>/</span></strong></a>
        </div>
    </div>

    <hr/>

    <code>Stack</code>
    <div class="stack stack--large">
        <div class="stack">
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
        </div>

        <div class="stack stack--small">
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
        </div>

        <div class="stack stack--large">
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
            <div class="kitchen-sink__stack-item">Item</div>
        </div>
    </div>

    <hr/>

    <code>Formulário</code>
    <div class="form-field">
        <label for="input-1" class="form-field__label">Texto</label>
        <input id="input-1" class="text-input" type="text">
    </div>

    <div class="form-field">
        <label for="select-1" class="form-field__label">Select</label>
        <select id="select-1" class="select">
            <option>Opção 1</option>
            <option>Opção 2</option>
        </select>
    </div>

    <div class="form-field">
        <label for="checkbox-1" class="form-field__label">Checkbox 1</label>
        <input id="checkbox-1" class="checkbox" type="checkbox">
    </div>

    <div class="form-field">
        <input id="checkbox-2" class="checkbox" type="checkbox">
        <label for="checkbox-2" class="form-field__label">Checkbox 2</label>
    </div>

    <div class="form-field">
        <label for="radio-1" class="form-field__label">Radio 1</label>
        <input id="radio-1" class="radio" type="radio">
    </div>

    <div class="form-field">
        <input id="radio-2" class="radio" type="radio">
        <label for="radio-2" class="form-field__label">Radio 2</label>
    </div>

    <hr/>

    <div class="form-field form-field--disabled">
        <label for="input-2" class="form-field__label">Texto</label>
        <input id="input-2" class="text-input text-input--disabled" value="Desabilitado" type="text" disabled>
    </div>

    <div class="form-field form-field--readonly">
        <label for="input-2" class="form-field__label">Texto</label>
        <input id="input-2" class="text-input text-input--readonly" value="Somente leitura" type="text" readonly>
    </div>

    <div class="form-field form-field--disabled">
        <label for="select-2" class="form-field__label">Select</label>
        <select id="select-2" class="select select--disabled" disabled>
            <option>Opção 1</option>
            <option>Opção 2</option>
        </select>
    </div>

    <div class="form-field form-field--disabled">
        <label for="checkbox-3" class="form-field__label">Checkbox</label>
        <input id="checkbox-3" class="checkbox checkbox--disabled" type="checkbox" disabled>
    </div>

    <div class="form-field form-field--disabled">
        <label for="radio-3" class="form-field__label">Radio</label>
        <input id="radio-3" class="radio radio--disabled" type="radio" disabled>
    </div>

    <hr/>

    <div class="form-field form-field--error">
        <label for="input-4" class="form-field__label">Texto</label>
        <input id="input-4" class="text-input text-input--error" type="text">
    </div>

    <div class="form-field form-field--error">
        <label for="select-3" class="form-field__label">Select</label>
        <select id="select-3" class="select select--error">
            <option>Opção 1</option>
            <option>Opção 2</option>
        </select>
    </div>

    <div class="form-field form-field--error">
        <label for="checkbox-4" class="form-field__label">Checkbox</label>
        <input id="checkbox-4" class="checkbox checkbox--error" type="checkbox">
    </div>

    <div class="form-field form-field--error">
        <label for="radio-4" class="form-field__label">Radio</label>
        <input id="radio-4" class="radio radio--error" type="radio">
    </div>

    <hr/>

    <code>object: hamburger, open, toggle</code>
    <svg class="hamburger" role="img" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg">
        <title>Exibir menu</title>
        <rect width="16" height="2" x="0" y="2"/>
        <rect width="16" height="2" x="0" y="7"/>
        <rect width="16" height="2" x="0" y="12"/>
    </svg>

    <svg class="hamburger hamburger--open" role="img" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg">
        <title>Ocultar menu</title>
        <rect width="16" height="2" x="0" y="2"/>
        <rect width="16" height="2" x="0" y="7"/>
        <rect width="16" height="2" x="0" y="12"/>
    </svg>

    <svg x-data="{ open: false }" class="hamburger" :class="{ 'hamburger--open': open }" role="img" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" @click="open = !open">
        <rect width="16" height="2" x="0" y="2"/>
        <rect width="16" height="2" x="0" y="7"/>
        <rect width="16" height="2" x="0" y="12"/>
    </svg>

    <hr/>

    <code>componente: card default</code>
    <?php get_template_part( 'template-parts/post-card', null, ['post' => '1'] ); ?>

    <hr/>

    <code>componente: card vertical</code>
    <?php get_template_part( 'template-parts/post-card', 'vertical', ['post' => '1'] ); ?>

    <hr/>

    <code>componente: card horizontal</code>
    <?php get_template_part( 'template-parts/post-card', 'horizontal', ['post' => '1'] ); ?>

    <hr/>

    <code>componente: card cover</code>
    <?php get_template_part( 'template-parts/post-card', 'cover', ['post' => '1'] ); ?>

    <hr/>

    <!-- <div class="accordeon">
        <details class="accordeon__item">
            <summary class="accordeon__header">Accordeon Title</summary>
            <div class="accordeon__content">Content</div>
        </details>
        <details class="accordeon__item">
            <summary class="accordeon__header">Accordeon Title</summary>
            <div class="accordeon__content">Content</div>
        </details>
        <details class="accordeon__item">
            <summary class="accordeon__header">Accordeon Title</summary>
            <div class="accordeon__content">Content</div>
        </details>
    </div> -->

    <div class="wp-block-coblocks-accordion">
        <div class="wp-block-coblocks-accordion-item">
            <details>
                <summary class="wp-block-coblocks-accordion-item__title">Accordeon Title</summary>
                <div class="wp-block-coblocks-accordion-item__content">
                    <p>Conteudo</p>
                </div>
            </details>
        </div>
        <div class="wp-block-coblocks-accordion-item">
            <details>
                <summary class="wp-block-coblocks-accordion-item__title">Accordeon Title</summary>
                <div class="wp-block-coblocks-accordion-item__content">
                    <p>Conteudo</p>
                </div>
            </details>
        </div>
        <div class="wp-block-coblocks-accordion-item">
            <details>
                <summary class="wp-block-coblocks-accordion-item__title">Accordeon Title</summary>
                <div class="wp-block-coblocks-accordion-item__content">
                    <p>Conteudo</p>
                </div>
            </details>
        </div>
    </div>


    <hr/>

    <h2>Tags</h2>
    <a class="tag tag--solid" href="#">Teste</a>
    <a class="tag tag--solid tag--primary" href="/category/saude">Teste</a>
    <a class="tag tag--outline tag--highlight" href="#">Teste</a>
    <span class="tag tag--solid tag--secondary">Teste</span>
    <span class="tag tag--solid tag--helper">Teste</span>
    <a class="tag tag--outline tag--warning" href="/category/saude">Teste</a>

    <hr/>

    <code>Navegação</code>
    <nav class="navigation pagination" aria-label="Posts">
        <h2 class="screen-reader-text">Navegação por posts</h2>
        <div class="nav-links">
            <a class="prev page-numbers" href="#/3/">
                <
            </a>
            <a class="page-numbers" href="https://midia.ninja/noticias/">1</a>
            <a class="page-numbers" href="#/2/">2</a>
            <a class="page-numbers" href="#/3/">3</a>
            <span aria-current="page" class="page-numbers current">4</span>
            <a class="page-numbers" href="#/5/">5</a>
            <a class="page-numbers" href="#/6/">6</a>
            <span class="page-numbers dots">…</span>
            <a class="page-numbers" href="#/1274/">1.274</a>
            <a class="next page-numbers" href="#/5/">
                >
            </a>
        </div>
    </nav>

    <hr/>

    <code>Tabelas</code>
    <table class="table">
        <thead class="table__header">
            <tr class="table__header-row">
                <th class="table__header-cell">Lorem ipsum</th>
                <th class="table__header-cell">consectetur</th>
                <th class="table__header-cell">adipiscing</th>
            </tr>
        </thead>

        <tbody class="table__body">
            <tr class="table__row">
                <td class="table__cell">Lorem ipsum dolor sit amet, consectetur adipiscing elit, lorem ipsum dolor sit amet, consectetur adipiscing elit,Lorem ipsum dolor sit amet, consectetur adipiscing elit.</td>
                <td class="table__cell">magna</td>
                <td class="table__cell">aliqua</td>
            </tr>
            <tr class="table__row">
                <td class="table__cell">Lorem ipsum dolor sit amet, adipiscing elit</td>
                <td class="table__cell">aliqua</td>
                <td class="table__cell">magna</td>
            </tr>
            <tr class="table__row">
                <td class="table__cell">Lorem ipsum dolor sit amet, adipiscing elit</td>
                <td class="table__cell">aliqua</td>
                <td class="table__cell">magna</td>
            </tr>
        </tbody>
    </table>

    <hr/>

    <code>Tabs</code>
    <div class="tabs" x-data="{ currentTab: 1 }" x-bind="Tabs($data)">
        <div class="tabs__header" role="tablist">
            <button class="tab" x-bind="TabButton(1, $data)">
                Aba 1 (selecionada)
            </button>
            <button class="tab tab--disabled" disabled aria-disabled="true" x-bind="TabButton(2, $data)">
                Aba 2 (desabilitada)
            </button>
            <button class="tab" x-bind="TabButton(3, $data)">
                Aba 3
            </button>
        </div>
        <div class="tabs__panels">
            <div class="tabs__panel" x-bind="TabPanel(1, $data)">
                Conteúdo da aba 1 (selecionada)
            </div>
            <div class="tabs__panel" x-bind="TabPanel(2, $data)">
                Conteúdo da aba 2 (desabilitada)
            </div>
            <div class="tabs__panel" x-bind="TabPanel(3, $data)">
                Conteúdo da aba 3
            </div>
        </div>
    </div>

    <hr/>

    <code>Container com sidebar</code>
    <div class="content-sidebar">
        <div class="container container--wide content-sidebar__container">
            <aside class="content-sidebar__sidebar">
                <ul>
                    <li>Item 1</li>
                    <li>Item 2</li>
                    <li>Item 3</li>
                    <li>Item 4</li>
                </ul>
            </aside>
            <main class="content-sidebar__content">
                <h1>Título</h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam auctor, felis eu pulvinar congue, ligula nunc congue quam, a tincidunt metus lacus quis tellus. Nulla facilisi. Praesent auctor, tortor sed consequat consequat, magna elit congue augue, eu vehicula nulla augue vel neque. Vivamus ut metus in metus bibendum tincidunt. Nullam euismod, felis sit amet tincidunt congue, augue odio scelerisque metus, a dignissim augue velit a risus. Sed vitae erat in purus condimentum congue. Nulla facilisi. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Praesent vel nibh et velit ultricies congue. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Pellentesque habitant morbi.</p>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam auctor, felis eu pulvinar congue, ligula nunc congue quam, a tincidunt metus lacus quis tellus. Nulla facilisi. Praesent auctor, tortor sed consequat consequat, magna elit congue augue, eu vehicula nulla augue vel neque. Vivamus ut metus in metus bibendum tincidunt. Nullam euismod, felis sit amet tincidunt congue, augue odio scelerisque metus, a dignissim augue velit a risus. Sed vitae erat in purus. </p>
            </main>
        </div>
    </div>
    <div class="thumbnail">
        <img src="<?= get_template_directory_uri() ?>/screenshot.png" alt="">
    </div>
    <div class="thumbnail thumbnail--square">
        <img src="<?= get_template_directory_uri() ?>/screenshot.png" alt="">
    </div>
    <main class="book-detail">
        <aside>
            <ul>
                <li>Item 1</li>
                <li>Item 2</li>
            </ul>
        </aside>
        <div class="container">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam auctor, felis eu pulvinar congue, ligula nunc congue quam, a tincidunt metus lacus quis tellus. Nulla facilisi. Praesent auctor, tortor sed consequat consequat, magna elit congue augue, eu vehicula nulla augue vel neque. Vivamus ut metus in metus bibendum tincidunt. Nullam euismod, felis sit amet tincidunt congue, augue odio scelerisque metus, a dignissim augue velit a risus. Sed vitae erat in purus.</p>
        </div>
    </main>

    <hr/>

    <code>Lista de porte das empresas</code>
    <div class="company-size">
        <div class="company-size__grid">
            <div class="company-size__item">
                <div class="company-size__percent">41%</div>
                <div class="company-size__label">Micro e pequenas empresas</div>
            </div>
            <div class="company-size__item">
                <div class="company-size__percent">20%</div>
                <div class="company-size__label">Médias empresas</div>
            </div>
            <div class="company-size__item">
                <div class="company-size__percent">38%</div>
                <div class="company-size__label">Grandes empresas</div>
            </div>
        </div>
    </div>

</div>

<?php get_footer(); ?>
