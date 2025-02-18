<?php
/**
 * Helper functions used for Optimization Detective Intrinsic Dimensions.
 *
 * @package od-intrinsic-dimensions
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Registers tag visitor.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Registry $registry Registry.
 */
function odid_register_tag_visitor( OD_Tag_Visitor_Registry $registry ): void {
	$registry->register( 'od-intrinsic-dimensions', 'odid_visit_tag' );
}

/**
 * Checks whether the provided attribute value is a valid dimension.
 *
 * @param string|true|null $value Attribute value.
 * @return bool Is valid.
 */
function odid_is_valid_dimension( $value ): bool {
	return (
		is_string( $value )
		&&
		1 === preg_match( '/^\d+%?$/', trim( $value ) ) // Note that is_numeric() cannot be used because is_numeric('100%') is false.
	);
}

/**
 * Visits a tag.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Context $context Tag visitor context.
 */
function odid_visit_tag( OD_Tag_Visitor_Context $context ): void {
	$processor = $context->processor;

	// Short-circuit if not visiting a relevant tag.
	if ( ! in_array( $processor->get_tag(), array( 'IMG', 'VIDEO' ), true ) ) {
		return;
	}

	// No need to track this element in URL Metrics to supply intrinsic dimensions if the width and height are already supplied.
	if (
		odid_is_valid_dimension( $processor->get_attribute( 'width' ) )
		&&
		odid_is_valid_dimension( $processor->get_attribute( 'height' ) )
	) {
		return;
	}

	/*
	 * From here on out, we know that we want to track the element in URL Metrics even if the collected URL Metrics may
	 * not yet mean the optimization can be applied (e.g. no intrinsicDimensions have been collected yet or not all the
	 * intrinsicDimensions are equal).
	 */
	$context->track_tag();

	// Compute a hash of the sources for the IMG/VIDEO. This is a safeguard used to ensure that we only apply the
	// previously-captured intrinsic dimensions if the source(s) for those dimensions match the current source(s).
	$sources  = array();
	$src_attr = $processor->get_attribute( 'src' );
	if ( is_string( $src_attr ) ) {
		$sources[] = $src_attr;
	} elseif ( 'VIDEO' === $processor->get_tag() ) {
		// If a src attribute is not present, we have to look at the SOURCE tags for what the sources are.
		$bookmark = 'intrinsic_dimensions_video';
		if ( ! $processor->set_bookmark( $bookmark ) ) {
			// Unable to set a bookmark so we have to abort.
			return;
		}

		while ( $processor->next_tag() ) {
			if ( $processor->get_tag() === 'SOURCE' ) { // @phpstan-ignore identical.alwaysFalse
				$src = $processor->get_attribute( 'src' );
				if ( is_string( $src ) ) {
					$sources[] = $src;
				}
			} elseif ( $processor->get_tag() === 'VIDEO' ) {
				// Stop once we got to the closing tag.
				break;
			}
		}

		if ( ! $processor->seek( $bookmark ) ) {
			// If unable to seek back to the VIDEO, we have to abort optimization.
			return;
		}
	}
	if ( 'IMG' === $processor->get_tag() ) {
		$srcset = $processor->get_attribute( 'srcset' );
		if ( is_string( $srcset ) ) {
			$sources[] = $srcset;
		}
	}
	$source_hash = md5( (string) wp_json_encode( $sources ) );
	$processor->set_meta_attribute( 'intrinsic-dimensions-src-hash', $source_hash );

	// Get this element from all URL Metrics.
	$xpath    = $processor->get_xpath();
	$elements = $context->url_metric_group_collection->get_xpath_elements_map()[ $xpath ] ?? array();

	$all_intrinsic_dimensions = array_filter( // Remove any null values.
		array_map(
			/**
			 * Gets the stored intrinsic dimensions from the element.
			 *
			 * The intrinsicDimensions value will be null if the element's URL Metric was collected before this
			 * extension was activated.
			 *
			 * @return array{width: int, height: int, srcHash: non-empty-string}|null Intrinsic dimensions.
			 */
			static function ( OD_Element $element ): ?array {
				return $element->get( 'intrinsicDimensions' );
			},
			$elements
		)
	);

	// No intrinsic dimensions have been collected yet.
	if ( count( $all_intrinsic_dimensions ) === 0 ) {
		return;
	}

	// Make sure all dimensions are equal, since if there is any variation then this indicates the IMG may point to a URL
	// that returns an image with varying dimensions, or the IMG/VIDEO is sorted randomly among siblings.

	/**
	 * Intrinsic dimensions.
	 *
	 * @var array{width: int, height: int, srcHash: non-empty-string} $intrinsic_dimensions
	 */
	$intrinsic_dimensions = array_shift( $all_intrinsic_dimensions );
	foreach ( $all_intrinsic_dimensions as $next_intrinsic_dimensions ) {
		if ( $intrinsic_dimensions !== $next_intrinsic_dimensions ) {
			return;
		}
	}

	// Abort if the current hash of the sources does not match the hash of the sources for the captured intrinsic dimensions.
	if ( $source_hash !== $intrinsic_dimensions['srcHash'] ) {
		return;
	}

	// Set the width and height to reflect the captured intrinsic dimensions.
	$processor->set_attribute( 'width', (string) $intrinsic_dimensions['width'] );
	$processor->set_attribute( 'height', (string) $intrinsic_dimensions['height'] );
	if ( 'VIDEO' === $processor->get_tag() ) {
		// TODO: It's not clear why the aspect-ratio needs to be specified when the user agent style is already defining `aspect-ratio: auto $width / $height;`.
		// TODO: Also, the Video block has styles the VIDEO with width:100% but it lacks height:auto. Why?
		// TODO: Why does the Image block use width:content-fit?
		$style = sprintf( 'height: auto; width: 100%%; aspect-ratio: %d / %d;', $intrinsic_dimensions['width'], $intrinsic_dimensions['height'] );

		$old_style = $processor->get_attribute( 'style' );
		if ( is_string( $old_style ) ) {
			$style .= $old_style;
		}

		$processor->set_attribute( 'style', $style );
	}
}

/**
 * Filters the list of Optimization Detective extension module URLs to include the extension for Intrinsic Dimensions.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function odid_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = plugins_url( add_query_arg( 'ver', OD_INTRINSIC_DIMENSIONS_VERSION, 'detect.js' ), __FILE__ );
	return $extension_module_urls;
}

/**
 * Filters additional properties for the root schema for Optimization Detective.
 *
 * @since 0.1.0
 * @access private
 *
 * @param array<string, array{type: string}>|mixed $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function odid_add_element_item_schema_properties( $additional_properties ): array {
	if ( ! is_array( $additional_properties ) ) {
		$additional_properties = array();
	}

	$additional_properties['intrinsicDimensions'] = array(
		'type'       => 'object',
		'properties' => array(
			'width'   => array(
				'type'     => 'integer',
				'minimum'  => 0,
				'required' => true,
			),
			'height'  => array(
				'type'     => 'integer',
				'minimum'  => 0,
				'required' => true,
			),
			'srcHash' => array(
				'type'     => 'string',
				'pattern'  => '^[0-9a-f]{32}\z',
				'required' => true,
			),
		),
	);
	return $additional_properties;
}
