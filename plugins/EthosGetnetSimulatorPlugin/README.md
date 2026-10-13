# Ethos Getnet Simulator

Plugin de **desenvolvimento**: preview do fluxo completo de eventos pagos —
checkout, webhook e shortcode de status — **sem credenciais da Getnet** e sem
nenhuma requisição saindo do site. Inerte em produção.

Toda confirmação simulada passa pelo **mesmo processador do webhook real**
(`ethos\payments\getnet\process_getnet_webhook_payload`): idempotência por
`(payment_intent_id, payment_id)`, máquina de transições de status, PATCH no
CRM e invalidação de cache. A única etapa pulada é a Basic auth (exclusiva da
rota REST).

## Índice

1. [O que NÃO é previewável](#1-o-que-não-é-previewável)
2. [Guardas de segurança](#2-guardas-de-segurança)
3. [Instalação (dev)](#3-instalação-dev)
4. [Como usar](#4-como-usar)
5. [Testes standalone](#5-testes-standalone)
6. [Arquitetura](#6-arquitetura)
7. [Hooks exigidos do plugin de pagamentos](#7-hooks-exigidos-do-plugin-de-pagamentos)
8. [Invariantes preservadas](#8-invariantes-preservadas)
9. [Deploy](#9-deploy)

## 1. O que NÃO é previewável

- UI do lightbox real da Getnet (loader.js hospedado por eles) — o simulador
  substitui por um modal próprio
- QR code do PIX e boleto (artefatos do gateway)
- Comportamento do redirect real (parâmetros extras que a Getnet anexa —
  há um logger diagnóstico no shortcode para quando isso ocorrer)
- Aceitação de `expires_at` pela API real (a string é construída e testada
  em `test-expires-at.php` no plugin de pagamentos, mas nunca enviada)

## 2. Guardas de segurança

O simulador é **inerte em produção**:

- Ativação recusada se `getnet_environment = production` (wp_die) ou se o
  plugin de pagamentos não estiver ativo
- `admin_init`: se o ambiente virar production com o simulador ativo, ele
  **auto-desativa** e avisa
- Runtime: todos os hooks/rotas consultam `ethos_getnet_sim_active()` — em
  production nenhum filtro é registrado e nenhuma rota existe
- Aviso persistente no admin enquanto ativo

## 3. Instalação (dev)

1. Clone o repositório no workspace (ao lado dos demais plugins):

```bash
git clone <repo>/EthosGetnetSimulatorPlugin.git plugins/EthosGetnetSimulatorPlugin
```

2. Sincronize `ethos-getnet-simulator/` para `wp-content/plugins/` e ative
   (requer `EthosPaymentIntegrationPlugin` **atualizado** — ver §7)
3. `Settings > Payment Integration`: mantenha o ambiente como **sandbox**
   (as credenciais Client ID/secret podem ficar vazias)
4. O bootstrap do simulador gera as credenciais de webhook (Basic auth)
   automaticamente — a rota real do webhook passa a responder

## 4. Como usar

### 4.1 Fluxo completo de preview

1. Crie/abra um evento pago no site e faça a inscrição pelo formulário
   (conta com endereço completo, como o gateway exige)
2. Após o submit, o botão **Pay** abre o **modal simulado** (não o lightbox):
   valor e `order_id` vêm da intent guardada server-side
3. Escolha um desfecho: aprovar crédito, negar, PIX pendente, PIX confirmado
   (tarde), boleto pendente — o resultado JSON do processador aparece no
   modal (`processed`, `duplicate`, `skipped`…)
4. **Reenviar mesmo pagamento** testa idempotência (mesmo
   `(intent, payment)` → `duplicate: true`)
5. **Ver página de status** abre `getnet_status_url?order=<uuid>` — o
   shortcode `[ethos-event-payment-status]` mostra o estado real do CRM
6. Refaça desfechos diferentes sobre a mesma inscrição para ver a máquina
   de transições (ex.: PIX pendente → depois PIX confirmado = Pago;
   tentar “pendente” depois de Pago = `skipped: forbidden`)

O re-checkout da página de status (“Complete your payment”) também passa
pelo mock — nova intent `sim_` + modal.

### 4.2 Painel de controle (Ferramentas → Getnet Simulator)

- **Consultar participante**: cole o UUID (`order_id`) → estado atual no CRM
  (status, referência, valores) + link para a página de status
- **Disparar desfecho**: mesmo caminho do modal, com `intent_id`/`payment_id`
  opcionais para re-entregas determinísticas
- **Intents simuladas recentes**: as 10 últimas intents criadas, com atalhos
- **Limpar seen-keys sim_***: zera a idempotência das entregas simuladas
  (permite reprocessar um webhook no dev)

### 4.3 Galeria de estados do shortcode

Na mesma página do painel: renderização de
`render_getnet_payment_status_for_participant()` com fixtures fabricadas —
pendente (sem status / com resumo), aguardando (OptionSetValue objeto),
pago (com e sem valor), cancelada. Sem nenhum CRM envolvido. O botão
“Complete seu pagamento” exige contato+evento reais — teste pela via do
participante (§4.2).

### 4.4 Webhook real via curl

A rota real (`/wp-json/ethos-payments/v1/getnet/webhook`) funciona com as
credenciais Basic geradas pelo bootstrap. Pegue user/password no option
`ethos_payment_integration__getnet` (nunca exiba em logs) e:

```bash
curl -u "$WEBHOOK_USER:$WEBHOOK_PASSWORD" \
  -H 'Content-Type: application/json' \
  -d '{"payment_intent_id":"sim_manual-1","order_id":"<uuid participante>",
       "payment":{"method":"credit","amount":15000,
       "result":{"status":"Authorized","payment_id":"pay-curl-1"}}}' \
  'https://<site>/wp-json/ethos-payments/v1/getnet/webhook'
```

Reenvie o mesmo corpo → `{"processed":true,"duplicate":true}`.

### 4.5 CLI

```
wp getnet simulate-webhook --scenario=credit_authorized --order=<uuid> [--execute]
wp getnet simulate-webhook --scenario=boleto_pending --order=<uuid> \
    --intent_id=sim_manual-1 --payment_id=pay-curl-1 --execute   # re-entrega
wp getnet self-test      # mapeamento + máquina de transições
```

`--execute` agora passa pelo processador compartilhado (idempotência +
transições aplicadas — antes gravava direto no CRM e podia rebaixar Pago).

## 5. Testes standalone

```bash
# payload builder + fixtures → render (neste repositório)
php ethos-getnet-simulator/dev-scripts/test-sim-payload.php

# mapeamento/transições — regressão do refactor (repositório do plugin de pagamentos)
php ethos-payment-integration/dev-scripts/test-webhook-mapping.php
```

O teste deste plugin localiza o plugin de pagamentos automaticamente no
layout do workspace; se o layout diferir, use
`ETHOS_PAYMENT_PLUGIN_PATH=/caminho/para/ethos-payment-integration`.

## 6. Arquitetura

```
ethos-getnet-simulator/
├── ethos-getnet-simulator.php   # guardas (ativação/production/avisos) + bootstrap
├── includes/
│   ├── mock-api.php             # pre_getnet_request: intents sim_, seller/config canônicos, store
│   ├── payload.php              # build_sim_webhook_payload() — puro, testável standalone
│   ├── ui.php                   # filtro getnet_checkout_button → botão + modal server-rendered
│   ├── rest.php                 # POST ethos-getnet-sim/v1/outcome → processador real
│   └── admin.php                # painel (Ferramentas) + galeria de fixtures
├── assets/                      # JS/CSS vanilla do modal
└── dev-scripts/                 # test-sim-payload.php
```

## 7. Hooks exigidos do plugin de pagamentos

| Hook / função | Arquivo | Efeito |
|---|---|---|
| filtro `ethos_payments/pre_getnet_request` | `includes/getnet/oauth.php` | Short-circuita `getnet_request()` antes do token |
| `process_getnet_webhook_payload()` | `includes/getnet/webhook.php` | Corpo do webhook extraído da rota REST (compartilhado) |
| filtro `ethos_payments/getnet_checkout_button` | `includes/getnet/checkout.php` | Substitui o botão do lightbox |
| `ensure_getnet_webhook_credentials()` | `includes/getnet/setup.php` | Geração de credenciais extraída de `setup_getnet_seller()` |
| `render_getnet_payment_status_for_participant()` | `includes/getnet/shortcode.php` | Núcleo de render do shortcode (callable com fixtures) |

Todos são passthrough opcionais (`null` = comportamento original): sem o
simulador, o plugin de pagamentos se comporta exatamente como antes.

## 8. Invariantes preservadas

- Seen-key compartilhada entre webhooks reais e simulados; intents `sim_`
  nunca colidem com ids reais
- Máquina de transições SEMPRE aplicada: Pago nunca rebaixado; Aguardando
  só antes de confirmação; Cancelada nunca ressuscitada
- Valor e `order_id` sempre server-side (store de intents / CRM)
- Referência no formato `{payment_intent_id}&{payment_id}`
- Ramos voucher/cortesia intocados (não passam pelo checkout)

## 9. Deploy

Atualizar **o plugin de pagamentos antes do simulador** (ele depende dos
hooks do §7). O simulador nunca vai para produção — a guarda de ativação
impede.
