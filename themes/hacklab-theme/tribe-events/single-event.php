<?php

global $post;

get_header();

$post_id  = get_the_ID();
$category = get_the_category();
$excerpt = !empty($post->post_excerpt) ? wp_kses_post($post->post_excerpt) : '';
$terms    = get_html_terms( $post_id , 'tribe_events_cat' );
$workload = get_post_meta($post_id, 'carga_horaria_total', true );
$investiment = get_post_meta($post_id, '_EventCost', true );
$local = tribe_get_venue($post_id);
$start = get_post_meta($post_id, '_EventStartDate', true );
$end = get_post_meta($post_id, '_EventEndDate', true );
$recurrence = get_post_meta($post_id, '_EventRecurrence', true );
$pdf = get_field( 'pdf', $post_id );
$inscrever = get_field( 'inscrever', $post_id );


$event = tribe_get_event( get_the_ID() );

if ( isset( $event->_tec_occurrence ) && $event->_tec_occurrence instanceof \TEC\Events\Custom_Tables\V1\Models\Occurrence ) {
    $series_id = (int) $event->_tec_occurrence->post_id;
} elseif ( ! empty( $event->post_parent ) ) {
    $series_id = (int) $event->post_parent;
} else {
    $series_id = (int) $event->ID;
}

$next = tribe_get_events( [
    'posts_per_page'       => 1,
    'eventDisplay'         => 'custom',
    'starts_after'         => 'now',
    'orderby'              => 'event_date',
    'order'                => 'ASC',
    'tribeHideRecurrence'  => false,
    'in_series'            => $series_id,
    'post_parent'          => $series_id,
] );

do_action( 'logger', $next );

$display_event = ! empty( $next ) ? $next[0] : $event;
$display_id    = $display_event->ID;

$start = tribe_get_start_date( $display_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT );
$end   = tribe_get_end_date( $display_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT );


if( isset($recurrence['rules']) ) {

    foreach ($recurrence['rules'] as $rule) {

        if ($rule['custom']){

            $type = $rule['type'];
            $interval = isset($rule['custom']['interval']) ? $rule['custom']['interval'] : '';
            $days = isset($rule['custom']['week']['day']) ? $rule['custom']['week']['day'] : array();
            $same_time = isset($rule['custom']['same-time']) ? $rule['custom']['same-time'] : '';
            $recurrence_type = isset($rule['custom']['type']) ? $rule['custom']['type'] : '';
            $end_type = isset($rule['end-type']) ? $rule['end-type'] : '';
            $end_count = isset($rule['end-count']) ? $rule['end-count'] : '';
            $event_serie = $post->post_name;

            if($days) {
                $week_days = array(
                    '1' => __('Monday', 'hacklabr'),
                    '2' => __('Tuesday', 'hacklabr'),
                    '3' => __('Wednesday', 'hacklabr'),
                    '4' => __('Thursday', 'hacklabr'),
                    '5' => __('Friday', 'hacklabr'),
                    '6' => __('Saturday', 'hacklabr'),
                    '7' => __('Sunday', 'hacklabr')
                );

                $day_names = array_map(function($day) use ($week_days) {
                    return isset($week_days[$day]) ? $week_days[$day] : '';
                }, $days);

                foreach ($day_names as $day_name) {
                    $day_name;
                }
            }
            if($recurrence_type){
                if($recurrence_type == 'Daily') {
                    $recurrence_type = 'Diário';
                }elseif($recurrence_type == 'Weekly') {
                    $recurrence_type = 'Semanal';
                }elseif($recurrence_type =='Monthly') {
                    $recurrence_type = 'Mensal';
                }elseif($recurrence_type =='Date'){
                    $recurrence_type = 'Repetição única';
                }
            }
        }
    }
}

