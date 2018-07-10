<?php
/*
Plugin Name: Objectives Key Results - OKR
Plugin URI: https://mkaion.com/wordpress-okr/
Description: WordPress OKR Plugin to manage objectives and key results.
Version: 1.01
Author: Mainul Kabir Aion
Author URI: https://mkaion.com/
License: GPL3
*/

/*
Thanks to Daryl L. L. Houston for the initial plugin.
TODO

* Percent complete per KR
* Weight of KR toward Objective completion
* Calculate/display completion
* Localize
* End date for O and KR
* Basic CSS, especially for labels in the shortcode.
* Make lots of stuff filterable.

*/

add_action('wp_enqueue_scripts','okr_init');

function okr_init() {
    wp_enqueue_script( 'okr', plugins_url( 'assets/js/script.js', __FILE__ ));
    wp_enqueue_script( 'okr', plugins_url( 'assets/css/style.css', __FILE__ ));
}

class OKR {

	const OBJECTIVE_POST_TYPE  = 'objective';
	const KEY_RESULT_POST_TYPE = 'key_result';
	const KEY_RESULT_META_KEY  = 'okr_key_result_meta';

	static function &init() {
		static $instance = false;

		if ( $instance ) {
			return $instance;
		}

		$instance = new OKR;

		return $instance;
	}

