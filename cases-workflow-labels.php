<?php
/*
Plugin Name: Cases. Kernel. Workflow Labels
Plugin URI: http://wpcases.com/
Description: Ярлыки для сортировки и обработки дел по стандарту документооборота.
Author: Sergey Biryukov, Ivan Vinogradov
Author URI: http://profiles.wordpress.org/sergeybiryukov/
Version: 0.3.2
*/ 

function cwl_register_taxonomy() {
	$labels = array(
		'name' => 'Ярлыки',
		'singular_name' => 'Ярлык',
		'search_items' => 'Поиск ярлыков',
		'popular_items' => 'Популярные ярлыки',
		'all_items' => 'Все ярлыки',
		'parent_item' => null,
		'parent_item_colon' => null,
		'edit_item' => 'Изменить ярлык',
		'update_item' => 'Обновить ярлык',
		'add_new_item' => 'Добавить новый ярлык',
		'new_item_name' => 'Название нового ярлыка',
		'separate_items_with_commas' => 'Ярлыки разделяются запятыми',
		'add_or_remove_items' => 'Добавить или удалить ярлыки',
		'choose_from_most_used' => 'Выбрать из часто используемых ярлыков',
		'menu_name' => 'Ярлыки',
	); 

	register_taxonomy( 'labels', 'cases', array(
		'hierarchical' => false,
		'labels' => $labels,
		'show_ui' => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var' => true,
		'rewrite' => array(
			'slug' => 'label'
		),
	) );
}
add_action( 'init', 'cwl_register_taxonomy' );

function cwl_add_labels_to_new_cases( $meta_id, $object_id, $meta_key, $meta_value ) {
	switch ( $meta_key ) {
		case 'initiator' :
			$label_name = $meta_value . '-Исходящие';
			wp_set_post_terms( $object_id, $label_name, 'labels', true );
			break;
		case 'responsible' :
		case 'participant' :
			$user_ids = explode( ',', $meta_value );
			foreach ( (array) $user_ids as $user_id ) {
				$label_name = $user_id . '-Входящие';
				wp_set_post_terms( $object_id, $label_name, 'labels', true );
			}
			break;
	}
}
add_action( 'added_post_meta', 'cwl_add_labels_to_new_cases', 10, 4 );
add_action( 'updated_post_meta', 'cwl_add_labels_to_new_cases', 10, 4 );

