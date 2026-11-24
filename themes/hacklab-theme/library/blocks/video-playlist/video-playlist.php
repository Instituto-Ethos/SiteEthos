<?php

namespace hacklabr;

/**
 * Video Playlist Gallery
 */

function embed($video){
	$home_url      = get_home_url();
	$video_id      = extract_vid_id( $video );
	$video_title   = extract_vid_title( $video );
	$channel_title = extract_vid_channel_title( $video );

	$iframe = "<div class=\"embed-template-block\">
				<p><strong>$video_title</strong></p>
				<p>$channel_title</p>
				<figure class=\"wp-block-embed is-type-video is-provider-youtube\">
					<div class=\"wp-block-embed__wrapper\">
						<iframe class=\"lazy-loaded\"
							loading=\"lazy\"
							title=\"$video_title\"
							data-lazy-type=\"iframe\"
							data-src=\"https://www.youtube-nocookie.com/embed/$video_id?origin=$home_url&showinfo=0&video-id=$video_id&modestbranding=1&rel=0\"
							allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\"
							allowfullscreen=\"\"
							src=\"https://www.youtube-nocookie.com/embed/$video_id?origin=$home_url&showinfo=0&video-id=$video_id&modestbranding=1&rel=0\"
							width=\"1200\" height=\"675\" frameborder=\"0\"></iframe>

						<noscript>
							<iframe loading=\"lazy\"
								title=\"$video_title\"
								width=\"1200\"
								height=\"675\"
								src=\"https://www.youtube-nocookie.com/embed/$video_id?origin=$home_url&showinfo=0&video-id=$video_id&modestbranding=1&rel=0\" frameborder=\"0\"
								allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen>
							</iframe>
						</noscript>
					</div>
				</figure>
			</div>";
	return $iframe;
}

function thumbnail($video){
    $thumb = "<a class=\"col-xs-6\" href=\"https://www.youtube.com/watch?v=" . extract_vid_id( $video ) . "\" target=\"_blank\">
                <img loading=\"lazy\" decoding=\"async\" src=\"" . extract_vid_thumbnail( $video ) . "\" />
                <h2>". extract_vid_title( $video ) ."</h2>
             </a>";

    return $thumb;
}

function block_header($title){
    return "<div class=\"video-gallery-block-title\">
                    <h2>". $title ."</h2>
                    <div class=\"title-line\"></div>
            </div>";
}

function get_content( $url, $referer ){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $referer);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function extract_vid_id( $video ){
    if(isset($video->snippet->resourceId->videoId)){
        return $video->snippet->resourceId->videoId;
    } else if (isset($video->id->videoId)){
        return $video->id->videoId;
    }
};

function extract_vid_title( $video ){
    if ( isset( $video->snippet->title ) ) {
        return $video->snippet->title;
    }
};

function extract_vid_thumbnail( $video ){
    if ( isset( $video->snippet->thumbnails->medium->url ) ){
        return $video->snippet->thumbnails->medium->url ;
    }
};

function extract_vid_channel_title( $video ) {
    if ( isset( $video->snippet->channelTitle ) ) {
        return $video->snippet->channelTitle;
    }
}

function get_author_by_archive(){
    global $wp;

    $authorSlug = basename( $wp->request );
    $user = get_user_by( 'slug', $authorSlug );

    return $user;
}

function render_video_playlist_callback( $attributes = [] ) {

    $api_key = get_option( 'youtube_key', '' );
    $youtube_format = ( isset( $attributes['youtubeFormat'] ) ) ? $attributes['youtubeFormat'] : 'channel';
    $youtube_id = ( isset( $attributes['youtubeId'] ) ) ? $attributes['youtubeId'] : '';

    if ( is_author() ) {
        $youtube_id = get_the_author_meta( 'youtube_channel_id', get_author_by_archive()->ID );
        $youtube_format = get_the_author_meta( 'youtube_format', get_author_by_archive()->ID );
    }

    if ( ! $api_key || ! $youtube_id ) {
        return;
    }

    $max_num = isset( $attributes['numItems'] ) ? $attributes['numItems'] : 5;
    $query_array = array(
        'key'        => $api_key,
        'maxResults' => $max_num,
        'part'       => 'snippet,id',
        'order'      => 'date'
    );

    if( $youtube_format == 'playlist' ){
        $query_array['playlistId'] = $youtube_id;
        $query = http_build_query($query_array);
        $url = "https://www.googleapis.com/youtube/v3/playlistItems?".$query;
    } else if( $youtube_format == 'channel' ){
        $query_array['channelId'] = $youtube_id;
        $query = http_build_query( $query_array );
        $url = "https://www.googleapis.com/youtube/v3/search?" . $query;
    }

    $cache_key = "video-playlist:" . md5( $url );

    if ( ! ( $result = get_transient( $cache_key ) ) ) {
        $result = json_decode( get_content( $url, get_site_url() ) );

        if ( is_object( $result ) && ! $result->error ) {
            set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
        } else {
            $result = (object) ['items' => []];
            set_transient( $cache_key, $result, 3 * MINUTE_IN_SECONDS );
        }
    }

    if ( ! $result->items ) {
        return;
    }

    $embeds = '';
    $style = isset( $attributes['style'] ) ? $attributes['style'] : 'sidebar';

    $videos = $result->items;

    $embeds .= "<div class='video-gallery-block video-" . $style . "'>";
    $embeds .=  isset( $attributes['title'] ) ? block_header( $attributes['title'] ) : '';
    $embeds .= "<div class='video-gallery-wrapper'>";

    $embeds .= embed( $videos[0] );

    array_shift( $videos );

    foreach( $videos as $video ){
        $embeds .= embed( $video );
    }

    $embeds .= "</div></div>";

    return $embeds;
}

function cookieyes_is_active() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    if ( is_plugin_active( 'cookie-law-info/cookie-law-info.php' ) ) {
        return true;
    } else {
        return false;
    }
}

function enqueue_scripts() {
    wp_localize_script( 'hacklabr-video-playlist-script', 'videoPlaylist', [
        'cookieYesActive' => cookieyes_is_active()
    ]);
}
add_action( 'wp_enqueue_scripts', 'hacklabr\\enqueue_scripts' );