	public function __construct() {

		// Set up the post types.
		$this->register_objective_post_type();
		$this->register_key_result_post_type();
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'wp_insert_post_data', array( $this, 'save_key_result_data' ), 10, 2 );
		add_shortcode( 'okr', array( $this, 'shortcode' ) );

	}





	public function add_meta_boxes() {
		add_meta_box( 'okr_key_result_parent', 'Key Result Data', array( $this, 'add_key_result_meta_box' ), OKR::KEY_RESULT_POST_TYPE );
	}


	public function add_key_result_meta_box() {
		global $post;

		wp_nonce_field( 'okr_key_result_data', 'okr_key_result_data' );
		$objectives = get_posts( array( 'post_type' => OKR::OBJECTIVE_POST_TYPE ) );

		$okr_data = get_post_meta( $post->ID, OKR::KEY_RESULT_META_KEY, true );


		echo 'Select Objective';
		echo '<select name="key_result_parent">';
		echo '<option value="">Choose an Objective</option>';
		foreach ( $objectives as $objective ) {
			echo '<option value="' . (int) $objective->ID . '"' . selected( $objective->ID, $post->post_parent ) . '>' . esc_html ( $objective->post_title ) . '</option>';
		}
		echo '</select>';
		echo '<br />';

		echo 'Due Date:';
		echo '<input name="key_result_due_date" type="date" value="' . esc_attr( $okr_data['due_date'] ) . '" />';
		echo '<br />';

		echo 'Percent Complete: ';
		echo '<input name="key_result_percent_complete" type="number" min="0" max="100" value="' . (int) $okr_data['percent_complete'] . '" />';
		echo '  ';

		echo '<div class="range-slider">';
 		echo ' <input name="key_result_percent_complete" class="range-slider__range" type="range" min="0" max="100" value="' . (int) $okr_data['percent_complete'] . '">';
		 echo ' <span class="range-slider__value">$okr_data[percent_complete]</span>';
			echo '</div>';

		//echo '<input name="key_result_percent_complete" type="range" min="0" max="100" class="slider" value="' . (int) $okr_data['percent_complete'] . '" />';

		echo '<br />';

		echo 'Weight:';
		echo '<input name="key_result_weight" type="number" min="0" max="100" value="' . (int) $okr_data['weight'] . '" />';
		echo '<br />';
		?>
		<script type="text/javascript">
			var rangeSlider = function(){
  var slider = $('.range-slider'),
      range = $('.range-slider__range'),
      value = $('.range-slider__value');
    
  slider.each(function(){

    value.each(function(){
      var value = " <?php echo $okr_data['percent_complete'] ?>%" ;
      $(this).html(value);
    });

    range.on('input', function(){
      $(this).next(value).html(this.value);
    });
  });
};

rangeSlider();
		</script>
		<?php

	}

	public function save_key_result_data( $data, $post_array ) {
		global $post;

		if ( ! wp_verify_nonce( $_POST['okr_key_result_data'], 'okr_key_result_data' ) ) {
			return $data;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		if ( OKR::KEY_RESULT_POST_TYPE == $post->post_type ) {
			$data['post_parent'] = (int) $post_array['key_result_parent'];

			if ( ! isset( $this->key_result_meta ) ) {
				$this->key_result_meta = array(
					'due_date'         => '',
					'percent_complete' => 0,
					'weight'           => 1,
				);
			}
			if ( isset( $post_array['key_result_due_date'] ) && preg_match( '/\d\d\d\d-\d\d-\d\d/', $post_array['key_result_due_date'] ) ) {
				$this->key_result_meta['due_date'] = sanitize_text_field( $post_array['key_result_due_date'] );
			}

			if ( isset( $post_array['key_result_percent_complete'] ) && is_numeric( $post_array['key_result_percent_complete'] ) ) {
				$this->key_result_meta['percent_complete'] = (int) $post_array['key_result_percent_complete'];
			}

			if ( isset( $post_array['key_result_weight'] ) && is_numeric( $post_array['key_result_weight'] ) ) {
				$this->key_result_meta['weight'] = (int) $post_array['key_result_weight'];
			}

			update_post_meta( $post->ID, OKR::KEY_RESULT_META_KEY, $this->key_result_meta );
		}

		return $data;
	}

	public function shortcode( $attributes ) {
		$out = '';

		// Parse the shortcode for an ids or a slugs attribute and fetch the matching Objective ids.
		$ids = array();
		if ( isset( $attributes['slugs'] ) ) {
			$slugs = explode( ',', $attributes['slugs'] );
			foreach( $slugs as $slug ) {
				$page = get_page_by_path( trim( $slug ), OBJECT, OKR::OBJECTIVE_POST_TYPE );
				if ( $page ) {
					$ids[] = (int) $page->ID;
				}
			}
		} else if ( isset( $attributes['ids'] ) ) {
			$ids = explode( ',', $attributes['ids'] );
			array_walk( $ids, 'trim' );
			array_walk( $ids, 'intval' );
		} else {
			return '';
		}

		$objectives = get_posts( array(
			'post_type' => OKR::OBJECTIVE_POST_TYPE,
			'include'   => $ids
		) );


		foreach( $objectives as $objective ) {
			$key_results = get_posts( array(
				'post_type'   => OKR::KEY_RESULT_POST_TYPE,
				'post_parent' => $objective->ID
			) );
			$out .= '<div class="objective">';
			$out .= '<h3>' . esc_html( $objective->post_title ) . '</h3>';
			$out .= '<div class="objective-content">' . esc_html( $objective->post_content ) . '</div>';

			$weight = 0;
			$percent_complete = 0;

			foreach ( $key_results as $kr ) {
				$kr_meta = get_post_meta( $kr->ID, OKR::KEY_RESULT_META_KEY, true );
				$out .= '<div class="key-result">';
				$out .= '<h4>' . esc_html( $kr->post_title ) . '</h4>';
				$out .= '<div class="key-result-content">' . esc_html( $kr->post_content ) . '</div>';
				$out .= '<ul class="key-result-meta">';
				if ( isset( $kr_meta['due_date'] ) ) {
					$out .= '<li class="key-result-due-date"><span class="label">' . __( 'Due Date' ) . '</span>' . esc_html( $kr_meta['due_date'] ) . '</li>';
				}

				if ( isset( $kr_meta['percent_complete'] ) ) {
					$out .= '<li class="key-result-percent-complete"><span class="label">' . __( 'Percent Complete' ) . '</span>' . esc_html( $kr_meta['percent_complete'] ) . '</li>';
					$percent_complete += (int) $kr_meta['percent_complete'];
				}

				if ( isset( $kr_meta['weight'] ) ) {
					$out .= '<li class="key-result-weight"><span class="label">' . __( 'Weight' ) . '</span>' . esc_html( $kr_meta['weight'] ) . '</li>';
					$weight += (int) $kr_meta['weight'];
				} else {
					$weight += 1;
				}
				$out .= '</ul>';
				$out .= '</div>'; // .key-result
			}

			$out .= 'Objective Percent Complete: ' . round( $percent_complete / $weight ) . '%';
			$out .= '</div>'; // .objective
		}

		return $out;
	}

	private function register_objective_post_type() {

		$labels = array(
			'name'               => 'Objectives',
			'singular_name'      => 'Objective',
			'menu_name'          => 'Objectives',
			'name_admin_bar'     => 'Objectives',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Objective',
			'new_item'           => 'New Objective',
			'edit_item'          => 'Edit Objective',
			'view_item'          => 'View Objective',
			'all_items'          => 'All Objectives',
			'search_items'       => 'Search Objectives',
			'parent_item_colon'  => 'Parent Objectives',
			'not_found'          => 'No Objectives found',
			'not_found_in_trash' => 'No Objectives found in trash',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'objective' ),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'comment', 'revisions' )
		);

		register_post_type( OKR::OBJECTIVE_POST_TYPE, $args );
	}

	private function register_key_result_post_type() {

		$labels = array(
			'name'               => 'Key Results',
			'singular_name'      => 'Key Result',
			'menu_name'          => 'Key Results',
			'name_admin_bar'     => 'Key Results',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Key Result',
			'new_item'           => 'New Key Result',
			'edit_item'          => 'Edit Key Result',
			'view_item'          => 'View Key Result',
			'all_items'          => 'All Key Results',
			'search_items'       => 'Search Key Results',
			'parent_item_colon'  => 'Parent Key Results',
			'not_found'          => 'No Key Results found',
			'not_found_in_trash' => 'No Key Result found in trash',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'key-result' ),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'comment', 'revisions' ),
			//'register_meta_box_cb' => array( $this, 'add_key_result_meta_box' ),
		);

		register_post_type( OKR::KEY_RESULT_POST_TYPE, $args );
	}
}

add_action( 'init', array( 'OKR', 'init' ) );

