# R01 — Caracterização de gargalos

**Épic:** #282 · **Rodada:** R01 · **Data:** 2026-11-23 · **Autor:** Stage 2 (variante Técnica)
Evidências citadas: [baseline](./baseline.md) (B§) ou código (`caminho:linha`).

## 1. Gargalos ranqueados por impacto

### G1 — Third-parties de marketing/chat: ~2,9 MB e o maior TBT do site
**Evidência:** B§5 — Dynamics/Omnichannel `oc-cdn-public-sam.azureedge.net` = 1,87 MB + GTM 655 KB + Meta Pixel 204–287 KB + UserWay 72–87 KB, **constantes nas 3 páginas**; TBT mobile 8,9–14,2 s e main-thread 33,3 s na home. Do total de ~2,9 MB de terceiros, a maior parte carrega antes/na interação inicial (`LiveChatBootstrapper.js` 123 KB, gtag/gtm/fbevents). 
**Caracterização:** o widget de chat Dynamics + stack de analytics são o maior bloco único de bytes e de trabalho de main-thread. Não são removíveis unilateralmente (decisão de negócio), mas são adiáveis (carregar após interação/consent).

### G2 — Imagens em PNG pesado, inclusive 3,4 MB como background CSS
**Evidência:** B§5 — `Capa-do-projeto-2.png` 3,37 MB usada como `background-image:url(...)` no header de card `wp-block-hacklabr-card-header` perto do topo do HTML da home (markup verificado em 2026-11-23); + `Rectangle-419.png` 516 KB, `cartilha_A4-vertical-724x1024.png` 252 KB, `site-indicadores-1-768x369.png` 230 KB, `Pesquisa-Ethos-DEI-2025–2026-723x1024.png` 228 KB. 
**Caracterização:** 1st-party da home = 6,2 MB, majoritariamente imagens PNG onde WebP/AVIF reduziria 60–85%. Background CSS não passa por `srcset`/`loading=lazy`/`fetchpriority` — sem pipeline de imagens responsivas para esse padrão de bloco.

### G3 — Preloads com URL mismatch causando download duplo (regressão do PR #84)
**Evidência:** código `themes/hacklab-theme/library/assets.php:95` imprime `<link rel="preload" ... app.js>` **sem `?ver=`**, mas `add_externalized_dependencies` (assets.php:293–312) versiona o script com hash (`app.js?ver=2ad543f613a759f4`) → rede mostra **os dois baixados** (24 KB × 2). Fontes: assets.php:108 emite `as="font"` **sem `crossorigin`** → preload não reaproveitado; e as fontes são **TTF** (assets.php:56–58: `OpenSans-Bold.ttf`, `Montserrat-Bold.ttf`…), 4 arquivos ~312 KB (WOFF2 ~30–50% menor, com subset). 
**Caracterização:** o mecanismo de preload do PR #84 está inócuo ou deletério — consome banda duplicada sem acelerar nada.

### G4 — CSS/JS de plugins carregando globalmente no front
**Evidência:** B§5 — 34 stylesheets e 92 scripts na home; `unused-css-rules` 62 KB; na home carregam CSS de `paid-memberships-pro` (base 17 KB), `the-events-calendar`+`events-calendar-pro` (9 arquivos), `integration-dynamics` (jquery-ui + datetimepicker 12 KB), `coblocks`, `complianz-gdpr`, `double-opt-in-for-download`, `advanced-tabs-block`. `swiper.min.js` do events-calendar-pro (43 KB) carrega na home sem uso (`unused-javascript` 41 KB). `react-dom 18.3.1` (41 KB) + `jquery 3.7.1` (30 KB) + jquery-ui/tooltipster/bootstrap-datepicker na home. 
**Caracterização:** não há segmentação por página nem `wp_dequeue` condicional para contexto; o padrão "plugin empilha assets em toda requisição" domina.

