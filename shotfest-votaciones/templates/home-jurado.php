<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $periodo         WP_Post
 *   $spots           WP_Post[]
 *   $votos_emitidos  array<int,int>  spot_id => valor
 */

use ShotfestVotaciones\Domain\PeriodoService;

$edicion_id     = get_post_meta( $periodo->ID, '_sf_periodo_edicion_id', true );
$edicion        = $edicion_id ? get_post_meta( $edicion_id, '_sf_edicion_anio', true ) : '';
$edicion_titulo = $edicion_id ? get_the_title( $edicion_id ) : '';
$fecha_fin      = get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true );
$ts_fin         = PeriodoService::fecha_a_timestamp( (string) $fecha_fin );

$total   = count( $spots );
$votados = count( array_filter( $spots, fn( $s ) => isset( $votos_emitidos[ $s->ID ] ) ) );
$pct     = $total > 0 ? round( $votados / $total * 100 ) : 0;

$spots_by_cat = [];
foreach ( $spots as $spot ) {
    $terms = get_the_terms( $spot->ID, 'spot_categoria' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $spots_by_cat[ $term->slug ]['name']    = $term->name;
            $spots_by_cat[ $term->slug ]['spots'][] = $spot;
        }
    } else {
        $spots_by_cat['_sin_cat']['name']    = __( 'Sin categoría', 'shotfest-votaciones' );
        $spots_by_cat['_sin_cat']['spots'][] = $spot;
    }
}
// Las pestañas solo aportan algo si hay alguna categoría de verdad
$has_categories = ! empty( array_diff( array_keys( $spots_by_cat ), [ '_sin_cat' ] ) );
?>
<header class="sf-head">
    <div class="sf-head-left">
        <p class="sf-eyebrow">
            <?php
            if ( $edicion_titulo ) {
                echo esc_html( $edicion ? sprintf( '%s · Edición %s', $edicion_titulo, $edicion ) : $edicion_titulo );
            } else {
                esc_html_e( 'Festival de Spots en Cine', 'shotfest-votaciones' );
            }
            ?>
        </p>
        <h1 class="sf-titulo"><?php esc_html_e( 'Vota los spots finalistas', 'shotfest-votaciones' ); ?></h1>
        <p class="sf-head-periodo">
            <?php echo esc_html( $periodo->post_title ); ?>
            <?php if ( null !== $ts_fin ) : ?>
                &nbsp;·&nbsp;<?php echo esc_html( sprintf(
                    /* translators: %s: fecha de cierre de la votación */
                    __( 'cierra el %s', 'shotfest-votaciones' ),
                    wp_date( 'j \d\e F \d\e Y', $ts_fin )
                ) ); ?>
            <?php endif; ?>
        </p>
    </div>

    <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="sf-logout">
        <?php esc_html_e( 'Cerrar sesión', 'shotfest-votaciones' ); ?>
    </a>
</header>

<div>
    <div class="sf-contador">
        <span class="sf-contador-cifra">
            <?php echo esc_html( str_pad( (string) $votados, 2, '0', STR_PAD_LEFT ) ); ?><span>/<?php echo esc_html( str_pad( (string) $total, 2, '0', STR_PAD_LEFT ) ); ?></span>
        </span>
        <span class="sf-contador-txt"><?php esc_html_e( 'spots votados', 'shotfest-votaciones' ); ?></span>
    </div>
    <div class="sf-rail" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $votados ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $total ); ?>">
        <div class="sf-rail-fill" style="width: <?php echo esc_attr( (string) $pct ); ?>%"></div>
    </div>
</div>

<?php if ( $has_categories ) : ?>
    <div class="sf-tabs" role="tablist">
        <button type="button" class="sf-tab" data-sf-cat="all" role="tab" aria-selected="true">
            <?php esc_html_e( 'Todos', 'shotfest-votaciones' ); ?>
            <span class="sf-tab-count"><?php echo esc_html( $votados . '/' . $total ); ?></span>
        </button>
        <?php foreach ( $spots_by_cat as $slug => $cat_data ) :
            $cat_spots = $cat_data['spots'];
            $cat_voted = count( array_filter( $cat_spots, fn( $s ) => isset( $votos_emitidos[ $s->ID ] ) ) );
        ?>
            <button type="button" class="sf-tab" data-sf-cat="<?php echo esc_attr( $slug ); ?>" role="tab" aria-selected="false">
                <?php echo esc_html( $cat_data['name'] ); ?>
                <span class="sf-tab-count"><?php echo esc_html( $cat_voted . '/' . count( $cat_spots ) ); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ( empty( $spots ) ) : ?>
    <p class="sf-aviso"><?php esc_html_e( 'No hay spots disponibles para votar en este momento.', 'shotfest-votaciones' ); ?></p>
<?php else : ?>
    <div class="sf-lista">
        <?php foreach ( $spots as $i => $spot ) :
            $ya_votado  = isset( $votos_emitidos[ $spot->ID ] );
            $valor_voto = $votos_emitidos[ $spot->ID ] ?? null;
            $indice     = $i;
            $terms      = get_the_terms( $spot->ID, 'spot_categoria' );
            $cat_slugs  = ( $terms && ! is_wp_error( $terms ) )
                ? array_map( fn( $t ) => $t->slug, $terms )
                : [ '_sin_cat' ];
            include SF_PLUGIN_DIR . 'templates/partials/spot-card.php';
        endforeach; ?>
    </div>
<?php endif; ?>

<?php if ( $has_categories ) : ?>
<script>
(function(){
    var tabs  = document.querySelectorAll('.sf-tab');
    var cards = document.querySelectorAll('.sf-spot-card');
    tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            tabs.forEach(function(t){ t.setAttribute('aria-selected','false'); });
            tab.setAttribute('aria-selected','true');
            var cat = tab.getAttribute('data-sf-cat');
            cards.forEach(function(card){
                var slugs = (card.getAttribute('data-sf-cats') || '').split(',');
                card.style.display = (cat === 'all' || slugs.indexOf(cat) >= 0) ? '' : 'none';
            });
        });
    });
})();
</script>
<?php endif; ?>
