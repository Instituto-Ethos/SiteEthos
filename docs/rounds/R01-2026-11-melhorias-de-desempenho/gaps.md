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