### G5 — CLS alto em desktop (fontes/widgets) 
**Evidência:** B§4 — CLS desktop: home **0,535**, institucional 0,242, evento 0,184 (limiar bom = 0,1); mobile: evento 0,117 (reprova), home 0,070 e institucional 0,041 (aprovam). 3 layout shifts registrados + widgets de terceiros (chat, UserWay, complianz) injetam UI pós-carga. 
**Caracterização:** a assimetria desktop≫mobile sugere swap de fonte sem `font-display`/fallback métrico compatível + elementos dinâmicos (banner de cookie, botão de chat) sem reserva de espaço.

### G6 — Documento HTML gigante
**Evidência:** B§5 — HTML da home = 393 KB com 84 `<script>` (muito inline CSS/JS de plugins e WP core). 
**Caracterização:** custo de parse + compressão; secundário frente a G1/G2, mas agrava FCP mobile (3,96 s).

### Não-gargalo (registrar para evitar trabalho perdido)
- **TTFB** (B§4: 65–200 ms real; lab 30–70 ms) — Cloudflare APO + Redis cobrem; **não** investir em cache de página nesta rodada sem nova evidência.

## 2. Auditoria de plugins

### 2.1 Plugins do repo (submódulos, `plugins/`)
| Plugin | Papel | Carrega no front? | Custo estimado |
|---|---|---|---|
| `ethos-associados` | Área do associado, dezenas de shortcodes legacy (`ethosDadosAssociado`, `ethosAreaAssociado`, `ethosListaRequisicoesAcesso`… em `ethos-associados.php:4518+`) | Sim — enqueues jQuery-dependentes (`ethos-associados.php:88–97`) | Médio; monolito legacy, alto risco indireto |
| `EthosDynamics365IntegrationPlugin` | Integração CRM Dynamics 365 | Via `integration-dynamics` (CSS front: jquery-ui/datetimepicker 12 KB na home) | Médio no front |
| `EthosPaymentIntegrationPlugin` | Pagamentos (Getnet) | Fluxos de checkout | Baixo na navegação geral |
| `EthosMigrationPlugin`, `EthosGetnetSimulatorPlugin`, `hacklab-dev-utils` | Migração / sandbox / dev | Não (dev/simulação) | Nulo em produção |

### 2.2 Plugins de produção observados na rede (front)
| Plugin | Reqs (3 págs) | Sinais front | Custo |
|---|---|---|---|
| `the-events-calendar` | 70 | CSS/JS views-full/skeleton, bootstrap-datepicker, tooltipster, utils | **Alto** (global) |
| `events-calendar-pro` | 49 | swiper (43 KB), custom-tables, mini-calendar CSS | **Alto** (global) |
| `coblocks` | 12 | style-coblocks + animações + GoDaddy styles | Médio |
| `contact-form-7` + `date-time-picker-for-c-f7` | 18 | CSS/JS de form + datetimepicker 20 KB | Médio |
| `integration-dynamics` | 9 | CSS jquery-ui/datetimepicker | Médio |
| `paid-memberships-pro` + `pmpro-group-accounts` | 9 | CSS base+variation 22 KB em toda página | Médio |
| `complianz-gdpr` | 6 | banner JS/CSS | Baixo-médio (mas gate de terceiros) |
| `advanced-tabs-block`, `double-opt-in-for-download`, `google-analytics-for-wordpress` (MonsterInsights) | 10 | CSS/JS pequenos + pixel loader | Baixo |

**Os 3 mais pesados no front:** (1) the-events-calendar + events-calendar-pro (119 reqs somadas, CSS/JS global inclusive swiper não usado); (2) integração Dynamics (chat/omnichannel 1,9 MB + CSS front); (3) paid-memberships-pro (CSS global).

## 3. NÃO-NEGOCIÁVEIS funcionais + verificações de paridade

Para cada um: sinal no repo → verificação manual testável (roteiro), a executar **antes e depois** de cada fatia de mudança.

