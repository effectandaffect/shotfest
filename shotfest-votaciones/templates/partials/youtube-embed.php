<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Variables esperadas:
 *   $video_url string  URL de YouTube
 */

if ( ! function_exists( 'sf_extract_video_id' ) ) {
    function sf_extract_video_id( string $url ): string {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_\-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_\-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_\-]{11})/',
        ];
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $m ) ) {
                return $m[1];
            }
        }
        return '';
    }
}

$video_id = sf_extract_video_id( $video_url ?? '' );
if ( ! $video_id ) {
    echo '<p class="sf-aviso">' . esc_html__( 'Vídeo no disponible.', 'shotfest-votaciones' ) . '</p>';
    return;
}
?>
<div class="sf-video-wrapper">
    <iframe
        src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $video_id ); ?>?rel=0&modestbranding=1"
        title="<?php echo esc_attr( $titulo ?? '' ); ?>"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        loading="lazy"
    ></iframe>
</div>
