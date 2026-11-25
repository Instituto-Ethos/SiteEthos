# Getnet — fluxo local de pagamentos de eventos

Estado e runbook da frente **2.2 do plano de ação** ("Integração de
Pagamentos (Getnet) para Eventos Pagos"). Escopo: validação local completa do
checkout → webhook → status, usando o simulador, **sem credenciais reais**.

Documentação complementar:

- Simulador (como usar, painel, CLI): `plugins/EthosGetnetSimulatorPlugin/README.md`
- Configuração operacional (equipe Ethos): `plugins/EthosPaymentIntegrationPlugin/ethos-payment-integration/includes/getnet/README.md`
- Histórico: `plugins/EthosPaymentIntegrationPlugin/CHANGELOG.md`

## Estado (25/11/2026)

| Validação | Resultado |
|---|---|
| Testes standalone do plugin (`php dev-scripts/test-*.php`) | ✅ 127 asserts (mapping/transições 28, participant 15, UF 61, + discounts/expires) |
| `wp getnet self_test` no container | ✅ |
| Rota real do webhook (HTTP) | ✅ sem auth → 401 · auth errada → 401 · auth correta → 200 com JSON do processador |
| Simulador | ✅ ativo (ambiente sandbox, credenciais de webhook geradas pelo bootstrap) |
| **E2E CLI do processador** (25/11/2026) | ✅ com participantes reais do sandbox: autorização → CRM **Paga** com valor/referência/método; reentrega → `duplicate:true`; downgrade após pago → `skipped:forbidden`; cadeia Pendente → Aguardando → Paga. Obs.: a CLI pede confirmação interativa — com `docker compose exec -T`, alimentar com `printf 'y\n' \|` |
| **E2E de navegador** (25/11/2026) | ✅ inscrição pelo formulário → participante criado no CRM → modal simulado com valor/order_id → "Aprovar cartão" → processador real (`processed:true`) → CRM Paga (R$ 1.600,70 + referência) → página de status confirmando. O **2.2 está fechado** |

### Cenário de teste do E2E de navegador (setup)

- Evento: post `184980` (curso pago, R$ 1600,70) com metas locais ajustadas:
  shortcode `[event-registration]` no conteúdo, encerramento de inscrições
  `2026-12-31` e **`entity_fut_projeto` apontando para o projeto do sandbox**
  (`6a4eb3eb-…` — eventos locais vêm do dump de produção e o GUID não existe
  no sandbox, o que derruba a inscrição com "Entity Does Not Exist").
- Usuário: `colab.hacklab` (senha local de teste) com `_ethos_crm_contact_id`
  → contato `51f08167-…` (Everaldo Matias) — endereço completo PATCHeado no
  sandbox (Rua Helvetia 55, Barra Funda, SP) para liberar o checkout.
- Página de status: `/status-pagamento/` (post `186083`, criado localmente
  com o shortcode). Nota: o link "Ver página de status" do modal usa a URL
  de status configurada (em produção existe; localmente apontava para a
  home até a página ser criada).

### Fix de login descoberto no E2E (pendente de commit)

`get_pmpro_child_level()` (membership.php) também quebrava com grupo sem
nível — fatal **no login** (wp_signon). Corrigido com guard `empty($level_id)`
(mesma família do fix de `get_pmpro_plan`); usuário de teste em grupo sem
nível volta a logar. **Não commitado ainda.**

Premissas de layout: o plugin de pagamentos é **submódulo do repo do site**
(`plugins/EthosPaymentIntegrationPlugin`, URL `Instituto-Ethos/...`). Os
testes standalone assumem esse layout (caminho fixo até o tema); qualquer
layout diferente não é suportado.

## Como validar (comandos)

```bash
# Testes standalone (no host, a partir do repo do site)
php plugins/EthosPaymentIntegrationPlugin/ethos-payment-integration/dev-scripts/test-webhook-mapping.php
php plugins/EthosPaymentIntegrationPlugin/ethos-payment-integration/dev-scripts/test-participant-check.php
php plugins/EthosPaymentIntegrationPlugin/ethos-payment-integration/dev-scripts/test-uf-enum.php

# No container
docker compose exec wordpress wp getnet self_test
```

O smoke HTTP do webhook (401/401/200) requer as credenciais Basic da opção
`ethos_payment_integration__getnet` (geradas pelo bootstrap; **nunca exibir
em logs**) — dentro do container, o site responde em `http://localhost`.

## E2E — receitas

Ambas as vias (CLI e navegador) já foram executadas e validadas (ver tabela
de estado); as receitas ficam como regressão.

### Via CLI (regressão rápida do processador — já validada em 25/11/2026)

```bash
# Partindo de um participante em status "Sem Status" (PENDING):
printf 'y\n' | docker compose exec -T wordpress wp getnet simulate_webhook \
    --scenario=credit_authorized --order=<uuid> --intent_id=sim_x --payment_id=pay_x --execute
# → processed:true · CRM vira Paga com valor/referência

printf 'y\n' | docker compose exec -T wordpress wp getnet simulate_webhook \
    --scenario=credit_authorized --order=<uuid> --intent_id=sim_x --payment_id=pay_x --execute
# → duplicate:true (idempotência)

printf 'y\n' | docker compose exec -T wordpress wp getnet simulate_webhook \
    --scenario=pix_pending --order=<uuid> --intent_id=sim_y --payment_id=pay_y --execute
# → sobre participante Paga: skipped:forbidden (não rebaixa)
```

Para a cadeia de transição (Pendente → Aguardando → Paga), trocar o
participante e rodar `pix_pending` e depois `credit_authorized`.

### Via navegador (regressão da UI — já validada em 25/11/2026)

Seguir o §4.1 do README do simulador: inscrição em evento pago → botão Pay →
modal → desfechos → página de status → re-checkout. Requer conta com
endereço completo (exigência do gateway) e o cenário de setup descrito
acima.

## Repositório e fluxo

- Commits do plugin acontecem **no submódulo** (repo próprio, branch `main`);
  o repo do site atualiza o ponteiro num commit subsequente.
- O plugin não usa `vendor/` (PHP puro) — zip de deploy via
  `dev-scripts/zip.sh` do repo do plugin (com exclusão `*docs-locais*`, como
  os demais).
