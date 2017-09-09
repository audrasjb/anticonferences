<?php
/**
 * Plugin's admin functions.
 *
 * @since  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

function anticonferences_admin_register_metabox( $camp = null ) {
	$pt = get_post_type( $camp );

	if ( 'camps' !== $pt ) {
		return;
	}

	remove_meta_box( 'commentstatusdiv', get_current_screen(), 'normal' );
	remove_meta_box( 'commentsdiv', get_current_screen(), 'normal' );

	$metaboxes = array(
		'ac-details-metabox' => (object) array(
			'id'    => 'ac-details-metabox',
			'title' => __( 'Details du camp', 'anticonferences' ),
			'cb'    => 'anticonferences_admin_details_metabox',
			'ctxt'  => 'normal',
			'prio'  => 'high',
		),
	);

	if ( isset( $camp->ID ) ) {
		$topics_count = wp_count_comments( $camp->ID );

		if ( ! empty( $topics_count->total_comments ) ) {
			$metaboxes['commentsdiv'] = (object) array(
				'id'    => 'commentsdiv',
				'title' => __( 'Sujets proposés', 'anticonferences' ),
				'cb'    => 'anticonferences_admin_camp_topics',
				'ctxt'  => 'aniticonferences',
				'prio'  => 'high',
			);
		}
	}

	if ( current_theme_supports( 'post-formats' ) ) {
		remove_meta_box( 'formatdiv', get_current_screen(), 'side' );

		$metaboxes['formatdiv'] = (object) array(
			'id'    => 'formatdiv',
			'title' => _x( 'Format', 'post format', 'anticonferences' ),
			'cb'    => 'anticonferences_admin_format_metabox',
			'ctxt'  => 'side',
			'prio'  => 'default',
		);
	}

	foreach ( $metaboxes as $metabox ) {
		add_meta_box( $metabox->id, $metabox->title, $metabox->cb, $pt, $metabox->ctxt, $metabox->prio );
	}
}

function anticonferences_admin_camp_topic_query( WP_Comment_Query $topic_query ) {
	$topic_query->query_vars['type'] = 'ac_topic';

	if ( wp_doing_ajax() ) {
		remove_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );
	}
}

function anticonferences_admin_ajax_set_camp_topics() {
	if ( ! wp_doing_ajax() || ! isset( $_SERVER['HTTP_REFERER'] ) ) {
		return;
	}

	$referer = parse_url( $_SERVER['HTTP_REFERER'] );

	if ( false === strpos( $referer['path'], 'wp-admin/post.php' ) || ! isset( $referer['query'] ) ) {
		return;
	}

	$qv = wp_parse_args( $referer['query'] );

	if ( ! isset( $qv['post'] ) || 'camps' !== get_post_type( $qv['post'] ) ) {
		return;
	}

	add_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );
}
add_action( 'wp_ajax_get-comments', 'anticonferences_admin_ajax_set_camp_topics', 0 );

function anticonferences_admin_camp_topics( $camp = null ) {
	add_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );

	post_comment_meta_box( $camp );

	remove_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );
}

function anticonferences_admin_details_metabox( $camp = null ) {
	$pt = get_post_type( $camp );

	if ( 'camps' !== $pt ) {
		printf( '<p class="notice error">%s</p>', esc_html__( 'Le type de contenu ne correspond pas à celui attentdu.', 'anticonferences' ) );
		return;
	}

	$metas = get_registered_meta_keys( $pt );

	if ( ! $metas ) {
		printf( '<p class="notice error">%s</p>', esc_html__( 'Les détails ne sont pas disponibles pour ce camp', 'anticonferences' ) );
		return;
	}

	$customs      = get_post_custom( $camp->ID );
	$placeholders = apply_filters( 'anticonferences_meta_placeholders', array(
		'_camp_closing_date'  => 'YYYY-MM-DD HH:II',
		'_camp_slack_webhook' => __( 'URL du webhook Slack', 'anticonferences' ),
	) );

	$output = '';
	foreach ( $metas as $key => $meta ) {
		$type = 'text';
		$ph   = $value = '';

		if ( 'integer' === $meta['type'] ) {
			$type = 'numeric';
		}

		if ( isset( $customs[ $key ] ) ) {
			$value = reset( $customs[ $key ] );

			if ( '_camp_closing_date' === $key && is_numeric( $value ) ) {
				$value = date_i18n( 'Y-m-d H:i', $value );
			}
		}

		if ( isset( $placeholders[ $key ] ) ) {
			$ph = ' placeholder="' . esc_attr( $placeholders[ $key ] ) . '"';
		}

		$output .= sprintf( '<tr>
			<td class="left">
				<label for="%1$s">%2$s</label>
			</td>
			<td>
				<input type="%3$s" name="meta_input[%1$s]" id="%1$s" class="widefat" value="%4$s"%5$s/>
			</td>
		</tr>
		', esc_attr( $key ), esc_html( $meta['description'] ), esc_attr( $type ), esc_attr( $value ), $ph );
	}

	if ( ! $output ) {
		return;
	}

	echo '<table class="fixed" width="100%">' . $output . '</table>';
}

function anticonferences_admin_format_metabox( $camp = null ) {
	$theme_pf     = get_theme_support( 'post-formats' );
	$theme_pf     = reset( $theme_pf );
	$supported_pf = anticonferences_get_support( 'post-formats' );
	$post_formats = array_intersect( $theme_pf, $supported_pf );

	if ( ! $post_formats ) {
		$post_formats = array( 0 );
		$default      = 0;
	} else {
		$default = reset( $post_formats );
	}

	$post_format = get_post_format( $camp->ID );

	if ( ! $post_format ) {
		$post_format = $default;
	}

	?>
	<div id="post-formats-select">
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Post Formats', 'anticonferences' ); ?></legend>

			<?php foreach ( $post_formats as $format ) : ?>

				<input type="radio" name="post_format" class="post-format" id="post-format-<?php echo esc_attr( $format ); ?>" value="<?php echo esc_attr( $format ); ?>" <?php checked( $post_format, $format ); ?> /> <label for="post-format-<?php echo esc_attr( $format ); ?>" class="post-format-icon post-format-<?php echo esc_attr( $format ); ?>"><?php echo esc_html( get_post_format_string( $format ) ); ?></label><br />

			<?php endforeach; ?>
		</fieldset>
	</div>
	<?php
}

function anticonferences_admin_box_area( $camp = null ) {
	if ( empty( $camp->ID ) ) {
		return;
	}

	$topics_count = wp_count_comments( $camp->ID );

	// Do not display the custom Metabox area when no topics.
	if ( empty( $topics_count->total_comments ) ) {
		return;
	}
	?>
	<br class="clear" />
	<div id="postbox-container-0" class="postbox-container">

		<?php
		/**
		 * Add a custom Metabox area so that topics are
		 * listed first.
		 */
		do_meta_boxes( null, 'aniticonferences', $camp ); ?>

	</div>
	<br class="clear" />
	<?php
}
add_action( 'edit_form_after_title', 'anticonferences_admin_box_area', 10, 1 );

