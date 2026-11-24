# R01 — Linha de base de desempenho

**Épic:** #282 "Melhorias de desempenho" · **Rodada:** R01 · **Data da medição:** 2026-11-23 (16:48–16:56 BRT / 19:48–19:56 UTC) · **Autor:** Stage 2 (variante Técnica)

## 1. URL medida

URL de produção derivada do repo: `compose/local/wordpress/htaccess:12` redireciona uploads para `https://ethos.org.br/...`; o apex responde 301 → **`https://www.ethos.org.br/`** (canônica, confirmada com `curl -sI`).

```bash
curl -sI https://ethos.org.br/ | grep -i location
# location: https://www.ethos.org.br/
```

Infra detectada pelos headers de resposta (2026-11-23):

- `server: cloudflare`, `cf-apo-via: origin,resnok` → site atrás da **Cloudflare com APO** (Automatic Platform Optimization) ativo; na amostra, HTML veio da origem (miss) com `cf-cache-status: BYPASS`.
- Rodapé do HTML: `<!-- Performance optimized by W3 Total Cache ... Object Caching 291/446 objects using Redis / Database Caching ... using Redis -->` → **W3 Total Cache com object cache (Redis) e DB cache**, **sem page cache** no W3TC (nenhuma linha "Page Caching" no comentário).

## 2. Método — desvio registrado

**Plano original:** PageSpeed Insights API v5 keyless (mobile + desktop, 3 páginas).

**Ocorrido:** todas as chamadas PSI retornaram **HTTP 429** — "Quota exceeded for quota metric 'Queries' and limit 'Queries per day'" (cota diária compartilhada do projeto anônimo do Google; aguardar 30s não recupera). Tentativas: 3 por URL × 6 combinações + 1 retry tardio. Comando utilizado:

```bash
curl -s "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=<URL-encoded>&strategy=mobile"
```

**Fallback executado (lab data reproduzível):** Lighthouse CLI 13.6.0 (mesmo motor do PSI) com Chrome headless contra a produção, preset mobile padrão (4G lenta, CPU 4× throttle) e `--preset=desktop`. JSONs brutos arquivados pelo autor da rodada em `/tmp/opencode/psi/*.json` (fora do repo; recomenda-se anexar à issue ou re-executar o comando abaixo).

```bash
npx -y lighthouse "https://www.ethos.org.br/" --quiet --only-categories=performance \
  --chrome-flags="--headless=new --no-sandbox" --output=json --output-path=home-mobile.json
npx -y lighthouse "https://www.ethos.org.br/" --quiet --only-categories=performance \
  --preset=desktop --chrome-flags="--headless=new --no-sandbox" --output=json --output-path=home-desktop.json
```

**Lacunas resultantes (ver motivation.md §6):** sem CrUX field data (`loadingExperience`) e sem INP real de campo — TBT é o proxy de lab.

## 3. Páginas medidas

| Papel | URL |
|---|---|
| Home | `https://www.ethos.org.br/` |
| Evento/inscrição | `https://www.ethos.org.br/evento/embarque-no-ethos-encontro-com-associadas-dezembro-2026/` (evento futuro com inscrição; escolhido após 301 de canonicalização de data em `/evento/dia-internacional-do-combate-a-corrupcao/2026-12-09/`) |
| Institucional | `https://www.ethos.org.br/instituto-ethos/` |

## 4. Resultados por página (Lighthouse, 2026-11-23)

### Mobile (throttle padrão: 4G lenta, CPU 4×)

| Página | Score | LCP | FCP | TBT | CLS | Speed Index | TTFB (lab) |
|---|---|---|---|---|---|---|---|
| Home | **31** | **5,79 s** | 3,96 s | **14,19 s** | 0,070 | 9,87 s | 0,07 s |
| Evento | **44** | **3,56 s** | 3,56 s | **9,66 s** | 0,117 | 6,46 s | 0,04 s |
| Institucional | **58** | **2,92 s** | 2,92 s | **8,88 s** | 0,041 | 3,73 s | 0,06 s |

