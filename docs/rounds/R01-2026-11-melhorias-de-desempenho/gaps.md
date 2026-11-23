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
