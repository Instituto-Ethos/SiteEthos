<?php

namespace hacklabr\Fields;

function render_pmpro_level_field (string $name, $value, array $definition) {
    $kit = filter_input(INPUT_GET, 'kit', FILTER_SANITIZE_ADD_SLASHES) ?? '';

    $post = \hacklabr\get_post_by_transaction('organizacao');

    $level_options = \hacklabr\get_pmpro_level_options($post->ID ?? null, $definition['for_manager']);
    $initial_value = $value ?: ($level_options[$kit] ?? 'null');

    $select_plan_str = esc_attr(__('Select plan', 'hacklabr'));
    $selected_plan_str = esc_attr(__('Selected plan', 'hacklabr'));
?>
    <div class="choose-plan__input" x-data="{ level: <?= $initial_value ?> }">
        <div class="choose-plan__grid">
            <div class="choose-plan__plan">
                <div class="choose-plan__title">
                    <strong>Conexão</strong>
                </div>
                <?php if (is_active_sidebar('vantagens_conexao')): ?>
                    <?php dynamic_sidebar('vantagens_conexao'); ?>
                <?php else: ?>
                    <div class="choose-plan__text"></div>
                <?php endif; ?>
                <div class="choose-plan__button">
                    <button type="button" class="button" :class="level === <?= $level_options['conexao'] ?> ? 'button--solid' : 'button--outline'" @click="level = <?= $level_options['conexao'] ?>">
                        <span x-text="(level === <?= $level_options['conexao'] ?> ? '<?= $selected_plan_str ?>' : '<?= $select_plan_str ?>')"></span>
                    </button>
                </div>
            </div>

            <div class="choose-plan__plan">
                <div class="choose-plan__title">
                    <strong>Essencial</strong>
                </div>
                <?php if (is_active_sidebar('vantagens_essencial')): ?>
                    <?php dynamic_sidebar('vantagens_essencial'); ?>
                <?php else: ?>
                    <div class="choose-plan__text"></div>
                <?php endif; ?>
                <div class="choose-plan__button">
                    <button type="button" class="button" :class="level === <?= $level_options['essencial'] ?> ? 'button--solid' : 'button--outline'" @click="level = <?= $level_options['essencial'] ?>">
                        <span x-text="(level === <?= $level_options['essencial'] ?> ? '<?= $selected_plan_str ?>' : '<?= $select_plan_str ?>')"></span>
                    </button>
                </div>
            </div>

            <div class="choose-plan__plan">
                <div class="choose-plan__title">
                    <strong>Vivência</strong>
                </div>
                <?php if (is_active_sidebar('vantagens_vivencia')): ?>
                    <?php dynamic_sidebar('vantagens_vivencia'); ?>
                <?php else: ?>
                    <div class="choose-plan__text"></div>
                <?php endif; ?>
                <div class="choose-plan__button">
                    <button type="button" class="button" :class="level === <?= $level_options['vivencia'] ?> ? 'button--solid' : 'button--outline'" @click="level = <?= $level_options['vivencia'] ?>">
                        <span x-text="(level === <?= $level_options['vivencia'] ?> ? '<?= $selected_plan_str ?>' : '<?= $select_plan_str ?>')"></span>
                    </button>
                </div>
            </div>

            <div class="choose-plan__plan">
                <div class="choose-plan__title">
                    <strong>Institucional</strong>
                </div>
                <?php if (is_active_sidebar('vantagens_institucional')): ?>
                    <?php dynamic_sidebar('vantagens_institucional'); ?>
                <?php else: ?>
                    <div class="choose-plan__text"></div>
                <?php endif; ?>
                <div class="choose-plan__button">
                    <button type="button" class="button" :class="level === <?= $level_options['institucional'] ?> ? 'button--solid' : 'button--outline'" @click="level = <?= $level_options['institucional'] ?>">
                        <span x-text="(level === <?= $level_options['institucional'] ?> ? '<?= $selected_plan_str ?>' : '<?= $select_plan_str ?>')"></span>
                    </button>
                </div>
            </div>
        </div>

        <input type="hidden" name="_<?= $name ?>" :value="level">
    </div>
<?php
}

