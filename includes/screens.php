<?php
/**
 * BP Idea Stream integration : screens.
 *
 * BuddyPress / Screens : only user's profile, IdeaStream will
 * use its own logic for the root 'component'.
 *
 * @package BP Idea Stream
 *
 * @since  1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Idea_Stream_Screens' ) ) :
/**
 * Main Screen Class.
 *
 * @since  1.0.0
 */
class BP_Idea_Stream_Screens {

	/**
	 * The constructor
	 *
	 * @since  1.0.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Starts the screens class
	 *
	 * @since  1.0.0
	 */
	public static function manage_screens() {
		$ideastream = buddypress()->ideastream;

		if ( empty( $ideastream->screens ) ) {
			$ideastream->screens = new self;
		}

		return $ideastream->screens;
	}

	/**
	 * Set some globals
	 *
	 * @since  1.0.0
	 */
	public function setup_globals() {
		$this->screen   = '';
		$this->user_id  = bp_displayed_user_id();
		$this->username = bp_get_displayed_user_username();
	}

	/**
	 * Set some actions
	 *
	 * @since  1.0.0
	 */
	public function setup_actions() {
		add_action( 'bp_idea_stream_user_ideas',    array( $this, 'do_template' ) );
		add_action( 'bp_idea_stream_user_comments', array( $this, 'do_template' ) );
		add_action( 'bp_idea_stream_user_rates',    array( $this, 'do_template' ) );
	}

	/**
	 * Cusomize title, content and pagination
	 *
	 * @since  1.0.0
	 */
	public function do_template() {
		if ( empty( $this->screen ) ) {
			return;
		}

		add_action( 'bp_template_title',   array( $this, 'set_title' ) );
		add_action( 'bp_template_content', array( $this, 'set_content' ) );

		// Set pagination for idea loops
		$paginate_filter = 'wp_idea_stream_ideas_pagination_args' ;

		// Or Set it for user comments
		if ( 'comments' == $this->screen ) {
			$paginate_filter = 'wp_idea_stream_comments_pagination_args';
		}

		add_filter( $paginate_filter, array( $this, 'set_pagination_base' ), 10, 1 );
	}

	/**
	 * Load the base template for user screens
	 *
	 * @since  1.0.0
	 *
	 * @param  string $screen   the screen to display
	 * @param  string $template the template to use
	 */
	public static function load_template( $screen = '', $template = 'members/single/plugins' ) {
		// Bail if screen is not defined
		if ( empty( $screen ) ) {
			return false;
		}

		/**
		 * Hook here to perform custom action before the IdeaStream single members parts
		 * are loaded
		 *
		 * @since  2.3.0
		 * @param  string $screen
		 */
		do_action( 'bp_idea_stream_load_member_template', $screen );

		/**
		 * Filter here to use your own template.
		 *
		 * @param  string $template the template to use
		 */
		bp_core_load_template( apply_filters( "bp_idea_stream_{$screen}_template", $template ) );
	}

	/**
	 * Published ideas
	 *
	 * @since  1.0.0
	 */
	public static function user_ideas() {

		// Set current screen
		buddypress()->ideastream->screens->screen = 'ideas';

		// Set is_ideastream
		bp_idea_stream_set_is_ideastream();

		// We're on user's ideas
		do_action( 'bp_idea_stream_user_ideas' );

		// Load the default BuddyPress template
		self::load_template( 'ideas' );
	}

	/**
	 * Commented ideas
	 *
	 * @since  1.0.0
	 */
	public static function user_comments() {

		// Set current screen
		buddypress()->ideastream->screens->screen = 'comments';

		// Set is_ideastream
		bp_idea_stream_set_is_ideastream();

		// We're on user's comments
		do_action( 'bp_idea_stream_user_comments' );

		// Load the default BuddyPress template
		self::load_template( 'comments' );
	}

	/**
	 * Rated ideas
	 *
	 * @since  1.0.0
	 */
	public static function user_rates() {

		// Set current screen
		buddypress()->ideastream->screens->screen = 'rates';

		// Set is_ideastream
		bp_idea_stream_set_is_ideastream();

		// We're on user's rates
		do_action( 'bp_idea_stream_user_rates' );

		// Load the default BuddyPress template
		self::load_template( 'rates' );
	}

	/**
	 * Set the title part of the template
	 *
	 * @since  1.0.0
	 */
	public function set_title() {
		// No title needed.
		return;
	}