| # | Não-negociável | Sinais no repo | Verificação de paridade |
|---|---|---|---|
| N1 | **Associação e pagamentos (pmpro/Getnet)** — fluxo de associação, planos, cobrança | CSS pmpro na home; `themes/hacklab-theme/library/membership.php`; `plugins/EthosPaymentIntegrationPlugin`; `meu-plano` em `library/assets.php:473–485` | Navegar `associe-se/` → selecionar plano → chegar ao checkout (sandbox) → concluir status "pagamento pendente/aprovado"; conferir página `meu-plano` logado com dados do plano intactos |
| N2 | **Inscrições em eventos (pagos e gratuitos)** — formulário, vaga, confirmação | `library/forms/registration.php`; `dist/js/functionalities/event-registration.js` (enqueue condicional `is_singular('tribe_events')`, assets.php:460–471); `template-registration-finished.php`; REST `library/api/` (`hacklabr/v2`) | Abrir página de evento futuro → preencher inscrição → receber tela de confirmação (template-registration-finished) → validar registro no admin/CRM; para evento pago, completar até o gateway |
| N3 | **Eventos (The Events Calendar/Pro)** — agenda, single, lista, mês, ical | `themes/hacklab-theme/tribe/`, `tribe-events/single-event.php`, `library/the-events-calendar/` | Home → `/agenda/` → alternar lista/mês → abrir single de evento → exportar ical → datas e recorrências idênticas ao baseline |
| N4 | **Integração CRM (Dynamics 365)** — sync de contatos/inscrições | `plugins/EthosDynamics365IntegrationPlugin`; `library/crm/`; `dist/js/sync-crm.js` | Submeter formulário de inscrição/contato → conferir criação/atualização do registro no Dynamics (validar por fila/batch do CRM) |
| N5 | **Área restrita de associados** — login, dados, área exclusiva | `template-associates-area.php`; `library/associates-area.php`; `library/exclusive-content.php`; redirects em `library/ethos-associados-redirects.php` | Logar como associado → acessar área (conteúdo exclusivo visível) → deslogar → mesma URL redireciona para login; shortcodes `ethosAreaAssociado`/`ethosDadosAssociado` renderem igual |
| N6 | **Traduções/idiomas e acessibilidade** — WP pt-BR, strings do tema, VLibras, UserWay | `themes/hacklab-theme/languages/` (pt_BR.po, JSONs); widgets vlibras/userway na rede | Conferir rótulos do tema em pt-BR nas 3 páginas-chave; acionar VLibras e UserWay e verificar widget funcional |
| N7 | **Formulários de contato (CF7)** — envio e validação | CSS/JS `contact-form-7` + `date-time-picker` na rede | Enviar formulário em `/contato/` com campo de data → mensagem de sucesso + e-mail/crm recebido |
| N8 | **Identidade visual do front** — layout, hierarquia, fontes visíveis | `assets/scss/`, `assets/fonts/`, `theme.json` | Comparação lado a lado (screenshots full-page) das 3 páginas vs. baseline; fontes continuam Montserrat/OpenSans com pesos corretos |

**Declaração explícita:** *comportamento visual pode mudar* apenas em: ordem/definição técnica de assets (ex.: trocar TTF→WOFF2 mantendo a família visual), reserva de espaço de widgets (chat/cookie) e ganho de velocidade. **Não pode mudar:** conteúdo, navegação, fluxos, textos e identidade percebida (N8).

## 4. Veredicto sobre trabalho prévio

- **PR #84 (`feat/performance`, merged 2024-07-04, commit `120acfc8`)**: introduziu o mecanismo de preloads em `themes/hacklab-theme/library/assets.php` (540 linhas alteradas). **Aproveitável como base, mas com defeito**: hrefs sem `?ver=` e fontes sem `crossorigin` → downloads duplos medidos (G3). Corrigir, não reescrever.
- **Branch `registration-optimization`**: `git merge-base --is-ancestor origin/registration-optimization origin/develop` → **é ancestral de develop — 100% integrada** (últimos commits tratam de formulário legacy de eventos pagos e templates unificados). **Não há trabalho não integrado aproveitável.**
- **Branch `feat/performance`**: idem, merge via PR #84; nada além do merge.
