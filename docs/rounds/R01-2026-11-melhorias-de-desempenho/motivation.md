# R01 — Motivação

**Épic:** #282 "Melhorias de desempenho" · **Rodada:** R01 · **Data:** 2026-11-23 · **Autor:** Stage 2 (variante Técnica)

## 1. Problema (com evidência)

O site do Instituto Ethos **reprovou nos Core Web Vitals**, registrado em **08/05** no corpo original do épic #282 ("Site reprovado no Web Core Vitals, em 08/05 - verificar no page speed e aplicar melhorias"). A linha de base medida em 2026-11-23 ([baseline.md](./baseline.md)) confirma e quantifica a reprovação **sete meses depois**, em todas as páginas amostradas:

- **LCP mobile reprova nas 3 páginas**: home 5,79 s (2,3× o limiar de 2,5 s), evento 3,56 s, institucional 2,92 s.
- **CLS reprova em 4 de 6 medições**: pior caso 0,535 (home desktop, 5× o limiar de 0,1).
- **TBT mobile de 8,9–14,2 s** (proxy de INP; limiar de INP bom = 200 ms) — a página fica **congelada por segundos** para interação.
- Score Lighthouse mobile: **31** (home), **44** (evento), **58** (institucional).
- Payload da home mobile: **8,88 MB em 218 requisições**, com 92 scripts e 34 stylesheets.

O problema não é o servidor: TTFB real de 65–200 ms (Cloudflare APO + Redis). O gargalo é inteiramente front-end — caracterizado em [characterization.md](./characterization.md).

## 2. Análise de impacto (o que a lentidão toca)

- **Usuários mobile (público-majoritário de ONGs no Brasil):** FCP de ~4 s e TBT de ~14 s na home significam tela parcial e sem interação por vários segundos em redes 4G — abandono direto de leitura e navegação.
- **Inscrições em eventos e associações (receita/engajamento):** páginas de evento reprovam LCP (3,56 s) e CLS (0,117 mobile / 0,184 desktop) — CLS em formulário causa cliques errados e erros de preenchimento; cada segundo a mais no carregamento antes do formulário é atrito no funil de inscrição/pagamento.
- **SEO/orgânico:** CWV é sinal de ranqueamento; LCP/CLS reprovados em páginas head (home, institucional, eventos) degradam a visibilidade orgânica justamente dos conteúdos que trazem tráfego (pesquisas, publicações, eventos).
- **Percepção institucional:** site de 8,9 MB por visita também multiplica custo de dados para o usuário e emissão de carbono — incoerente com a pauta de sustentabilidade do Instituto.

## 3. O que PODE mudar de propósito (escopo técnico da rodada)

1. **Markup e estratégia de assets**: ordem de carregamento, `defer/async`, correção dos preloads com URL mismatch (characterization G3), conversão de fontes TTF→WOFF2 com `crossorigin` e `font-display`.
2. **Imagens**: conversão para WebP/AVIF, dimensões responsivas (`srcset/sizes`), substituição do padrão "PNG multimegabyte como background CSS" por imagem otimizada com `fetchpriority` quando acima da dobra e `loading="lazy"` abaixo.
3. **Segmentação de assets de plugins por contexto**: dequeue/condicional de CSS/JS de events-calendar, pmpro, integration-dynamics, coblocks etc. em páginas onde não são usados (characterization G4).
4. **Adiamento de terceiros**: carregar chat Dynamics (1,87 MB), GTM/Meta Pixel e UserWay após interação ou consentimento (com aprovação de negócio — instrumentação em jogo).
5. **Reserva de espaço** para widgets (chat, cookie banner) e fallbacks de fonte para reduzir CLS.

## 4. NÃO-NEGOCIÁVEIS (definidos na caracterização §3)

Funcionalidades que devem continuar **idênticas**: N1 associação/pagamentos (pmpro/Getnet) · N2 inscrições em eventos (pagos e gratuitos) · N3 agenda/eventos (TEC/Pro) · N4 integração CRM Dynamics · N5 área restrita de associados · N6 traduções/accessibilidade (pt-BR, VLibras, UserWay) · N7 formulários CF7 · N8 identidade visual.

Cada uma com roteiro de verificação de paridade executável (characterization.md §3). **Comportamento visual:** pode mudar apenas o definido em §3 da caracterização (definição técnica de assets, reserva de espaço de widgets, velocidade); conteúdo, navegação, fluxos, textos e identidade percebida não mudam.

## 5. META MENSURÁVEL (valor-alvo)

> **LCP mobile ≤ 2,5 s, CLS ≤ 0,1 em mobile e desktop, e score mobile (Lighthouse/PageSpeed) ≥ 75 nas páginas home (`/`), evento (`/evento/…`) e institucional (`/instituto-ethos/`), atingidos em até 3 rodadas de execução (R02–R04), medida pelo mesmo método da linha de base (Lighthouse 13 CLI, presets mobile/desktop, 3 páginas), sem regressão desktop (LCP ≤ 2,5 s) e com paridade comprovada em todos os não-negociáveis N1–N8.**

Âncora nos números medidos: partir de LCP 5,79/3,56/2,92 s → ≤ 2,5 s; CLS 0,535→≤ 0,1; score 31→≥ 75. O valor 75 (não 90) é o alcançável sem remover funcionalidades de negócio, pois **2,9 MB são third-parties** (chat/analytics) cuja deferral total depende de decisão da Stage 1; se aprovada a deferral completa de terceiros, registra-se meta estendida de score ≥ 90 como ponto de parada seguinte. Progresso e números finais vs. linha de base serão registrados em `resultados.md`.

## 6. Lacunas e tarefas de medição (não inventado — registrado)

1. **CrUX field data / INP real**: PSI API keyless esgotou cota diária (429) em 2026-11-23 — repetir quando a cota renovar ou providenciar API key; alternativa: RUM leve (ex.: beacon CWV) a avaliar na Stage 1.
2. **Inventário completo de plugins ativos em produção** (wp-admin) — a lista da linha de base é a observada via rede front.
3. **Versão exata de WP/PHP em produção** (HTML não divulga; dev = WP 6.5.3/PHP 8.3).
4. **Page cache em produção** (W3TC sem "Page Caching" no rodapé; APO cobre HTML hoje) — validar comportamento de cache HTML em páginas de formulário logado/deslogado antes de qualquer mudança de cache.
