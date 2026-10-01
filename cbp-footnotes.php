<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: CB Footnotes
 * Plugin URI:  https://github.com/ChillibyteUK/cbp-footnotes
 * Description: Adds [Footnote]...[/Footnote] tag support to content — converts tagged text into numbered, linked footnotes with a single running counter per page.
 * Version:     1.0.1
 * Author:      Chillibyte - DS
 * License:     GPL v2 or later
 *
 * @package CB_Footnotes
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CB_FOOTNOTES_VERSION' ) ) {
	define( 'CB_FOOTNOTES_VERSION', '1.0.1' );
}
if ( ! defined( 'CB_FOOTNOTES_PLUGIN_URL' ) ) {
	define( 'CB_FOOTNOTES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'CBFootnotes' ) ) {

	/**
	 * Class CBFootnotes
	 *
	 * Extracts [Footnote]...[/Footnote] tags from content, replacing them with
	 * numbered bracketed links, and renders the collected footnote list.
	 * Unlike the theme implementation this was ported from, this uses a single
	 * running counter for the whole page request rather than per-section
	 * counters — simpler to reason about when a site only needs one list.
	 *
	 * @package CB_Footnotes
	 */
	class CBFootnotes {

		/**
		 * Footnotes collected so far during the current page request.
		 *
		 * @var stdClass[]
		 */
		private $footnotes = array();

		/**
		 * Running counter, shared across the whole page request.
		 *
		 * @var int
		 */
		private $index = 1;

		/**
		 * Whether the footnote list has already been output.
		 *
		 * @var bool
		 */
		private $rendered = false;

		/**
		 * Constructor — wire up hooks.
		 *
		 * Runs before WordPress's default `do_shortcode` (priority 11) so that
		 * a [cbp_footnotes] shortcode placed in the same content as [Footnote]
		 * tags sees the fully-populated footnote list when it renders.
		 */
		public function __construct() {
			add_filter( 'the_content', array( $this, 'process' ), 8 );
			add_shortcode( 'cbp_footnotes', array( $this, 'shortcode_render' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		}

		/**
		 * Enqueue the default box styling for the footnote container.
		 *
		 * A theme that wants full control over the look can either dequeue
		 * `cbp-footnotes` and supply its own CSS, or return false from the
		 * `cbp_footnotes_enqueue_styles` filter to skip this entirely.
		 *
		 * @return void
		 */
		public function enqueue_styles() {
			if ( ! apply_filters( 'cbp_footnotes_enqueue_styles', true ) ) {
				return;
			}

			wp_enqueue_style(
				'cbp-footnotes',
				CB_FOOTNOTES_PLUGIN_URL . 'assets/cbp-footnotes.css',
				array(),
				CB_FOOTNOTES_VERSION
			);
		}

		/**
		 * Extract [Footnote] tags from a string, replacing each with a link,
		 * then append the rendered footnote list directly to the end of that
		 * same content — unless the content already places the list manually
		 * via the [cbp_footnotes] shortcode.
		 *
		 * Public so themes/blocks can run it over content that doesn't pass
		 * through the `the_content` filter (ACF fields, etc.) — see the
		 * cbp_footnotes_process() helper below.
		 *
		 * @param string $content Content string, possibly containing [Footnote] tags.
		 * @return string Content with tags replaced by footnote links.
		 */
		public function process( $content ) {
			if ( false === stripos( $content, '[footnote]' ) ) {
				return $content;
			}

			// Only the main loop feeds the single running counter. Anything
			// else that runs content through the_content (SEO/schema/TOC
			// pre-passes outside the loop, related-post loops) must not
			// consume indices or trigger the list: otherwise numbering
			// restarts mid-page and the list lands in discarded output —
			// i.e. visible footnotes starting at [4] with no list in the DOM.
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}

			$has_shortcode = has_shortcode( $content, 'cbp_footnotes' );

			$content = preg_replace_callback(
				'/\[Footnote\](.*?)\[\/Footnote\]/is',
				array( $this, 'replace_callback' ),
				$content
			);

			if ( ! $has_shortcode && $this->has_footnotes() && ! $this->rendered ) {
				$this->rendered = true;
				$content       .= $this->render_html();
			}

			return $content;
		}

		/**
		 * Regex callback: stores the footnote and returns its link markup.
		 *
		 * @param array $matches Regex matches; $matches[1] is the footnote text.
		 * @return string HTML link to the stored footnote.
		 */
		private function replace_callback( $matches ) {
			$footnote          = new stdClass();
			$footnote->index   = $this->index++;
			$footnote->content = $matches[1];

			$this->footnotes[] = $footnote;

			return $this->link_to_footnote( $footnote );
		}

	/**
	 * Build the bracketed link markup pointing at a footnote's list entry.
	 *
	 * @param stdClass $footnote Footnote object.
	 * @return string HTML link.
	 */
	private function link_to_footnote( $footnote ) {
		return sprintf(
			'<a href="#footnote-%1$d" id="footnote-ref-%1$d" class="footnote-link">[%1$d]</a>',
			(int) $footnote->index
		);
	}

		/**
		 * Whether any footnotes have been collected so far this request.
		 *
		 * @return bool
		 */
		public function has_footnotes() {
			return ! empty( $this->footnotes );
		}

		/**
		 * Shortcode handler: [cbp_footnotes] — renders the footnote list at
		 * the point it's placed instead of the default end-of-content
		 * position, and marks it as rendered so it isn't output twice.
		 *
		 * @return string Footnotes list HTML, or empty string if none collected
		 *                yet, or if the list was already rendered elsewhere.
		 */
		public function shortcode_render() {
			if ( $this->rendered || ! $this->has_footnotes() ) {
				return '';
			}

			$this->rendered = true;
			return $this->render_html();
		}

		/**
		 * Build the footnote list markup, wrapped in a container div.
		 *
		 * The wrapper defaults to `container cbp-footnotes-box` — `container`
		 * so it lines up with the theme's width-constrained content rather
		 * than bleeding full-width (most themes define a `.container` class
		 * for this, Bootstrap-derived or not), and `cbp-footnotes-box` for the
		 * bordered-box styling in assets/cbp-footnotes.css. If a theme uses a
		 * different class for this, swap it in via the `cbp_footnotes_wrapper_class`
		 * filter (space-separated) instead of overriding the plugin's CSS.
		 *
		 * @return string Footnote list HTML.
		 */
		private function render_html() {
			$wrapper_class = apply_filters( 'cbp_footnotes_wrapper_class', 'container cbp-footnotes-box' );

			ob_start();
			?>
			<div class="<?php echo esc_attr( $wrapper_class ); ?>">
				<ol class="cbp-footnotes-list">
					<?php foreach ( $this->footnotes as $footnote ) : ?>
						<li id="footnote-<?php echo esc_attr( $footnote->index ); ?>">
							<?php echo wp_kses_post( $footnote->content ); ?>
							<a href="#footnote-ref-<?php echo esc_attr( $footnote->index ); ?>" class="cbp-footnote-backlink" aria-label="<?php esc_attr_e( 'Back to content', 'cbp-footnotes' ); ?>">&#8617;</a>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
			<?php
			return ob_get_clean();
		}
	}
} // end class_exists check.

// Instantiate.
if ( class_exists( 'CBFootnotes' ) && ! isset( $GLOBALS['cb_footnotes_instance'] ) ) {
	$GLOBALS['cb_footnotes_instance'] = new CBFootnotes();
}

if ( ! function_exists( 'cbp_footnotes_process' ) ) {
	/**
	 * Helper for themes/blocks to process arbitrary content strings (e.g.
	 * ACF fields) that don't pass through the `the_content` filter.
	 *
	 * @param string $content Content string, possibly containing [Footnote] tags.
	 * @return string Content with tags replaced by footnote links.
	 */
	function cbp_footnotes_process( $content ) {
		if ( ! isset( $GLOBALS['cb_footnotes_instance'] ) ) {
			return $content;
		}
		return $GLOBALS['cb_footnotes_instance']->process( $content );
	}
}