function anticonferences_admin_camps_columns( $columns = array() ) {
	$new_column = array(
		'topics' => sprintf(
			'<span class="vers comment-grey-bubble" title="%1$s"><span class="screen-reader-text">%2$s</span></span>',
			esc_attr__( 'Sujets', 'anticonferences' ),
			esc_html__( 'Sujets proposés', 'anticonferences' )
		),
	);

	$flip_cols  = array_values( array_flip( $columns ) );
	$i_comments = array_search( 'comments', $flip_cols );

	$columns = array_merge(
		array_slice( $columns, 0, $i_comments, true ),
		$new_column,
		array_slice( $columns, -1, $i_comments, true )
	);

	return $columns;
}
add_filter( 'manage_camps_posts_columns', 'anticonferences_admin_camps_columns', 10, 1 );

function anticonferences_admin_camps_custom_column( $column = '', $camp_id = 0 ) {
	if ( 'topics' !== $column || ! $camp_id ) {
		return;
	}

	global $wp_list_table, $post;
	$ac = anticonferences();

	$post            = get_post( (int) $camp_id );
	$ac->camp_topics = $camp_id;
	$topics_count    = wp_count_comments( $camp_id );
	$ac->camp_topics = 0;

	$post->comment_count = (int) $topics_count->approved;

	$wp_list_table->comments_bubble( $camp_id, (int) $topics_count->moderated );
}
add_action( 'manage_camps_posts_custom_column', 'anticonferences_admin_camps_custom_column', 10, 2 );

