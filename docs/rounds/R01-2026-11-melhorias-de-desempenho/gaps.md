# Lacunas de implementação — R01 (épic #282)

Lacunas declaradas pelas fatias de implementação: trabalho necessário que **não pôde ser executado no repo** nesta rodada, com recomendação para retomada.

## #320 — Imagens pesadas fora do repo (media library do WP)

As imagens de maior impacto na home (1st-party = 6,2 MB) residem em `wp-content/uploads/`, **não versionadas no repo**. Sem acesso wp-admin/SSH nesta rodada, a otimização no servidor é lacuna. Pesos conferidos por HTTP HEAD em 2026-11-23 (batem com baseline.md §5):

| Imagem | Peso | Uso na home | Recomendação |
|---|---|---|---|
| [`uploads/2026/11/Capa-do-projeto-2.png`](https://www.ethos.org.br/wp-content/uploads/2026/11/Capa-do-projeto-2.png) | **3.449.659 B (3.369 KB)** | `background-image:url(...)` inline no bloco `wp-block-hacklabr-card-header` perto do topo — atributo de bloco no conteúdo do post (DB), fora de templates do tema | Converter para WebP (~70–85% de redução esperada em PNG ilustrativo) e redimensionar à largura exibida; re-salvar o bloco apontando para o novo arquivo (ou plugin de otimização que sirva WebP no mesmo URL). Por ser background CSS, não há `srcset`/`lazy` — manter abaixo de ~300 KB |
| [`uploads/2024/05/Rectangle-419.png`](https://www.ethos.org.br/wp-content/uploads/2024/05/Rectangle-419.png) | **528.010 B (516 KB)** | `<img>` no conteúdo (sem srcset) | Re-upload como WebP/resize |
| [`uploads/2026/08/cartilha_A4-vertical-724x1024.png`](https://www.ethos.org.br/wp-content/uploads/2026/08/cartilha_A4-vertical-724x1024.png) | **257.391 B (251 KB)** | `<img>` com srcset completo (WP já gera tamanhos; falta formato) | Plugin de conversão WebP (Imagify/ShortPixel/perfmatters) para servir WebP nos mesmos srcsets |
| [`uploads/2025/07/site-indicadores-1-768x369.png`](https://www.ethos.org.br/wp-content/uploads/2025/07/site-indicadores-1-768x369.png) | **234.617 B (229 KB)** | idem | idem |
| [`uploads/2026/11/Pesquisa-Ethos-DEI-2025–2026-723x1024.png`](https://www.ethos.org.br/wp-content/uploads/2026/11/Pesquisa-Ethos-DEI-2025%E2%80%932026-723x1024.png) | **233.361 B (228 KB)** | idem | idem |

**Total identificado: ~4,6 MB** em 5 PNGs de `uploads/`. Caminhos que não dependem de acesso ao servidor:

1. **Cloudflare Polish** (planos Pro+; o site já está atrás da Cloudflare com APO) — converte para WebP/AVIF e otimiza na borda, sem mudança no WP. Verificar plano atual antes de ativar.
2. **Otimização de mídia no WP** (plugin) — requer deploy/acesso; converter originais e tamanhos gerados, incluindo a `Capa-do-projeto-2.png` referenciada como background no conteúdo do bloco.

## #319/#320 — Achados menores (fora de escopo das fatias, sem ação nesta rodada)

- `themes/hacklab-theme/assets/images/header/header-editais.png` (141 KB) e `header-perguntas-frequentes.png` (68 KB): **sem nenhuma referência** em PHP/SCSS/CSS do repo — candidatos a exclusão em fatia de limpeza (não carregam em página alguma; sem impacto de rede hoje).
- `template-parts/content/related-posts.php:35`: variável `$thumbnail` computada e **nunca usada** (o `post-card` recalcula a própria) — código morto.
- Fontes TTF (G3, caracterização): preload corrigido na #319 elimina o download duplo, mas a conversão TTF→WOFF2 (~40–60% menores) permanece como melhoria futura (mudança de asset + `@font-face`, escopo maior que a correção cirúrgica da #319).

## #321 — Assets de plugins por página: o que ficou fora

Economia esperada por página (bytes comprimidos, medidos no HTML de produção em 2026-11-23; **não re-medidos pós-deploy**): home ~103 KB · evento ~116 KB · institucional ~72 KB · contato ~63 KB · `/associe-se/` ~51 KB.

1. **Sem verificação em site em execução.** Os plugins de produção (The Events Calendar/PRO, PMPro, CoBlocks, Dynamics 365, CF7…) não estão no repo, então a fatia foi validada estaticamente: `php -l` (PHP 7.4 e 8.3) e um harness que roda `library/plugin-assets.php` contra as classes reais do WP 6.5.3 (`WP_Dependencies`/`WP_Scripts`/`WP_Styles`), com os handles observados no HTML de produção — 20 cenários, 80 verificações, incluindo os roteiros de paridade N1 (páginas do PMPro), N3 (views da agenda) e N7 (formulário CF7). **Antes do deploy**, comparar em homologação a lista de `<link>`/`<script>` de cada página com a esperada e rodar N1, N3, N5 e N7.
2. **Premissa do Swiper a revisar se as configurações mudarem**: o Swiper só é mantido em requisições de *view* da agenda. A view embutida na home por `[tribe_events]` oferece apenas lista e mês (HTML de produção), views que não o usam; se outras views forem habilitadas, revisar.
3. **Não cortados** (ganho pequeno ou sem predicado seguro): `double-opt-in-for-download-plugin-styles` (485 B; o tema conecta-se ao shortcode em `library/utils.php`), `tribe-events-pro-mini-calendar-block-styles` (391 B), `tribe-tooltipster` (1,1 KB; o popup é criado por JS).
4. **Oportunidade maior, fora do escopo**: no single de evento, The Events Calendar/PRO carregam 37 scripts (96 KB) e 30 CSS (68 KB), dos quais 31 scripts de views (91 KB) e 12 CSS de views (33 KB) — os mesmos conjuntos que a agenda navegável usa. O uso desses arquivos pelo template do tema (`tribe-events/single-event.php`) **não foi medido**; exige cobertura por script antes de qualquer corte. `jquery-migrate` (4,9 KB, core) e `jquery` (30 KB) seguem globais.

## #323 — Adiamento de terceiros (G1): mecanismo, evidências e roteiro pós-deploy

**Mecanismo.** `themes/hacklab-theme/library/third-parties.php` (require em `functions.php`): buffer de saída iniciado em `template_redirect` (pri. 1; só frontend GET/HEAD, fora admin/AJAX/CRON/REST) alimenta a função pura `defer_third_parties_rewrite()`, que faz substituições ancoradas e **fail-open** sobre as assinaturas exatas observadas no HTML de produção — se um plugin/snippet mudar, a regra para de casar e a tag original é servida inalterada. Helper inline `window.__ethosLazyLoad` (injetado após `<head>`): `requestIdleCallback` com timeout OU primeira interação (`pointerdown|keydown|wheel|scroll|touchstart`, once, capture) OU cap duro — o que vier primeiro. Kill switches: filtros `hacklabr_defer_third_parties` (mestre) e `hacklabr_defer_accessibility_third_parties` (só UserWay+VLibras).

| Terceiro | Como carregava antes (evidência HTML produção) | Como passa a carregar | Fora do caminho crítico |
|---|---|---|---|
| **GTM** (GTM-KGPSJ48, 655 KB de domínio com gtag/AW/pixel) | Snippet canônico via snippet HFCM #1 no `<head>` (linhas 107–112): injeta `gtm.js` async durante o parse do head | Shim fica cedo no mesmo lugar: `dataLayer` + `gtm.start` + stubs oficiais `gtag`/`fbq` (fila); `gtm.js` em idle 2 s / interação / cap 4 s. Noscript (HFCM #2) intacto | ~655 KB |
| **Meta Pixel** | Injetado pelo container do GTM (não há `fbq` no HTML); stub oficial de fila `fbq.queue` disponível desde o shim | Adiado junto com o GTM; eventos enfileirados antes são reproduzidos | 204–287 KB |
| **Dynamics chat** (1,87 MB) | Tag `<script id="Microsoft_Omnichannel_LCWidget" src=https://… **sem async/defer** — parser-blocking logo após `<body>` (snippet HFCM #3, linha 2060) | Tag idêntica staged como `type="application/json"` (sem fetch/exec) + loader que recria o elemento com os mesmos atributos em idle 2,5 s / interação / **cap 4 s — chat sempre aparece** | 1,87 MB |
| **UserWay** (N6) | IIFE no topo do `<head>` (template do tema `header-lateral.php:4–11`) appenda `widget.js` durante o parse | Mesmo append em **idle 3 s + cap 3,5 s — nunca atrás de interação**; `data-account` preservado | 72–87 KB |
| **VLibras** (N6) | `vlibras-plugin.js` **parser-blocking no `<head>`** (enqueue do plugin VLibras de produção, `vlibrasjs-js`); `new VLibras.Widget(...)` no rodapé | Tag de head + after-inline (no-op: `vlibrasjs` não existe no plugin v7.12.3) removidas; loader no rodapé carrega `vlibras-plugin.js` e instancia `Widget` em **idle 3 s + cap 3,5 s — nunca interação**; markup `<div vw>` intacto | ~2,4 KB + widget |
| **gtag.js G-TF6Z5Z3TFB** (MonsterInsights) | Tag estática async no corpo (linha 2421) | Lazy no mesmo gatilho do GTM; inline do MI (define `window.gtag = __gtagTracker` → dataLayer) intacto e queue-safe | ~126 KB |

**Custo:** +3,6 KB de HTML inline (helper + loaders); ~2,9 MB de terceiros saem da janela crítica em todas as páginas.

**Verificação executada (pré-deploy).** (1) Harness estático: função pura sobre o HTML de produção das 3 páginas — 85 verificações PASS (shims cedo, gatilhos, N6 `interaction:false`, preservação de atributos/noscript/MI/complianz, kill switches, fail-open byte-idêntico). (2) `php -l` em PHP 7.4 e sintaxe dos 6 blocos JS reescritos validada com `node --check`. (3) Smoke em Chrome headless servindo o HTML reescrito (`--virtual-time-budget=12s`): `gtm.js?id=GTM-KGPSJ48` criado e o **container processou** (disparou `gtag/js?id=G-TF6Z5Z3TFB&gtm=4e6bi0h2` e o tag `AW-10949109400` — paridade de eventos), UserWay executou o app do widget, `vlibras-plugin.js` carregou e o botão `#vlibras-access` foi construído, placeholder do chat removido e `LiveChatBootstrapper.js` recriado + cadeia v2 (`v2scripts`, `LiveChatWidgetFrame.css`) em execução.

**Roteiro manual pós-deploy (N6 e paridade).**
1. Abrir a home **sem interagir** e cronometrar: botão do UserWay visível e funcional (abrir menu de acessibilidade e acionar um recurso); widget VLibras visível e funcional (abrir e reproduzir tradução); botão do chat Dynamics aparece **em ≤ ~5 s** sem nenhum clique/scroll (cap de 4 s + rede).
2. Teclar Tab/rolar imediatamente após o load: chat e GTM carregam na hora (gatilho de interação).
3. Eventos GTM chegando: GTM Preview (se acessível) ou painel Realtime do GA4/Ads — confirmar `page_view` e disparo dos tags do container (G-TF6Z5Z3TFB / AW-10949109400 / Pixel).
4. Se algo mensurável quebrar em UserWay/VLibras: `add_filter('hacklabr_defer_accessibility_third_parties', '__return_false')` restaura o carregamento original dos dois; `hacklabr_defer_third_parties` restaura tudo.

**Lacunas.**
1. **Sem verificação em produção** (sem deploy/acesso nesta rodada): os passos acima ficam como gate pós-deploy; medir LCP/TBT/mobile score depois (esperado: TBT mobile da home bem abaixo dos 14,2 s do baseline, habilitando a meta estendida ≥ 90).
2. **Snippets HFCM vivem no banco** (plugin não versionado): se a equipe editar GTM/Omnichat no admin, o padrão para de casar silenciosamente (fail-open = volta a carregar cedo). Com `WP_DEBUG` ativo, o módulo loga contagens por regra (`[hacklabr/third-parties]`).
3. **Trade-off de timing de analytics**: `page_view` e afins passam a refletir o momento pós-gatilho (padrão de mercado em adiamento); timestamps de navegação dentro do GTM seguem corretos (`gtm.start` é registrado no shim, no parse original).
4. **Concorrência de rede no gatilho idle**: em 4G lenta, o download de 1,87 MB iniciado a ~2–2,5 s pode sobrepor a janela de LCP; rIC só dispara com thread livre e o cap é curto por exigência do chat sempre visível. Se a medição pós-deploy mostrar regressão de LCP, alongar `idleMs`/`capMs` do Dynamics no módulo.
5. **`frontend-gtag.min.js` do MonsterInsights mantido** (1st-party, async, necessário ao tracker inline); `wp-emoji` e Complianz fora do escopo.

## #322 — Estabilidade de layout (CLS): causa real, decisão sobre fontes e lacunas

**Causa-raiz medida.** O CLS desktop do baseline (0,535/0,184/0,242) não vem de fontes nem de widgets sem altura: vem de `library/supports.php:39` (`should_load_separate_core_block_assets = true`). Nesse modo, o WordPress adia `global-styles` (24 KB, contém `display:flex` e o basis das colunas) e o CSS de cada bloco para o rodapé/`render_block`; o primeiro paint desenha as colunas empilhadas e elas viram lado a lado quando o parser chega ao rodapé. Corrigido em `library/block-styles.php`, que imprime no `<head>` (em `wp_head` 102, preservando a ordem em cascata de hoje: app.css 99 → CSS do Customizer 101 → estilos de blocos) os estilos globais e os estilos dos blocos presentes na requisição; blocos ausentes continuam de fora.

| Página (throttling real, mediana de 2 corridas) | CLS antes | CLS depois | Lighthouse baseline (antes) |
|---|---|---|---|
| home desktop / mobile | 0,5295 / 0,1785 | **0,0008 / 0,0002** | 0,535 / 0,070 |
| evento desktop / mobile | 0,1807 / 0,1277 | **0,0044 / 0,0036** | 0,184 / 0,117 |
| institucional desktop / mobile | 0,2369 / 0,0011 | **0,0138 / 0,0011** | 0,242 / 0,041 |

Paridade N8 verificada por diff de estilos computados (elemento + `::before`/`::after`) nas 5 páginas × 2 viewports: 0 diferenças (exceção: 1 snapshot de animação do spinner CF7 em `/contato/`).

1. **Fallback métrico de fonte: calibrado, validado e não embarcado.** A premissa da fatia ("swap de fonte sem fallback métrico") não se confirmou na medição: no baseline, a única causa "Web font loaded" pontua 0,0005 (evento desktop). Após a correção da causa-raiz, o resíduo é reflow de swap (0,0138 na institucional); um fallback `@font-face` com `size-adjust`/`ascent-override` calibrados (fonttools indisponível — métricas lidas dos TTFs por leitor próprio; ajuste por mínimos quadrados sobre 18 amostras pt-BR das páginas) zera o desvio **vertical** (0,00px de erro de altura/linha de base) e reduz o **horizontal** (média de 3,7–13,7% para 1,7–3,1%), mas o efeito medido no CLS das 3 páginas foi +0,0001/−0,0016/+0,0001 — ruído — e em alguns elementos (menu da institucional: 769→779px sem fallback; 748→779px com) **aumenta** o deslocamento horizontal. Recomendação: retomar apenas se um resíduo relevante de fonte for medido (a análise muda, por exemplo, com WOFF2 + `font-display: optional`). `font-display: swap` já existia nas 9 faces (`3-generic/3.fonts.scss`).
2. **Sem reserva de espaço adicional (`aspect-ratio`/`min-height`)**: os elementos apontados no baseline (`img.custom-logo`, `img.wp-image-157497`, `hacklabr-posts-grid__root`, coluna da agenda) param de deslocar com a correção da causa-raiz; adicionar reserva "por precaução" mudaria o layout final sem ganho medido.
3. **Trade-off declarado**: ~24 KB de CSS inline passam a chegar antes do primeiro paint; pior caso teórico de FCP +~120 ms em 4G lento (1,6 Mbps). Nas medições (2 corridas por variante) nenhuma regressão de FCP/LCP foi identificável acima da variância entre corridas (±300 ms).
4. **Versão do WordPress de produção não divulgada** (mascarada nos assets: `?ver=370ab2f…`); sinais (React 18.3.1.1, `sizes="auto"`, `block-style-variation-styles`) indicam **WP ≥ 6.8**, enquanto o compose dev usa 6.5.3 — drift a confirmar. A validação estática desta rodada rodou contra as classes do core 6.5.3 **e** 6.8.3 (comportamento idêntico nos pontos usados).
5. **Resíduo pós-correção** (institucional desktop 0,0138): reflow por troca de fonte durante o swap — ver item 1.
