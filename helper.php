<?php
/**
 * Helper functions used for Optimization Detective Intrinsic Dimensions.
 *
 * @package od-intrinsic-dimensions
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
 * Visits a tag.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Context $context Tag visitor context.
 * @return bool Whether the tag should be tracked in URL Metrics.
 */
function odid_visit_tag( OD_Tag_Visitor_Context $context ): bool {
	$processor = $context->processor;
	if ( ! in_array( $processor->get_tag(), array( 'IMG', 'VIDEO' ), true ) ) {
		return false;
	}

	// No need to track this element in URL Metrics to supply intrinsic dimensions if the width and height are already supplied.
	$width  = $processor->get_attribute( 'width' );
	$height = $processor->get_attribute( 'height' );
	if (
		is_string( $width )
		&&
		is_numeric( $width )
		&&
		is_string( $height )
		&&
		is_numeric( $height )
	) {
		return false;
	}

	$processor->add_class( 'od-missing-dimensions' );

	$xpath              = $processor->get_xpath();
	$xpath_elements_map = $context->url_metric_group_collection->get_xpath_elements_map();

	if ( isset( $xpath_elements_map[ $xpath ] ) ) {
		$all_intrinsic_dimensions = array_map(
			static function ( OD_Element $element ) {
				return $element->get( 'intrinsicDimensions' );
			},
			array_filter(
				$xpath_elements_map[ $xpath ],
				static function ( OD_Element $element ) {
					return is_array( $element->get( 'intrinsicDimensions' ) );
				}
			)
		);

		$common_intrinsic_dimensions = null;
		$intrinsic_dimensions_count  = count( $all_intrinsic_dimensions );
		if ( $intrinsic_dimensions_count > 0 ) {
			// If we encountered one of the URL Metrics which captured an inconsistency in the captured intrinsic dimensions for this element, abort.
			$common_intrinsic_dimensions = $all_intrinsic_dimensions[0];
			for ( $i = 1; $i < $intrinsic_dimensions_count; $i++ ) {
				if ( $all_intrinsic_dimensions[ $i ] !== $common_intrinsic_dimensions ) {
					$common_intrinsic_dimensions = null;
					break;
				}
			}
		}

		// Set the width and height to reflect the captured intrinsic dimensions.
		if ( isset( $common_intrinsic_dimensions['width'], $common_intrinsic_dimensions['height'] ) ) {
			$processor->set_attribute( 'width', $common_intrinsic_dimensions['width'] );
			$processor->set_attribute( 'height', $common_intrinsic_dimensions['height'] );
			if ( 'VIDEO' === $processor->get_tag() ) {
				// TODO: It's not clear why the aspect-ratio needs to be specified when the user agent style is already defining `aspect-ratio: auto $width / $height;`.
				// TODO: Also, the Video block has styles the VIDEO with width:100% but it lacks height:auto. Why?
				// TODO: Why does the Image block use width:content-fit?
				$style = sprintf( 'height: auto; width: 100%%; aspect-ratio: %d / %d;', $common_intrinsic_dimensions['width'], $common_intrinsic_dimensions['height'] );

				$old_style = $processor->get_attribute( 'style' );
				if ( is_string( $old_style ) ) {
					$style .= $old_style;
				}

				$processor->set_attribute( 'style', $style );
			}
		}
	}

	return true;
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
			'width'  => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'height' => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		),
	);
	return $additional_properties;
}