function anticonferences_register_topic_metabox( WP_Comment $topic ) {
	if ( 'ac_topic' !== $topic->comment_type ) {
		return;
	}

	add_meta_box(
		'ac-topic-supports',
		__( 'Soutiens', 'anticonferences' ),
		'anticonferences_do_topic_metabox',
		get_current_screen(),
		'normal',
		'low'
	);
}

function anticonferences_do_topic_metabox( WP_Comment $topic ) {
	$supports = wp_filter_object_list( $topic->get_children( array( 'type' => 'ac_support' ) ), array( 'comment_approved' => 1 ) );

	if ( empty( $supports ) ) {
		esc_html_e( 'Aucun soutien pour ce sujet pour le moment.', 'anticonferences' );
	} else {
		$users_support  = array_map( 'absint', wp_list_pluck( $supports, 'comment_content', 'comment_author_email' ) );
		$users_count    = count( $users_support );
		$supports_count = array_sum( $users_support );
		$max_support    = (int) get_post_meta( $topic->comment_post_ID, '_camp_votes_amount', true );
		?>
		<p class="description">
			<?php echo esc_html( sprintf( _n(
				'%1$s utilisateur soutient ce sujet. %2$s support(s) au total.',
				'%1$s utilisateurs soutiennent ce sujet. %2$s support(s) au total.',
				$users_count,
				'anticonferences'
			), number_format_i18n( $users_count ), number_format_i18n( $supports_count ) ) ); ?>
		</p>
		<ul class="admin-topic-supports">
			<?php for ( $i = 0; $i < $max_support; $i++  ) :
				$heart = $i + 1;
			?>
			<li>
				<div class="admin-topic-supports-heart">
					<?php printf( '<span class="dashicons dashicons-heart"></span><span class="screen-reader-text">%1$s</span> %2$s',
						_n( 'Soutien apporté', 'Soutiens apportés', $heart, 'anticonferences' ),
						$heart
					); ?>
				</div>
				<div class="admin-topic-supports-users">
					<?php

					$s = wp_list_filter( $supports, array( 'comment_content' => $heart ) );
					if ( empty( $s ) ) : ?>
						&#8212;
					<?php else : foreach ( $s as $support ) : ?>
						<span class="user-supported">
							<?php echo get_avatar( $support->comment_author_email, 40 ); ?>

							<?php $remove_link = wp_nonce_url( add_query_arg( 'remove_support', $support->comment_ID, $_SERVER['REQUEST_URI'] ), 'topic_remove_support_' . $topic->comment_ID ); ?>

							<a href="<?php echo esc_url( $remove_link ); ?>" class="del-support" title="<?php esc_attr_e( 'Supprimer le soutien', 'anticonferences' );?>">
								<div class="dashicons dashicons-trash"></div>
							</a>
						</span>
					<?php endforeach; endif; ?>
				</div>
			</li>
			<?php endfor; ?>
		</ul>
		<?php
	}
}

function anticonferences_admin_topic_support_feedback() {
	$feedbacks = array(
		'suppport_removed'      => __( 'Soutien supprimé avec succès', 'anticonferences' ),
		'suppport_remove_error' => __( 'Une erreur est survenue lors de la suppression du soutien', 'anticonferences' ),
	);

	if ( isset( $_GET['message'] ) && isset( $feedbacks[ $_GET['message'] ] ) ) {
		$class = 'updated notice notice-success is-dismissible';

		if ( 'suppport_remove_error' === $_GET['message'] ) {
			$class = 'error notice is-dismissible';
		}

		printf( '<div id="message" class="%1$s"><p>%2$s</p></div>',
			$class,
			esc_html( $feedbacks[ $_GET['message'] ] )
		);
	}
}

