<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $periodo         WP_Post
 *   $spots           WP_Post[]
 *   $votos_emitidos  array<int,int>  spot_id => valor
 */

$edicion_id     = get_post_meta( $periodo->ID, '_sf_periodo_edicion_id', true );
$edicion        = $edicion_id ? get_post_meta( $edicion_id, '_sf_edicion_anio', true ) : '';
$edicion_titulo = $edicion_id ? get_the_title( $edicion_id ) : '';
$fecha_fin  = get_post_meta( $periodo->ID, '_sf_periodo_fecha_fin', true );
$total     = count( $spots );
$votados   = count( array_filter( $spots, fn( $s ) => isset( $votos_emitidos[ $s->ID ] ) ) );

$spots_by_cat = [];
$cat_all_key  = '_all';
foreach ( $spots as $spot ) {
    $terms = get_the_terms( $spot->ID, 'spot_categoria' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $spots_by_cat[ $term->slug ][ 'name' ]    = $term->name;
            $spots_by_cat[ $term->slug ][ 'spots' ][] = $spot;
        }
    } else {
        $spots_by_cat[ '_sin_cat' ][ 'name' ]    = __( 'Sin categoría', 'shotfest-votaciones' );
        $spots_by_cat[ '_sin_cat' ][ 'spots' ][] = $spot;
    }
}
// Basta con que haya una categoría real para mostrar las pestañas. Antes se exigía
// count() > 1, así que con una sola categoría desaparecían y se caía a la barra de progreso.
$has_categories = ! empty( array_diff( array_keys( $spots_by_cat ), [ '_sin_cat' ] ) );
?>
<div class="sf-home-jurado">
    <header class="sf-header">
        <div class="sf-header-left">
            <p class="sf-header-label">
                <?php
                if ( $edicion_titulo ) {
                    echo esc_html( $edicion ? sprintf( '%s · Edición %s', $edicion_titulo, $edicion ) : $edicion_titulo );
                } else {
                    esc_html_e( 'Festival de Spots en Cine', 'shotfest-votaciones' );
                }
                ?>
            </p>
            <p class="sf-header-periodo"><?php echo esc_html( $periodo->post_title ); ?></p>
            <h1 class="sf-titulo"><?php esc_html_e( 'Vota los spots finalistas', 'shotfest-votaciones' ); ?></h1>
        </div>
        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="sf-logout-btn">
            <?php esc_html_e( 'Cerrar sesión', 'shotfest-votaciones' ); ?>
        </a>
    </header>

    <?php
    $ts_fin = \ShotfestVotaciones\Domain\PeriodoService::fecha_a_timestamp( (string) $fecha_fin );
    if ( null !== $ts_fin ) : ?>
        <p class="sf-fecha-cierre">
            <?php echo esc_html( sprintf(
                __( 'La votación cierra el %s', 'shotfest-votaciones' ),
                wp_date( 'j \d\e F \d\e Y', $ts_fin )
            ) ); ?>
        </p>
    <?php endif; ?>

    <?php if ( $has_categories ) : ?>
        <div class="sf-category-tabs" role="tablist">
            <button class="sf-category-tab sf-tab-active" data-sf-cat="all" role="tab" aria-selected="true">
                <?php esc_html_e( 'Todos', 'shotfest-votaciones' ); ?>
                <span class="sf-category-count"><?php echo esc_html( $votados . '/' . $total ); ?></span>
            </button>
            <?php foreach ( $spots_by_cat as $slug => $cat_data ) :
                $cat_spots  = $cat_data['spots'];
                $cat_voted  = count( array_filter( $cat_spots, fn( $s ) => isset( $votos_emitidos[ $s->ID ] ) ) );
                $cat_total  = count( $cat_spots );
            ?>
                <button class="sf-category-tab" data-sf-cat="<?php echo esc_attr( $slug ); ?>" role="tab" aria-selected="false">
                    <?php echo esc_html( $cat_data['name'] ); ?>
                    <span class="sf-category-count"><?php echo esc_html( $cat_voted . '/' . $cat_total ); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="sf-progreso-wrap">
            <p class="sf-progreso-texto">
                <?php echo esc_html( sprintf( __( '%d de %d spots votados', 'shotfest-votaciones' ), $votados, $total ) ); ?>
            </p>
            <div class="sf-progreso-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $votados ); ?>" aria-valuemax="<?php echo esc_attr( (string) $total ); ?>">
                <div class="sf-progreso-fill" style="width: <?php echo $total > 0 ? esc_attr( (string) round( $votados / $total * 100 ) ) : '0'; ?>%"></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( empty( $spots ) ) : ?>
        <p class="sf-aviso"><?php esc_html_e( 'No hay spots disponibles para votar en este momento.', 'shotfest-votaciones' ); ?></p>
    <?php else : ?>
        <div class="sf-spots-grid">
            <?php foreach ( $spots as $spot ) :
                $ya_votado  = isset( $votos_emitidos[ $spot->ID ] );
                $valor_voto = $votos_emitidos[ $spot->ID ] ?? null;
                $terms      = get_the_terms( $spot->ID, 'spot_categoria' );
                $cat_slugs  = ( $terms && ! is_wp_error( $terms ) )
                    ? array_map( fn( $t ) => $t->slug, $terms )
                    : [ '_sin_cat' ];
                include SF_PLUGIN_DIR . 'templates/partials/spot-card.php';
            endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:34px;display:flex;align-items:center;justify-content:space-between;padding-top:24px;border-top:1px solid var(--sf-border)">
        <span class="sf-progreso-texto"><?php echo esc_html( sprintf( __( '%d de %d spots votados en total', 'shotfest-votaciones' ), $votados, $total ) ); ?></span>
    </div>
</div>

<?php if ( $has_categories ) : ?>
<script>
(function(){
    var tabs = document.querySelectorAll('.sf-category-tab');
    var cards = document.querySelectorAll('.sf-spot-card');
    tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            tabs.forEach(function(t){ t.classList.remove('sf-tab-active'); t.setAttribute('aria-selected','false'); });
            tab.classList.add('sf-tab-active');
            tab.setAttribute('aria-selected','true');
            var cat = tab.getAttribute('data-sf-cat');
            cards.forEach(function(card){
                if(cat === 'all') { card.style.display = ''; }
                else {
                    var slugs = (card.getAttribute('data-sf-cats') || '').split(',');
                    card.style.display = slugs.indexOf(cat) >= 0 ? '' : 'none';
                }
            });
        });
    });
})();
</script>
<?php endif; ?>