	/**
	 * Set the content part of the template
	 *
	 * @since  1.0.0
	 */
	public function set_content() {
		// Init vars
		$this->user_args = array();
		$template_slug   = 'idea';
		$template_name   = 'loop';
		$filters         = array( 'is_user_profile', 'ideas_query_args' );

		/**
		 * Make sure the pagination is set
		 *
		 * For idea queries the pagination slug is the same than WordPress
		 * the paged query var is set to it's not necessary.
		 * For comments queries, as the pagination slug is different then the one
		 * of WordPress, it's necessary.
		 */
		if ( in_array( bp_action_variable( 0 ), array( wp_idea_stream_cpage_slug(), wp_idea_stream_paged_slug() ) ) && is_numeric( bp_action_variable( 1 ) ) ) {
			$this->user_args['page'] = (int) bp_action_variable( 1 );
		}

		/**
		 * About rates, we don't need the ideas the user submitted
		 * but the ideas he rated, so the author must not be set.
		 */
		if ( 'rates' == $this->screen ) {
			// Building the meta query, no need to edit query orderby
			// for user rates.
			$this->user_args['meta_query'] = array(
				array(
					'key'     => '_ideastream_rates',
					'value'   => ';i:' . $this->user_id . ';',
					'compare' => 'LIKE'
				)
			);

			$filters = array_merge( $filters, array( 'is_user_profile_rates', 'users_displayed_user_id' ) );

		/**
		 * About comments, we are using specific loop, template
		 * and filter.
		 */
		} else if( 'comments' == $this->screen ) {
			$template_slug              = 'user';
			$template_name              = 'comments';
			$this->user_args['user_id'] = $this->user_id;
			$filters                    = array( 'comments_query_args' );

		// Default is user ideas, we need to set the author.
		} else {
			$this->user_args['author'] = $this->user_id;

			// Show private ideas only if on current user is on his profile
			if ( bp_is_my_profile() ) {
				$filters = array_merge( $filters, array( 'ideas_get_status' ) );
			}
		}

		// Add temporary filters
		foreach ( $filters as $filter ) {
			$this_filter = str_replace(
				array( 'ideas','comments', '_rates', 'users_' ),
				array( 'filter', 'filter', '', '' ),
				$filter
			);
			add_filter( 'wp_idea_stream_' . $filter, array( $this, $this_filter ), 10, 1 );
		}
		?>

		<div id="wp-idea-stream">

			<?php wp_idea_stream_template_part( $template_slug, $template_name ); ?>

		</div>

		<?php
		// Remove temporary filters
		foreach ( $filters as $filter ) {
			$this_filter = str_replace(
				array( 'ideas','comments', '_rates', 'users_' ),
				array( 'filter', 'filter', '', '' ),
				$filter
			);
			remove_filter( 'wp_idea_stream_' . $filter, array( $this, $this_filter ), 10, 1 );
		}
	}

	/**
	 * Apply the specific arguments to the IdeaStream Loop
	 *
	 * @since  1.0.0
	 *
	 * @param  array $args the loop arguments
	 * @return array specific arguments
	 */
	public function filter_query_args( $args = array() ) {
		return $this->user_args;
	}

	/**
	 * Map wp_idea_stream_is_user_profile to bp_is_user
	 *
	 * @since  1.0.0
	 *
	 * @param  bool $retval true if on a user's profile, false otherwise
	 * @return bool true if on a user's profile, false otherwise
	 */
	public function is_user_profile( $retval = false ) {
		return (bool) bp_is_user();
	}

	/**
	 * Sets wp_idea_stream_displayed_user_id to displayed user id
	 *
	 * @since .0.0
	 *
	 * @param  int $retval the displayed user id
	 * @return int the displayed user id
	 */
	public function displayed_user_id( $retval = 0 ) {
		return (int) $this->user_id;
	}

	/**
	 * Makes sure private ideas will be seen if current user is viewing his profile
	 *
	 * @since  1.0.0
	 *
	 * @param  array $status ideas default status
	 * @return array the status for a user viewing his own profile
	 */
	public function filter_get_status( $status = array( 'publish' ) ) {
		return array_merge( $status, array( 'private' ) );
	}

	/**
	 * Sets pagination base
	 *
	 * @since  1.0.0
	 *
	 * @param  array $pagination_args
	 * @return array the new pagination args if needed
	 */
	public function set_pagination_base( $pagination_args = '' ) {
		// Initialize base
		$base = '';

		if ( 'rates' ==  $this->screen ) {
			$base = bp_idea_stream_get_user_rates_url( $this->user_id, $this->username );

		} else if ( 'comments' == $this->screen ) {
			$base = bp_idea_stream_get_user_comments_url( $this->user_id, $this->username );

		} else {
			$base = bp_idea_stream_get_user_profile_url( $this->user_id, $this->username );

			// BuddyPress needs an extra action var to make sure pagination works in root profile page
			$base = trailingslashit( $base . buddypress()->ideastream->idea_nav['ideas']['slug'] );
		}

		$pagination_args['base'] = $base . '%_%';

		return $pagination_args;
	}
}

endif;

// Init Screens class
add_action( 'bp_init', array( 'BP_Idea_Stream_Screens', 'manage_screens' ) );