/**
 * Campo do wizard de associação do novo modelo (step 3): cards dos planos
 * disponíveis no CRM (nome + créditos por faixa + desconto) e card
 * institucional ("fale com a gente" — planos manuais do comercial).
 *
 * O valor submetido é o GUID do plano, normalizado e validado contra a
 * lista do CRM. O Alpine trabalha com o índice do plano (mesmo padrão
 * numérico do campo legado); o mapa índice => GUID vive no estado. Fail
 * closed: sem planos (CRM indisponível e sem cache), aviso em vez de cards.
 */
function render_association_plans_field (string $name, $value, array $definition) {
    $kit = filter_input(INPUT_GET, 'kit', FILTER_SANITIZE_ADD_SLASHES) ?? '';

    $planos = function_exists('hacklabr\obter_planos_disponiveis')
        ? \hacklabr\obter_planos_disponiveis()
        : null;

    if (empty($planos)) {
?>
    <div class="choose-plan__unavailable">
        <p><?= esc_html__('Não foi possível carregar os planos de associação no momento. Atualize a página ou tente novamente em alguns minutos.', 'hacklabr') ?></p>
    </div>
<?php
        return;
    }

    // Índice do plano no estado Alpine => GUID enviado no submit.
    $plano_index = [];
    $planos_js = [];

    foreach ($planos as $i => $plano) {
        $guid = \hacklabr\normalizar_guid($plano->id);
        $plano_index[$guid] = $i;
        $planos_js[$i] = $guid;
    }

    $initial_index = null;

    if (!empty($value)) {
        $initial_index = $plano_index[\hacklabr\normalizar_guid($value)] ?? null;
    } elseif (!empty($kit)) {
        // Pré-seleção de campanha (kit = slug do plano, ex. ?kit=ethos-agir)
        foreach ($planos as $i => $plano) {
            if (sanitize_title($plano->nome) === $kit) {
                $initial_index = $i;
                break;
            }
        }
    }

    $select_plan_str = esc_attr(__('Select plan', 'hacklabr'));
    $selected_plan_str = esc_attr(__('Selected plan', 'hacklabr'));

    $contact_page = get_page_by_path('contato');
    $contact_url = !empty($contact_page) ? get_permalink($contact_page) : home_url('/');
?>
    <div class="choose-plan__input choose-plan__input--association" x-data='<?= wp_json_encode(['planos' => $planos_js, 'plano' => $initial_index], JSON_HEX_APOS) ?>'>
        <div class="choose-plan__grid">
            <?php foreach ($planos as $i => $plano): ?>
            <div class="choose-plan__plan">
                <div class="choose-plan__title">
                    <strong><?= esc_html($plano->nome) ?></strong>
                </div>
                <div class="choose-plan__button">
                    <button type="button" class="button" :class="plano === <?= $i ?> ? 'button--solid' : 'button--outline'" @click="plano = <?= $i ?>">
                        <span x-text="(plano === <?= $i ?> ? '<?= $selected_plan_str ?>' : '<?= $select_plan_str ?>')"></span>
                    </button>
                </div>
                <div class="choose-plan__text">
                    <ul>
                        <?php foreach ($plano->faixas as $letra => $creditos): ?>
                        <li><?= sprintf(esc_html__('Faixa %s · %d créditos', 'hacklabr'), esc_html($letra), (int) $creditos) ?></li>
                        <?php endforeach; ?>
                        <?php if (!empty($plano->desconto)): ?>
                        <li><?= sprintf(esc_html__('Até %s%% de desconto em inscrições', 'hacklabr'), esc_html(number_format_i18n($plano->desconto, 0))) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="choose-plan__plan choose-plan__plan--manual">
                <div class="choose-plan__title">
                    <strong>Institucional</strong>
                </div>
                <div class="choose-plan__button">
                    <a class="button button--outline" href="<?= esc_url($contact_url) ?>"><?= esc_html__('Fale com a gente', 'hacklabr') ?></a>
                </div>
                <div class="choose-plan__text">
                    <ul>
                        <li><?= esc_html__('Planos com cotas próprias, atendidos pelo nosso time comercial.', 'hacklabr') ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <input type="hidden" name="_<?= esc_attr($name) ?>" :value="plano === null ? '' : planos[plano]">
    </div>
<?php
}