### Desktop (`--preset=desktop`)

| Página | Score | LCP | FCP | TBT | CLS | Speed Index | TTFB (lab) |
|---|---|---|---|---|---|---|---|
| Home | **32** | 1,79 s | 1,15 s | 1,24 s | **0,535** | 3,18 s | 0,03 s |
| Evento | **52** | 1,07 s | 1,07 s | 1,27 s | **0,184** | 2,84 s | 0,03 s |
| Institucional | **53** | 1,03 s | 1,03 s | 1,73 s | **0,242** | 1,51 s | 0,03 s |

**Leitura contra limiares Core Web Vitals (bom):** LCP ≤ 2,5 s — reprovação mobile nas 3 páginas; CLS ≤ 0,1 — reprovação em 4 de 6 combinações (pior: 0,535 na home desktop); TBT (proxy de INP; > 600 ms já indica risco) — 14,2 s na home mobile, ~24× o limiar de INP.

### TTFB real (curl, fora de lab, 5 amostras/página, 2026-11-23)

```bash
curl -s -o /dev/null -w "TTFB=%{time_starttransfer}s\n" "https://www.ethos.org.br/"
```

| Página | Min–Máx TTFB | Mediana aproximada |
|---|---|---|
| Home | 0,096–0,151 s | ~0,12 s |
| Evento | 0,065–0,141 s | ~0,09 s |
| Institucional | 0,073–0,200 s | ~0,09 s |

**TTFB não é o gargalo** (APO/Cloudflare + Redis cobrem bem o HTML); o problema é front-end (payload, JS bloqueante, imagens).

## 5. Composição do carregamento (mobile)

| Métrica | Home | Evento | Institucional |
|---|---|---|---|
| Requisições totais | **218** | 218 | 127 |
| Transferência total | **8,88 MB** | 3,86 MB | 3,41 MB |
| Scripts | 92 | — | — |
| Stylesheets | 34 | — | — |
| 1st-party (ethos.org.br) | 6,20 MB | 0,99 MB | 0,62 MB |
| 3rd-party | 2,90 MB | 2,96 MB | 2,88 MB |
| Main-thread work | **33,3 s** | — | — |
| JS bootup time | **16,9 s** | — | — |
| Documento HTML | 393 KB | — | — |
| Long tasks | 20 | — | — |

### Third-parties mais pesados (transfer, mobile — constante nas 3 páginas)

| Domínio | O que é | Peso |
|---|---|---|
| `oc-cdn-public-sam.azureedge.net` | Microsoft Dynamics 365 / Omnichannel Engagement Hub (widget de chat) | **1,87 MB** |
| `www.googletagmanager.com` | GTM-KGPSJ48 + gtag G-TF6Z5Z3TFB + gtag AW-10949109400 | **655 KB** |
| `connect.facebook.net` | Meta Pixel (536188004290641) | 204–287 KB |
| `cdn.userway.org` | UserWay (acessibilidade) | 72–87 KB |
| `m-…br.omnichannelengagementhub.com` | Chat Dynamics (runtime) | 31 KB |

### Maiores recursos 1st-party (home mobile)

| Recurso | Peso |
|---|---|
| `uploads/2026/11/Capa-do-projeto-2.png` (background-image CSS de card) | **3,37 MB** |
| `uploads/2024/05/Rectangle-419.png` | 516 KB |
| `uploads/2026/08/cartilha_A4-vertical-724x1024.png` | 252 KB |
| `uploads/2025/07/site-indicadores-1-768x369.png` | 230 KB |
| `uploads/2026/11/Pesquisa-Ethos-DEI-2025–2026-723x1024.png` | 228 KB |
| Fontes `assets/fonts/*.ttf` (OpenSans/Montserrat, 4 arquivos) | ~312 KB (TTF, sem WOFF2; baixadas 2×) |
| `dist/js/functionalities/app.js` | 24 KB × **2 downloads** (preload sem `?ver=` + script com `?ver=2ad543f613a759f4`) |
| `wp-includes/js/dist/vendor/react-dom.min.js?ver=18.3.1.1` | 41 KB (React 18 carregado no front da home) |
| `wp-includes/js/jquery/jquery.min.js?ver=3.7.1` | 30 KB |