function anticonferences_admin_load_edit_comments() {
	global $typenow;

	if ( ! empty( $_GET['post_type'] ) || empty( $_GET ) ) {
		return;
	}

	$get_keys = array_keys( $_GET );

	// Editing a single Topic
	if ( 'load-comment.php' === current_action() ) {
		if ( empty( $_GET['c'] ) ) {
			return;
		}

		$comment = get_comment( $_GET['c'] );

		if ( 'ac_topic' !== $comment->comment_type ) {
			return;
		}

		if ( ! empty( $_GET['remove_support'] ) ) {
			$support_id = (int) $_GET['remove_support'];
			$topic_id   = $comment->comment_ID;

			// nonce check
			check_admin_referer( 'topic_remove_support_' . $topic_id );
			$support = get_comment( $support_id );

			if ( false !== wp_delete_comment( $support_id ) ) {
				$message = 'suppport_removed';
			} else {
				$message = 'suppport_remove_error';
			}

			$commentlink = remove_query_arg( array( 'remove_support', '_wpnonce' ) );
			$redirect = add_query_arg( 'message', $message, $commentlink );
			wp_safe_redirect( $redirect );
			exit();
		}

		$post_type = 'camps';
		anticonferences()->admin_inline_script = array(
			'editTopic' => esc_html__( 'Modifier le sujet', 'anticonferences' ),
			'titletag'  => esc_html__( 'Modification d\'un sujet', 'anticonferences' ),
		);

		add_action( 'add_meta_boxes_comment', 'anticonferences_register_topic_metabox', 10, 1 );

		if ( isset( $_GET['message'] ) && in_array( $_GET['message'], array( 'suppport_removed', 'suppport_remove_error' ), true ) ) {
			add_action( 'admin_notices', 'anticonferences_admin_topic_support_feedback' );
		}

	// Moderating Topics
	} else {
		$keys = array( 'p', 'comment_status' );
		$match_keys = array_intersect( $get_keys, $keys );

		if ( ! $match_keys || 2 !== count( $match_keys ) ) {
			return;
		}

		$post_type = get_post_type( absint( $_GET['p'] ) );
		if ( empty( $post_type ) || 'camps' !== $post_type ) {
			return;
		}

		add_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );

		anticonferences()->admin_inline_script = array(
			'moderateTopics' => esc_html__( 'Sujets proposés pour {l}', 'anticonferences' ),
			'searchTopics'   => esc_html__( 'Rechercher un sujet', 'anticonferences' ),
			'titletag'       => esc_html__( 'Modération des sujets', 'anticonferences' ),
			'noTopics'       => esc_html__( 'Aucun sujet pour le moment.', 'anticonferences' ),
		);
	}

	$typenow = $post_type;
	get_current_screen()->post_type = $post_type;

	add_filter( 'admin_title', 'anticonferences_admin_title', 10, 1 );
}
add_action( 'load-edit-comments.php', 'anticonferences_admin_load_edit_comments', 10 );
add_action( 'load-comment.php',       'anticonferences_admin_load_edit_comments', 10 );

function anticonferences_admin_title( $admin_title = '' ) {
	$title = explode( '&lsaquo;', $admin_title );

	$ac = anticonferences();

	if ( isset( $ac->admin_inline_script['titletag'] ) && isset( $title[1] ) ) {
		$title[0]    = $ac->admin_inline_script['titletag'];
		$admin_title = join( ' &lsaquo;', $title );
	}

	return $admin_title;
}

function anticonferences_admin_head() {
	global $parent_file;

	$ac_parent = add_query_arg( 'post_type', 'camps', 'edit.php' );

	if ( 'camps' === get_current_screen()->post_type && $ac_parent !== $parent_file ) {
		$parent_file = $ac_parent;
	}
}
add_action( 'admin_head', 'anticonferences_admin_head', 10 );

