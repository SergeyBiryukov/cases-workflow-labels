<?php
/*
Plugin Name: Cases. Kernel. Workflow Labels
Plugin URI: http://wpcases.com/
Description: Ярлыки для сортировки и обработки дел по стандарту документооборота.
Author: Sergey Biryukov, Ivan Vinogradov
Author URI: http://profiles.wordpress.org/sergeybiryukov/
Version: 0.3
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
		'rewrite' => array( 'slug' => 'label' ),
	) );
}
add_action( 'init', 'cwl_register_taxonomy' );

function cwl_add_labels_to_new_cases( $meta_id, $object_id, $meta_key, $meta_value ) {
	switch ( $meta_key ) {
		case 'initiator' :
			$label_name = $meta_value . '_Исходящие';
			wp_set_post_terms( $object_id, $label_name, 'labels', true );
			break;
		case 'responsible' :
		case 'participant' :
			$user_ids = explode( ',', $meta_value );
			foreach ( (array) $user_ids as $user_id ) {
				$label_name = $user_id . '_Входящие';
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
		<p class="howto">Ярлыки разделяются запятыми</p>
	</div>
	<div class="tagchecklist"></div>
</div>
<p class="hide-if-no-js"><a id="link-labels" class="tagcloud-link" href="#titlediv">Выбрать из часто используемых ярлыков</a></p>
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
?>