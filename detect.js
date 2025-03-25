/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("../optimization-detective/types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.ts").ExtendElementDataFunction} ExtendElementDataFunction
 */

const dataXPathAttribute = 'data-od-xpath';

const dataSrcHashAttribute = 'data-od-intrinsic-dimensions-src-hash';

/**
 * Captures the intrinsic dimensions of an element.
 *
 * @param {HTMLImageElement|HTMLVideoElement} element           - Element.
 * @param {ExtendElementDataFunction}         extendElementData - Function to extend element data.
 */
function captureIntrinsicDimensions( element, extendElementData ) {
	const xpath = element.getAttribute( dataXPathAttribute );
	if ( element instanceof HTMLImageElement ) {
		extendElementData( xpath, {
			width: element.naturalWidth,
			height: element.naturalHeight,
			srcHash: element.getAttribute( dataSrcHashAttribute ),
		} );
	} else if ( element instanceof HTMLVideoElement ) {
		extendElementData( xpath, {
			width: element.videoWidth,
			height: element.videoHeight,
			srcHash: element.getAttribute( dataSrcHashAttribute ),
		} );
	}
}

/**
 * Initializes extension.
 *
 * @since 0.1.0
 *
 * @type {InitializeCallback}
 */
export async function initialize( { extendElementData } ) {
	/** @type NodeListOf<HTMLImageElement> */
	const imgElements = document.querySelectorAll(
		`img[ ${ dataXPathAttribute } ][ ${ dataSrcHashAttribute } ]`
	);
	for ( /** @type {HTMLImageElement} */ const element of imgElements ) {
		if ( element.complete ) {
			captureIntrinsicDimensions( element, extendElementData );
		} else {
			element.addEventListener(
				'load',
				( event ) =>
					captureIntrinsicDimensions(
						/** @type {HTMLImageElement} */ ( event.target ),
						extendElementData
					),
				{ once: true }
			);
		}
	}

	/** @type NodeListOf<HTMLVideoElement> */
	const videoElements = document.querySelectorAll(
		`video[ ${ dataXPathAttribute } ][ ${ dataSrcHashAttribute } ]`
	);
	for ( /** @type {HTMLVideoElement} */ const element of videoElements ) {
		if ( element.readyState >= HTMLMediaElement.HAVE_METADATA ) {
			captureIntrinsicDimensions( element, extendElementData );
		} else {
			element.addEventListener(
				'loadedmetadata',
				( event ) =>
					captureIntrinsicDimensions(
						/** @type {HTMLVideoElement} */ ( event.target ),
						extendElementData
					),
				{ once: true }
			);
		}
	}
}