?>
<header class="post-header">
    <div class="post-header__postdata alingfull">
        <div class="post-header__postdata__date container--wide">
            <?php if ( $terms ) : ?>
                <div class="tag-event -text-center">
                    <span class="tag"><?php echo $terms ?></span>
                </div>
            <?php endif; ?>
            <h1 class="post-header__title"> <?php the_title(); ?> </h1>
            <?php if ( $excerpt ) : ?>
                <p class="post-header__excerpt container"><?= get_the_excerpt() ?></p>
            <?php endif; ?>
            <div class="post-header__meta container">
                <p class="post-header__date"><?php _e('Published in ', 'hacklabr') ?><?= get_the_date() ?></p>
                <?php get_template_part('template-parts/share-links', null, ['link' => get_the_permalink()]) ?>
            </div>
        </div>
    </div>
    <div class="event-metadada-section">
        <div class="event-metadada container">
            <div class="event-metadada__infos -text-center">
                <?php
                if ($start) :
                    $date = new DateTime($start);
                    $formatted_date_time = $date->format('d/m/Y H:i:s');
                    ?>
                    <div class="event-metadada__infos__date">
                        <p class="-bold"><?php _e('Start', 'hacklabr') ?></p>
                        <?php echo apply_filters('the_content', $formatted_date_time); ?>
                    </div>
                <?php endif; ?>
                <?php if ($end) :
                    $date = new DateTime($end);
                    $formatted_date_time = $date->format('d/m/Y H:i:s');
                    ?>
                    <div class="event-metadada__infos__date">
                        <p class="-bold"><?php _e('End', 'hacklabr') ?></p>
                        <?php echo apply_filters('the_content', $formatted_date_time); ?>
                    </div>
                <?php endif; ?>
                <?php if ($workload) : ?>
                    <div class="event-metadada__infos__workload">
                        <p class="-bold"><?php _e('Workload', 'hacklabr') ?></p>
                        <?php echo apply_filters('the_content', $workload); ?>
                    </div>
                <?php endif; ?>
                <?php if ($investiment) : ?>
                <div class="event-metadada__infos__investment">
                    <p class="-bold"><?php _e('Investment', 'hacklabr') ?></p>
                    <?php echo apply_filters('the_content', $investiment); ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($recurrence) : ?>
                <div class="event-metadada__recurrence -text-center">

                    <?php if ($recurrence_type) : ?>
                        <div class="event-metadada__recurrence__type">
                            <p class="-bold"><?php _e('Recurrence', 'hacklabr') ?></p>
                            <?php echo apply_filters('the_content', $recurrence_type); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($end_count) : ?>
                        <div class="event-metadada__recurrence__count">
                            <p class="-bold"><?php _e('Number of events', 'hacklabr') ?></p>
                            <?php echo apply_filters('the_content', $end_count); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($day_name)) : ?>

                        <div class="event-metadada__recurrence__weekday">
                            <p class="event-metadada__recurrence__weekday__title -bold"> <?php _e('Weekday', 'hacklabr') ?></p>
                                <div class="event-metadada__recurrence__weekday__day">
                                    <?php foreach ($day_names as $day) : ?>
                                        <?php echo apply_filters('the_content', $day); ?>
                                        <span>,</span>
                                    <?php endforeach; ?>
                                </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <?php if ($local) : ?>
                <div class="event-metadada__location -text-center">
                    <div class="event-metadada__location__local">
                        <p class="-bold"><?php _e('Local', 'hacklabr') ?></p>
                        <?php echo apply_filters('the_content', $local); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</header>

<main class="post-content stack container">
    <?php the_content() ?>

    <div class="btn">
        <?php
        if ( $pdf || $inscrever ) {
            if ( $pdf["arquivo"] ) : ?>
                <a class="button button--outline" href="<?= $pdf["arquivo"];?>"><?php _e($pdf["botao"], 'hacklabr');?></a>
            <?php endif; ?>

            <?php
            if ( $inscrever["link"] ) : ?>
                <a class="button button--solid" href="<?= $inscrever["link"];?>"><?php _e($inscrever["botao"], 'hacklabr');?></a>
            <?php endif;
        } ?>
    </div>


    <?php
    if ($id = get_post_meta($post_id, '_ethos_crm:fut_pf_id', true)) {
        if (isset($_GET['certificado'])) {
            echo do_shortcode('[ethosGeraCertificado2]');
        } else {
            echo do_shortcode('[ethosDadosEvento menu_order=""]');
            echo do_shortcode('[ethosDadosEventoCorpo tp_evt=""]');
            /*
            if (hacklabr\is_paid_event($post_id)) {
                echo do_shortcode('[ethosDadosEventoCorpo tp_evt=""]');
            } else {
                echo do_shortcode('[event-registration]');
            }
            */
        }
    }
    ?>
</main>

<?php get_footer() ?>