function cwl_enqueue_scripts() {
	if ( is_singular( 'persons' ) ) {
		wp_enqueue_style( 'cases-workflow-labels', plugins_url( 'cases-workflow-labels.css', __FILE__ ), array(), '1.0' );

		wp_enqueue_script( 'cases-workflow-labels', plugins_url( 'cases-workflow-labels.js', __FILE__ ), array( 'jquery' ), '1.0', true );
		wp_localize_script( 'cases-workflow-labels', 'cwlAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'cwl_enqueue_scripts' );

function cwl_add_label_box() {
?>
<div id="labels" class="tagsdiv">
	<div class="jaxtag">
 		<div class="ajaxtag hide-if-no-js">
			<label for="new-tag-labels" class="screen-reader-text">Ярлыки</label>
			<div class="taghint" style="">Добавить новый ярлык</div>
			<p><input type="text" value="" autocomplete="off" size="16" class="newtag form-input-tip" name="newtag[labels]" id="new-tag-labels">
			<input type="button" tabindex="3" value="Добавить" class="button tagadd"></p>
		</div>
		<!-- <p class="howto">Ярлыки разделяются запятыми</p> -->
	</div>
	<div class="tagchecklist"></div>
</div>
<!-- <p class="hide-if-no-js"><a id="link-labels" class="tagcloud-link" href="#titlediv">Выбрать из часто используемых ярлыков</a></p> -->
<?php
}

function cwl_get_label_list( $args = '' ) {
	$defaults = array( 'sep' => ' &middot; ' , 'echo' => true );
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	$terms = get_terms( 'labels', 'hide_empty=0' );
	$count = count( $terms );
	$i = 0;
	$term_list = '';

	foreach ( (array) $terms as $term ) {
		$term_link = get_term_link( $term->slug, $term->taxonomy );
		if ( is_wp_error( $term_link ) )
			continue;

		$term_list .= sprintf( '<a href="%s" title="%s">%s</a> (%d)',
			$term_link,
			sprintf( 'Просмотреть все дела с ярлыком %s', $term->name ),
			$term->name,
			$term->count
		);

		if ( $count != ++$i )
			$term_list .= $sep;
	}

	if ( $echo )
		echo $term_list;
	else
		return $term_list;
}

function cwl_ajax_create_label() {
	$tag = wp_insert_term( $_POST['labels'], 'labels' );

	cwl_get_label_list();

	die();
}
// add_action( 'wp_ajax_create_label', 'cwl_ajax_create_label' );

function cwl_ajax_add_labels() {
	// echo 'test';
	// echo '<pre>'; print_r( $_POST ); echo '</pre>';
	// die();
	// echo '<pre>'; print_r( $posts ); echo '</pre>';

	if ( empty( $_POST['posts'] ) ) {
		wp_insert_term( $_POST['labels'], 'labels' );
	} else {
		foreach ( (array) $_POST['posts'] as $post_id )
			wp_set_post_terms( $post_id, $_POST['labels'], 'labels', true );
	}

	cwl_get_label_list();

	die();
}
add_action( 'wp_ajax_add_labels', 'cwl_ajax_add_labels' );

function cwl_get_person_id_by_email( $email ) {

	$person = new WP_Query( array(
		'fields' => 'ids',
		'post_type' => 'persons',
		'meta_query' => array( array( 'key' => 'email', 'value' => $email ) ),
	) );

	return array_shift( $person->posts );
}

function cwl_filter_labels_by_person( $args, $taxonomies ) {
	if ( ! is_user_logged_in() || array( 'labels' ) !== $taxonomies )
		return $args;

	$person_id = cwl_get_person_id_by_email( wp_get_current_user()->user_email );

	if ( empty( $args['name__like'] ) )
		$args['name__like'] = $person_id . '-';

	return $args;
}
add_filter( 'get_terms_args', 'cwl_filter_labels_by_person', 10, 2 );

function cwl_hide_prefix_from_term_names( $terms, $taxonomies, $args ) {
	if ( ! is_user_logged_in() || array( 'labels' ) !== $taxonomies )
		return $terms;

	if ( ! is_array( $terms ) )
		return $terms;

	$person_id = cwl_get_person_id_by_email( wp_get_current_user()->user_email );

	foreach ( $terms as $key => $term )
		$terms[ $key ]->name = str_replace( $person_id . '-', '', $term->name );

	return $terms;
}
add_filter( 'get_terms', 'cwl_hide_prefix_from_term_names', 10, 3 );

function cwl_remove_from_inbox() {
	global $post;

	if ( ! isset( $_GET['action'] ) || 'remove-from-inbox' != $_GET['action'] )
		return;

	if ( ! is_user_logged_in() || ! is_singular( 'cases' ) )
		return;

	$person_id = cwl_get_person_id_by_email( wp_get_current_user()->user_email );

	$terms = wp_get_post_terms( $post->ID, 'labels', array( 'fields' => 'names' ) );
	$terms = array_diff( $terms, array( $person_id . '-Входящие' ) );

	wp_set_post_terms( $post->ID, $terms, 'labels' );

	wp_redirect( get_permalink() );
	die();
}
add_action( 'wp', 'cwl_remove_from_inbox' );

function cwl_echo_remove_from_inbox_link() {
	global $post;

	if ( 'cases' != $post->post_type )
		return;

	$person_id = cwl_get_person_id_by_email( wp_get_current_user()->user_email );
	if ( ! has_term( sanitize_title( $person_id . '-Входящие' ), 'labels', $post->ID ) )
		return;

	$url = add_query_arg( array( 'action' => 'remove-from-inbox' ), get_permalink() );

	echo sprintf( '<a class="btn btn-mini" href="%s">Убрать из Входящих</a>', $url );
}
add_action( 'roots_entry_meta_before', 'cwl_echo_remove_from_inbox_link' );

/*
function cwl_convert_labels() {
	remove_filter( 'get_terms', 'cwl_hide_prefix_from_term_names', 10, 3 );
	$terms = get_terms( 'labels', 'hide_empty=0' );

	foreach ( $terms as $key => $term ) {
		$term->name = str_replace( '_', '-', $term->name );
		$term->slug = str_replace( '_', '-', $term->slug );
		wp_update_term( $term->term_id, 'labels', array( 'name' => $term->name, 'slug' => $term->slug ) );
	}

}
// add_action( 'init', 'cwl_convert_labels' );
*/
?>