<?php
/**
 * AJAX Support Functions
 *
 * @package BadgeOS
 * @subpackage AJAX
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

// Setup our badgeos AJAX actions
$badgeos_ajax_actions = array(
	'get-achievements',
    'get-earned-achievements',
    'get-earned-ranks',
	'get-feedback',
	'get-achievements-select2',
	'get-achievement-types',
	'get-users',
	'update-feedback',
);

// Register core Ajax calls.
foreach ( $badgeos_ajax_actions as $action ) {
	add_action( 'wp_ajax_' . $action, 'badgeos_ajax_' . str_replace( '-', '_', $action ), 1 );
	add_action( 'wp_ajax_nopriv_' . $action, 'badgeos_ajax_' . str_replace( '-', '_', $action ), 1 );
}

/**
 * AJAX Helper for returning earned ranks
 *
 * @since 1.0.0
 * @return void
 */
function badgeos_ajax_get_earned_ranks() {
    global $user_ID, $blog_id, $wpdb;

    // Setup our AJAX query vars
    $type       = isset( $_REQUEST['rank_type'] )  ? $_REQUEST['rank_type']  : false;
    $limit      = isset( $_REQUEST['limit'] )      ? $_REQUEST['limit']      : false;
    $offset     = isset( $_REQUEST['offset'] )     ? $_REQUEST['offset']     : false;
    $count      = isset( $_REQUEST['count'] )      ? $_REQUEST['count']      : false;
    $search     = isset( $_REQUEST['search'] )     ? $_REQUEST['search']     : false;
    $user_id    = isset( $_REQUEST['user_id'] )    ? $_REQUEST['user_id']    : get_current_user_id();
    $orderby    = isset( $_REQUEST['orderby'] )    ? $_REQUEST['orderby']    : 'rank_id';
    $order      = isset( $_REQUEST['order'] )      ? $_REQUEST['order']      : 'ASC';
    $show_title = isset( $_REQUEST['show_title'] ) ? $_REQUEST['show_title'] : 'true';
    $show_thumb = isset( $_REQUEST['show_thumb'] ) ? $_REQUEST['show_thumb'] : 'true';
    $show_description = isset( $_REQUEST['show_description'] ) ? $_REQUEST['show_description'] : 'true';

    // Convert $type to properly support multiple rank types
    $badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
    if ( 'all' == $type ) {
        $type = badgeos_get_rank_types_slugs();
        // Drop steps from our list of "all" achievements
        $step_key = array_search( trim( $badgeos_settings['ranks_step_post_type'] ), $type );
        if ( $step_key )
            unset( $type[$step_key] );
    } else {
        $type = explode( ',', $type );
    }

    // Get the current user if one wasn't specified
    if( ! $user_id )
        $user_id = get_current_user_id();

    // Initialize our output and counters
    if( $offset > 0 ) {
        $ranks = '';
    } else {
        $ranks = '<div class="badgeos-arrange-buttons"><button class="list buttons selected"><i class="fa fa-bars"></i> ' . __( 'List', 'badgeos' ) . '</button><button class="grid buttons"><i class="fa fa-th-large"></i> ' . __( 'Grid', 'badgeos' ) . '</button></div><ul class="ls_grid_container list">';
    }

    $ranks_count = 0;

    // If we're polling all sites, grab an array of site IDs
    if( $wpms && $wpms != 'false' )
        $sites = badgeos_get_network_site_ids();
    else
        $sites = array( $blog_id );

    // Loop through each site (default is current site only)
    $query_count = 0;
    foreach( $sites as $site_blog_id ) {

        $qry = "SELECT * FROM ".$wpdb->prefix."badgeos_ranks WHERE user_id='".$user_id."'";
        $total_qry = "SELECT count(ID) as total FROM ".$wpdb->prefix."badgeos_ranks WHERE user_id='".$user_id."'";

        if( is_array( $type ) && count( $type ) > 0 ) {
            $qry .= " and rank_type in ('".implode( "', '", $type )."') ";
            $total_qry .= " and rank_type in ('".implode( "', '", $type )."') ";
        }

        if ( $search ) {
            $qry .= " and rank_title like '%".$search."%' ";
            $total_qry .= " and rank_title like '%".$search."%' ";
        }

        $query_count += intval( $wpdb->get_var( $total_qry ) );
        if( !empty( $orderby ) ) {
            if( !empty( $order ) ) {
                $qry .= " ORDER BY ".$orderby." ".$order;
            } else {
                $qry .= " ORDER BY ".$orderby." ASC";
            }
        }

        $qry .= " limit ".$offset.", ".$limit;
        $user_ranks = $wpdb->get_results( $qry );

        // Loop ranks
        foreach ( $user_ranks as $rank ) {

            $output = '<li><div id="badgeos-achievements-list-item-' . $rank->rank_id . '" class="badgeos-achievements-list-item">';

            // Achievement Image
            if( $show_thumb == 'true' ) {
                $output .= '<div class="badgeos-item-image">';
                $output .= '<a href="' . get_permalink( $rank->rank_id ) . '">' . badgeos_get_rank_image( $rank->rank_id ) . '</a>';
                $output .= '</div><!-- .badgeos-item-image -->';
            }

            // Achievement Content
            $output .= '<div class="badgeos-item-detail">';

            if( $show_title == 'true' ) {
	       $title = get_the_title($rank->ID);                
	       $title = $rank->rank_title;
                // Achievement Title
                $output .= '<h2 class="badgeos-item-title"><a href="'.get_permalink( $rank->rank_id ).'">'.$title.'</a></h2>';
            }

            // Achievement Short Description
            if( $show_description == 'true' ) {
                $post = get_post( $rank->rank_id );
                if( $post ) {
                    $output .= '<div class="badgeos-item-excerpt">';
                    $excerpt = !empty( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
                    $output .= wpautop( apply_filters( 'get_the_excerpt', $excerpt ) );
                    $output .= '</div><!-- .badgeos-item-excerpt -->';
                }
            }

            $output .= '</div><!-- .badgeos-item-description -->';
            $output .= apply_filters( 'badgeos_after_earned_ranks', '', $rank );
            $output .= '</div></li><!-- .badgeos-achievements-list-item -->';

            $ranks .= $output;
            $ranks_count++;
        }

        $ranks .= '</ul>';

        // Display a message for no results
        if ( empty( $ranks ) ) {
            $current = current( $type );
            // If we have exactly one achivement type, get its plural name, otherwise use "ranks"
            $post_type_plural = ( 1 == count( $type ) && ! empty( $current ) ) ? get_post_type_object( $current )->labels->name : __( 'achievements' , 'badgeos' );

            // Setup our completion message
            $ranks .= '<div class="badgeos-no-results">';
            if ( 'completed' == $filter ) {
                $ranks .= '<p>' . sprintf( __( 'No completed %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
            }else{
                $ranks .= '<p>' . sprintf( __( 'No %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
            }
            $ranks .= '</div><!-- .badgeos-no-results -->';
        }

        if ( $blog_id != $site_blog_id ) {
            // Come back to current blog
            restore_current_blog();
        }

    }

    // Send back our successful response
    wp_send_json_success( array(
        'message'     => $ranks,
        'offset'      => $offset + $limit,
        'query_count' => $query_count,
        'badge_count' => $ranks_count,
        'type'        => $earned_ids,
        'attr'        => $_REQUEST
    ) );

}

/**
 * AJAX Helper for returning earned achievements
 *
 * @since 1.0.0
 * @return void
 */
function badgeos_ajax_get_earned_achievements() {
    global $user_ID, $blog_id, $wpdb;

    // Setup our AJAX query vars
    $type       = isset( $_REQUEST['type'] )       ? $_REQUEST['type']       : false;
    $limit      = isset( $_REQUEST['limit'] )      ? $_REQUEST['limit']      : false;
    $offset     = isset( $_REQUEST['offset'] )     ? $_REQUEST['offset']     : false;
    $count      = isset( $_REQUEST['count'] )      ? $_REQUEST['count']      : false;
    $search     = isset( $_REQUEST['search'] )     ? $_REQUEST['search']     : false;
    $user_id    = isset( $_REQUEST['user_id'] )    ? $_REQUEST['user_id']    : get_current_user_id();
    $orderby    = isset( $_REQUEST['orderby'] )    ? $_REQUEST['orderby']    : 'ID';
    $order      = isset( $_REQUEST['order'] )      ? $_REQUEST['order']      : 'ASC';
    $wpms       = isset( $_REQUEST['wpms'] )       ? $_REQUEST['wpms']       : false;
    $include    = isset( $_REQUEST['include'] )    ? $_REQUEST['include']    : array();
    $exclude    = isset( $_REQUEST['exclude'] )    ? $_REQUEST['exclude']    : array();

    $show_title = isset( $_REQUEST['show_title'] ) ? $_REQUEST['show_title'] : 'true';
    $show_thumb = isset( $_REQUEST['show_thumb'] ) ? $_REQUEST['show_thumb'] : 'true';
    $show_description = isset( $_REQUEST['show_description'] ) ? $_REQUEST['show_description'] : 'true';

    // Convert $type to properly support multiple achievement types
    $badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
    if ( 'all' == $type ) {
        $type = badgeos_get_achievement_types_slugs();
        // Drop steps from our list of "all" achievements
        $step_key = array_search( trim( $badgeos_settings['achievement_step_post_type'] ), $type );
        if ( $step_key )
            unset( $type[$step_key] );
    } else {
        $type = explode( ',', $type );
    }

    // Get the current user if one wasn't specified
    if( ! $user_id )
        $user_id = $user_ID;

    // Initialize our output and counters
    if( $offset > 0 ) {
        $achievements = '';
    } else {
        $achievements = '<div class="badgeos-arrange-buttons"><button class="list buttons selected"><i class="fa fa-bars"></i> ' . __( 'List', 'badgeos' ) . '</button><button class="grid buttons"><i class="fa fa-th-large"></i> ' . __( 'Grid', 'badgeos' ) . '</button></div><ul class="ls_grid_container list">';
    }

    $achievement_count = 0;

    // If we're polling all sites, grab an array of site IDs
    if( $wpms && $wpms != 'false' )
        $sites = badgeos_get_network_site_ids();
    else
        $sites = array( $blog_id );

    // Loop through each site (default is current site only)
    $query_count = 0;
    foreach( $sites as $site_blog_id ) {

        $qry = "SELECT * FROM ".$wpdb->prefix."badgeos_achievements WHERE site_id='".$site_blog_id."'";
        $total_qry = "SELECT count(ID) as total FROM ".$wpdb->prefix."badgeos_achievements WHERE site_id='".$site_blog_id."'";

        if( is_array( $type ) && count( $type ) > 0 ) {
            $qry .= " and post_type in ('".implode( "', '", $type )."') ";
            $total_qry .= " and post_type in ('".implode( "', '", $type )."') ";
        }

        $qry .= " and user_id = '".get_current_user_id()."' ";
        $total_qry .= " and user_id = '".get_current_user_id()."' ";

        if ( $search ) {
            $qry .= " and achievement_title like '%".$search."%' ";
            $total_qry .= " and achievement_title like '%".$search."%' ";
        }

        // Build $include array
        if ( !empty( $include ) ) {
            $qry .= " and ID in (".$include.") ";
            $total_qry .= " and ID in (".$include.") ";
        }

        // Build $exclude array
        if ( !empty( $exclude ) ) {
            $qry .= " and ID not in (".$exclude.") ";
            $total_qry .= " and ID not in (".$exclude.") ";
        }
        $query_count += intval( $wpdb->get_var( $total_qry ) );
        if( !empty( $orderby ) ) {
            if( !empty( $order ) ) {
                $qry .= " ORDER BY ".$orderby." ".$order;
            } else {
                $qry .= " ORDER BY ".$orderby." ASC";
            }
        }

        $qry .= " limit ".$offset.", ".$limit;
        $user_achievements = $wpdb->get_results( $qry );

        // Loop Achievements
        //$query_count += count( $user_achievements );
        foreach ( $user_achievements as $achievement ) {
            $output = '<li><div id="badgeos-achievements-list-item-' . $achievement->ID . '" class="badgeos-achievements-list-item">';

            // Achievement Image
            if( $show_thumb=='true' ) {
                $output .= '<div class="badgeos-item-image">';
                $output .= '<a href="' . get_permalink( $achievement->ID ) . '">' . badgeos_get_achievement_post_thumbnail( $achievement->ID, 'boswp-badgeos-achievement' ) . '</a>';
                $output .= '</div><!-- .badgeos-item-image -->';
            }

            // Achievement Content
            $output .= '<div class="badgeos-item-detail">';

            // Achievement Title
            if( $show_title == 'true' ) {
                $title = get_the_title( $achievement->ID );
                if( empty( $title ) )
                    $title = $achievement->achievement_title;

                $output .= '<h2 class="badgeos-item-title"><a href="'.get_permalink( $achievement->ID ).'">'.$title.'</a></h2>';
            }

            // Achievement Short Description
            if( $show_description=='true' ) {
                $post = get_post( $achievement->ID );
                if( $post ) {
                    $output .= '<div class="badgeos-item-excerpt">';
                    $excerpt = !empty( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
                    $output .= wpautop( apply_filters( 'get_the_excerpt', $excerpt ) );
                    $output .= '</div>';
                }
            }

            $output .= '</div>';
            $output .= apply_filters( 'badgeos_after_earned_achievement', '', $achievement );
            $output .= '</div></li>';

            $achievements .= $output;
            $achievement_count++;
        }

        if( $offset == 0 ) {
            $achievements .= '</ul>';
        }

        // Display a message for no results
        if ( empty( $achievements ) ) {
            $current = current( $type );
            // If we have exactly one achivement type, get its plural name, otherwise use "achievements"
            $post_type_plural = ( 1 == count( $type ) && ! empty( $current ) ) ? get_post_type_object( $current )->labels->name : __( 'achievements' , 'badgeos' );

            // Setup our completion message
            $achievements .= '<div class="badgeos-no-results">';
            if ( 'completed' == $filter ) {
                $achievements .= '<p>' . sprintf( __( 'No completed %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
            }else{
                $achievements .= '<p>' . sprintf( __( 'No %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
            }
            $achievements .= '</div><!-- .badgeos-no-results -->';
        }

        if ( $blog_id != $site_blog_id ) {
            // Come back to current blog
            restore_current_blog();
        }

    }

    // Send back our successful response
    wp_send_json_success( array(
        'message'     => $achievements,
        'offset'      => $offset + $limit,
        'query_count' => $query_count,
        'badge_count' => $achievement_count,
        'type'        => $earned_ids,
        'attr'        => $_REQUEST
	
    ) );
}

/**
 * AJAX Helper for returning achievements
 *
 * @since 1.0.0
 * @return void
 */
function badgeos_ajax_get_achievements() {
	global $user_ID, $blog_id;

	// Setup our AJAX query vars
	$type       = isset( $_REQUEST['type'] )       ? $_REQUEST['type']       : false;
	$limit      = isset( $_REQUEST['limit'] )      ? $_REQUEST['limit']      : false;
	$offset     = isset( $_REQUEST['offset'] )     ? $_REQUEST['offset']     : false;
	$count      = isset( $_REQUEST['count'] )      ? $_REQUEST['count']      : false;
	$filter     = isset( $_REQUEST['filter'] )     ? $_REQUEST['filter']     : false;
	$search     = isset( $_REQUEST['search'] )     ? $_REQUEST['search']     : false;
	$user_id    = isset( $_REQUEST['user_id'] )    ? $_REQUEST['user_id']    : false;
	$orderby    = isset( $_REQUEST['orderby'] )    ? $_REQUEST['orderby']    : false;
	$order      = isset( $_REQUEST['order'] )      ? $_REQUEST['order']      : false;
	$wpms       = isset( $_REQUEST['wpms'] )       ? $_REQUEST['wpms']       : false;
	$include    = isset( $_REQUEST['include'] )    ? $_REQUEST['include']    : array();
	$exclude    = isset( $_REQUEST['exclude'] )    ? $_REQUEST['exclude']    : array();
	$meta_key   = isset( $_REQUEST['meta_key'] )   ? $_REQUEST['meta_key']   : '';
    $meta_value = isset( $_REQUEST['meta_value'] ) ? $_REQUEST['meta_value'] : '';
    $show_title = isset( $_REQUEST['show_title'] ) ? $_REQUEST['show_title'] : 'true';
    $show_thumb = isset( $_REQUEST['show_thumb'] ) ? $_REQUEST['show_thumb'] : 'true';
    $show_description = isset( $_REQUEST['show_description'] ) ? $_REQUEST['show_description'] : 'true';
    $show_steps = isset( $_REQUEST['show_steps'] ) ? $_REQUEST['show_steps'] : 'true';

    // Convert $type to properly support multiple achievement types
    $badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
    if ( 'all' == $type ) {
		$type = badgeos_get_achievement_types_slugs();
		// Drop steps from our list of "all" achievements
		$step_key = array_search( trim( $badgeos_settings['achievement_step_post_type'] ), $type );
		if ( $step_key )
			unset( $type[$step_key] );
	} else {
		$type = explode( ',', $type );
	}

	// Get the current user if one wasn't specified
	if( ! $user_id )
		$user_id = $user_ID;

	// Build $include array
    if ( !is_array( $include ) && !empty( $include ) ) {
		$include = explode( ',', $include );
	}

	// Build $exclude array
	if ( !is_array( $exclude ) && !empty($exclude) ) {
		$exclude = explode( ',', $exclude );
	}

    // Initialize our output and counters
    if( $offset > 0 ) {
        $achievements = '';
    } else {
        $achievements = '<div class="badgeos-arrange-buttons"><button class="list buttons selected"><i class="fa fa-bars"></i> '. __( 'List', 'badgeos' ) .'</button><button class="grid buttons"><i class="fa fa-th-large"></i> '. __( 'Grid', 'badgeos' ) .'</button></div><ul class="ls_grid_container list">';
    }

    $achievement_count = 0;
    $query_count = 0;

    // Grab our hidden badges (used to filter the query)
	$hidden = badgeos_get_hidden_achievement_ids( $type );

	// If we're polling all sites, grab an array of site IDs
	if( $wpms && $wpms != 'false' )
		$sites = badgeos_get_network_site_ids();
	// Otherwise, use only the current site
	else
		$sites = array( $blog_id );

	// Loop through each site (default is current site only)
	foreach( $sites as $site_blog_id ) {

		// If we're not polling the current site, switch to the site we're polling
		if ( $blog_id != $site_blog_id ) {
			switch_to_blog( $site_blog_id );
		}

		// Grab our earned badges (used to filter the query)
		$earned_ids = badgeos_get_user_earned_achievement_ids( $user_id, $type );

		// Query Achievements
		$args = array(
			'post_type'      =>	$type,
			'orderby'        =>	$orderby,
			'order'          =>	$order,
			'posts_per_page' =>	$limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'post__not_in'   => $hidden
		);

        if( ! is_array( $args[ 'post__not_in' ] ) ) {
            $args[ 'post__not_in' ] = [];
        }

        // Filter - query completed or non completed achievements
		if ( $filter == 'completed' ) {
			$args[ 'post__in' ] = array_merge( array( 0 ), $earned_ids );
		}elseif( $filter == 'not-completed' ) {
			$args[ 'post__not_in' ] = array_merge( $hidden, $earned_ids );
		}

		if ( '' !== $meta_key && '' !== $meta_value ) {
			$args[ 'meta_key' ] = $meta_key;
			$args[ 'meta_value' ] = $meta_value;
		}

        // Include certain achievements
        if ( ! empty( $include ) ) {
            $args[ 'post__not_in' ] = array_diff( $args[ 'post__not_in' ], $include );
            if( ! is_array( $args[ 'post__in' ] ) ) {
                $args[ 'post__in' ] = [];
            }

            $args[ 'post__in' ] = array_merge( array( 0 ), array_diff( $include, $args[ 'post__in' ] ) );
        }

		// Exclude certain achievements
		if ( !empty( $exclude ) ) {
			$args[ 'post__not_in' ] = array_merge( $args[ 'post__not_in' ], $exclude );
		}

		// Search
		if ( $search ) {
			$args[ 's' ] = $search;
		}

		// Loop Achievements
		$achievement_posts = new WP_Query( $args );
		$query_count += $achievement_posts->found_posts;
		while ( $achievement_posts->have_posts() ) : $achievement_posts->the_post();
            $achievements .= '<li>'.badgeos_render_achievement( get_the_ID(), $show_title, $show_thumb, $show_description, $show_steps ).'</li>';
			$achievement_count++;
		endwhile;

		// Sanity helper: if we're filtering for complete and we have no
		// earned achievements, $achievement_posts should definitely be false
		/*if ( 'completed' == $filter && empty( $earned_ids ) )
			$achievements = '';*/

		// Display a message for no results
		if ( empty( $achievements ) ) {
			$current = current( $type );
			// If we have exactly one achivement type, get its plural name, otherwise use "achievements"
			$post_type_plural = ( 1 == count( $type ) && ! empty( $current ) ) ? get_post_type_object( $current )->labels->name : __( 'achievements' , 'badgeos' );

			// Setup our completion message
			$achievements .= '<div class="badgeos-no-results">';
			if ( 'completed' == $filter ) {
				$achievements .= '<p>' . sprintf( __( 'No completed %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
			}else{
				$achievements .= '<p>' . sprintf( __( 'No %s to display at this time.', 'badgeos' ), strtolower( $post_type_plural ) ) . '</p>';
			}
			$achievements .= '</div><!-- .badgeos-no-results -->';
		}

		if ( $blog_id != $site_blog_id ) {
			// Come back to current blog
			restore_current_blog();
		}
	}

    if( $offset == 0 ) {
        $achievements .= '</ul>';
    }

    // Send back our successful response
	wp_send_json_success( array(
		'message'     => $achievements,
		'offset'      => $offset + $limit,
		'query_count' => $query_count,
		'badge_count' => $achievement_count,
		'type'        => $earned_ids,
        'attr'        => $_REQUEST
	) );
}

/**
 * AJAX Helper for returning feedback posts
 *
 * @since 1.1.0
 * @return void
 */
function badgeos_ajax_get_feedback() {

	$feedback = badgeos_get_feedback( array(
		'post_type'        => isset( $_REQUEST['type'] ) ? esc_html( $_REQUEST['type'] ) : '',
		'posts_per_page'   => isset( $_REQUEST['limit'] ) ? esc_html( $_REQUEST['limit'] ) : '',
		'status'           => isset( $_REQUEST['status'] ) ? esc_html( $_REQUEST['status'] ) : '',
		'show_attachments' => isset( $_REQUEST['show_attachments'] ) ? esc_html( $_REQUEST['show_attachments'] ) : '',
		'show_comments'    => isset( $_REQUEST['show_comments'] ) ? esc_html( $_REQUEST['show_comments'] ) : '',
		's'                => isset( $_REQUEST['search'] ) ? esc_html( $_REQUEST['search'] ) : '',
	) );

	wp_send_json_success( array(
		'feedback' => $feedback
	) );
}

/**
 * AJAX Helper for approving/denying feedback
 *
 * @since 1.1.0
 * @return void
 */
function badgeos_ajax_update_feedback() {

	// Verify our nonce
	check_ajax_referer( 'review_feedback', 'nonce' );

	// Status workflow
	$status_args = array(
		'achievement_id' => $_REQUEST[ 'achievement_id' ],
        'user_id' => $_REQUEST[ 'user_id' ],
		'submission_type' => $_REQUEST[ 'feedback_type' ]
	);

	// Setup status
	$status = ( in_array( $_REQUEST[ 'status' ], array( 'approve', 'approved' ) ) ) ? 'approved' : 'denied';

	badgeos_set_submission_status( $_REQUEST[ 'feedback_id' ], $status, $status_args );

	// Send back our successful response
	wp_send_json_success( array(
		'message' => '<p class="badgeos-feedback-response success">' . __( 'Status Updated!', 'badgeos' ) . '</p>',
		'status' => ucfirst( $status )
	) );

}

/**
 * AJAX Helper for selecting users in Shortcode Embedder
 *
 * @since 1.4.0
 */
function badgeos_ajax_get_users() {

	// If no query was sent, die here
	if ( ! isset( $_REQUEST['q'] ) ) {
		die();
	}

	global $wpdb;

	// Pull back the search string
	$search = esc_sql( like_escape( $_REQUEST['q'] ) );

    $sql = "SELECT ID as id, user_login as label, ID as value FROM {$wpdb->users}";

	// Build our query
	if ( !empty( $search ) ) {
        $sql .= " WHERE user_login LIKE '%".$_REQUEST['q']."%'";
	}

	// Fetch our results (store as associative array)
    $results = $wpdb->get_results( $sql." limit 100 ", 'ARRAY_A' );

	// Return our results
    wp_send_json( $results );
}

/**
 * AJAX Helper for selecting posts in Shortcode Embedder
 *
 * @since 1.4.0
 */
function badgeos_ajax_get_achievements_select2() {
	global $wpdb;

    $badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
	// Pull back the search string
	$search = isset( $_REQUEST['q'] ) ? like_escape( $_REQUEST['q'] ) : '';
	$achievement_types = isset( $_REQUEST['post_type'] ) && 'all' !== $_REQUEST['post_type']
		? array( esc_sql( $_REQUEST['post_type'] ) )
		: array_diff( badgeos_get_achievement_types_slugs(), array( trim( $badgeos_settings['achievement_step_post_type'] ) ) );
	$post_type = sprintf( 'AND p.post_type IN(\'%s\')', implode( "','", $achievement_types ) );

	$results = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT p.ID, p.post_title
		FROM   $wpdb->posts AS p 
		JOIN $wpdb->postmeta AS pm
		ON p.ID = pm.post_id
		WHERE  p.post_title LIKE %s
		       {$post_type}
		       AND p.post_status = 'publish'
		       AND pm.meta_key LIKE %s
		       AND pm.meta_value LIKE %s
		",
		"%%{$search}%%",
		"%%_badgeos_hidden%%",
		"%%show%%"
	) );

	// Return our results
	wp_send_json_success( $results );
}

/**
 * AJAX Helper for selecting achievement types in Shortcode Embedder
 *
 * @since 1.4.0
 */
function badgeos_ajax_get_achievement_types() {

	// If no query was sent, die here
	if ( ! isset( $_REQUEST['q'] ) ) {
		die();
	}

    $badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
    $achievement_types = array_diff( badgeos_get_achievement_types_slugs(), array( trim( $badgeos_settings['achievement_step_post_type'] ) ) ); 
    $matches = preg_grep( "/{$_REQUEST['q']}/", $achievement_types );
	$found = array_map( 'get_post_type_object', $matches );

	// Include an "all" option as the first option
	array_unshift( $found, (object) array( 'name' => 'all', 'label' => __( 'All', 'badgeos') ) );

	// Return our results
	wp_send_json_success( $found );
}

function delete_badgeos_log_entries() {

    $action = ( isset( $_POST['action'] ) ? $_POST['action'] : '' );
    if( $action !== 'delete_badgeos_log_entries' ) {
        exit;
    }
    if ( ! wp_next_scheduled ( 'cron_delete_log_entries' ) ) {
        wp_schedule_single_event( time(), 'cron_delete_log_entries' );
    }
}
add_action( 'wp_ajax_delete_badgeos_log_entries', 'delete_badgeos_log_entries' );

function cron_delete_log_entries() {
    @set_time_limit( 3600 );

    global $wpdb;

    $badgeos_log_entry = $wpdb->get_results( "SELECT `ID` FROM $wpdb->posts WHERE post_type = 'badgeos-log-entry';" );
    if( is_array( $badgeos_log_entry ) && !empty( $badgeos_log_entry ) && !is_null( $badgeos_log_entry ) ) {
        foreach( $badgeos_log_entry as $log_entry ) {
            $wpdb->query( "DELETE FROM $wpdb->posts WHERE ID = '$log_entry->ID';" );
            $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE post_id = '$log_entry->ID';" );
        }
    }
}
add_action( 'cron_delete_log_entries', 'cron_delete_log_entries' );