function anticonferences_admin_enqueue_scripts() {
	if ( 'camps' !== get_current_screen()->post_type ) {
		return;
	}

	// Get Plugin Main instance
	$ac = anticonferences();

	wp_enqueue_style( 'ac-admin-style', anticonferences_get_asset( 'admin' ), array(), $ac->version );

	if ( isset( $ac->admin_inline_script ) ) {
		wp_add_inline_script( 'common', sprintf( '
			( function( $ ) {
				$( document ).ready( function() {
					var text = JSON.parse( \'%s\' );

					if ( $( \'.edit-comments-php h1.wp-heading-inline\' ).length ) {
						var link = $( \'.edit-comments-php h1.wp-heading-inline\' ).find( \'a\' ).get( 0 ).outerHTML;

						$( \'.edit-comments-php h1.wp-heading-inline\' ).html( text.moderateTopics.replace( \'{l}\', link ) );
						$( \'#comments-form #search-submit\' ).val( text.searchTopics );
						$( \'#the-comment-list tr.no-items td\' ).first().html( text.noTopics );

					} else if ( $( \'.comment-php .wrap h1\' ).length ) {
						$( \'.comment-php .wrap h1\' ).html( text.editTopic );
					}
				} );
			} )( jQuery );
		', json_encode( $ac->admin_inline_script ) ) );
	}
}
add_action( 'admin_enqueue_scripts', 'anticonferences_admin_enqueue_scripts' );

function anticonferences_notify_topic_author( WP_Comment $topic ) {
	if ( ! isset( $topic->comment_type ) || 'ac_topic' !== $topic->comment_type ) {
		return;
	}

	$blogname        = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$topic_content   = wp_specialchars_decode( $topic->comment_content );

	$notify_message  = __( 'La proposition de sujet suivante a été publiée :', 'anticonferences' ) . "\r\n";
	$notify_message .= esc_html( wp_trim_words( $topic_content, 30 ) ) . "\r\n\r\n";
	$notify_message .= __( 'Vous pouvez voter pour elle en vous rendant à cette adresse :', 'anticonferences' ) . "\r\n";
	$notify_message .= get_comment_link( $topic ) . "\r\n";
	$notify_message .= __( 'Partagez-la pour gagner des votes !', 'anticonferences' ) . "\r\n";

	$camp_title = get_post_field( 'post_title', $topic->comment_post_ID );
	$subject    = sprintf( __('[%s] Sujet publié', 'anticonferences' ), $camp_title );

	@wp_mail( $topic->comment_author_email, wp_specialchars_decode( $subject ), $notify_message );
}
add_action( 'comment_unapproved_to_approved', 'anticonferences_notify_topic_author', 10, 1 );

function anticonferences_admin_manage_topics_columns( $columns = array() ) {
	if ( 'camps' !== get_current_screen()->post_type ) {
		return $columns;
	}

	$new_column = array(
		'supports' => sprintf(
			'<span class="dashicons dashicons-heart" title="%1$s"></span><span class="screen-reader-text">%2$s</span>',
			esc_attr__( 'Soutiens', 'anticonferences' ),
			esc_html__( 'Soutiens apportés', 'anticonferences' )
		),
	);

	if ( isset( $columns['date'] ) ) {
		$new_column = array_merge( $new_column, array_slice( $columns, -1, 1, true ) );
		array_pop( $columns );
	}

	if ( isset( $columns['comment'] ) ) {
		$columns['comment'] = __( 'Sujet proposé', 'anticonferences' );
	}

	// Make sure the comments query is not overriden anymore.
	if ( has_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 ) ) {
		remove_action( 'parse_comment_query', 'anticonferences_admin_camp_topic_query', 15, 1 );
	}

	return array_merge( $columns, $new_column );
}
add_filter( 'manage_edit-comments_columns', 'anticonferences_admin_manage_topics_columns', 10, 1 );

function anticonferences_admin_topic_supports_column( $column = '', $comment_ID = 0 ) {
	if ( 'supports' !== $column ) {
		return;
	}

	$supports_count = get_comment_meta( $comment_ID, '_ac_support_count', true );

	if ( ! $supports_count ) {
		$supports_count = '&#8212;';
	} else {
		$supports_count = sprintf( '<span class="ac-support-count">%d</span>', $supports_count );
	}

	echo wp_kses( $supports_count, array( 'span' => array( 'class' => true ) ) );
}
add_action( 'manage_comments_custom_column', 'anticonferences_admin_topic_supports_column', 10, 2 );