### Oportunidades apontadas pelo Lighthouse (home mobile)

- `unused-javascript`: **541 KB** estimados não usados (maiores: gtag 126+76 KB, LiveChatBootstrapper 69 KB, gtm.js 65 KB, gtag AW 57 KB, swiper do events-calendar-pro 41 KB não usado na home).
- `unused-css-rules`: 62 KB (34 stylesheets: TEC + Events Calendar Pro + pmpro + coblocks carregam em toda página).
- `mainthread-work-breakdown`: 33,3 s · `bootup-time`: 16,9 s · `total-byte-weight`: 9.095 KB.

## 6. Inventário de plataforma

### Versões
- **WordPress 6.5.3 / PHP 8.3** — imagem dev `hacklab/wp:6.5.3-php8.3` (`compose/local/wordpress/Dockerfile:1`). Versão de produção não divulgada no HTML (sem meta generator) — lacuna menor; jQuery 3.7.1 e React-DOM 18.3.1 observados nos assets.
- Tema: `themes/hacklab-theme` (SiteEthos v1.11.0, base hacklabr 2.0.0, build Laravel Mix).
- HTML da home: 393 KB, 84 tags `<script>`.

### Plugins
- **No repo (`plugins/`, submódulos):** 6 diretórios — `ethos-associados`, `EthosDynamics365IntegrationPlugin`, `EthosGetnetSimulatorPlugin`, `EthosMigrationPlugin`, `EthosPaymentIntegrationPlugin`, `hacklab-dev-utils` (dev). **`mu-plugins/`: vazio.**
- **Carregando no front de produção** (evidência de rede, 3 páginas mobile): `the-events-calendar` (70 reqs), `events-calendar-pro` (49), `coblocks` (12), `contact-form-7` (9), `date-time-picker-for-contact-form-7` (9), `integration-dynamics` (9), `paid-memberships-pro` (6), `complianz-gdpr` (6), `advanced-tabs-block` (4), `double-opt-in-for-download` (3), `google-analytics-for-wordpress`/MonsterInsights (3), `pmpro-group-accounts` (3), `ethos-associados` (3).
- **Total de plugins ativos em produção: desconhecido** (sem acesso wp-admin nesta rodada) — lacuna registrada.

### Cache/CDN
- **Produção:** Cloudflare + APO (headers `cf-apo-via`, `cf-cache-status`); W3 Total Cache com object cache Redis (291/446 objetos) e DB cache Redis; **sem page cache no W3TC**.
- **Repo/compose:** nenhum serviço de cache/CDN em `docker-compose.yml`/`compose/` (ambientes locais apenas: wordpress, watcher, mariadb, mailhog).

## 7. Reprodução integral

```bash
# scores e métricas (lab)
npx -y lighthouse <URL> --quiet --only-categories=performance \
  --chrome-flags="--headless=new --no-sandbox" --output=json | jq '{score:.categories.performance.score, lcp:.audits["largest-contentful-paint"].displayValue, tbt:.audits["total-blocking-time"].displayValue, cls:.audits["cumulative-layout-shift"].displayValue}'
# desktop: adicionar --preset=desktop
# TTFB real
curl -s -o /dev/null -w "%{time_starttransfer}\n" <URL>
# assinatura de cache/CDN
curl -s <URL> | tail -c 600
```

JSONs completos das 6 corridas (fetchTime UTC): home-mobile 19:49:02 · home-desktop 19:50:24 · evento-mobile 19:51:50 · evento-desktop 19:53:06 · institucional-mobile 19:54:32 · institucional-desktop 19:55:47.
