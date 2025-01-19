/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("../optimization-detective/types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.ts").FinalizeArgs} FinalizeArgs
 * @typedef {import("../optimization-detective/types.ts").FinalizeCallback} FinalizeCallback
 */

const dataXPathAttribute = 'data-od-xpath';

const dataSrcHashAttribute = 'data-od-intrinsic-dimensions-src-hash';

/**
 * Map of element XPath to its intrinsic dimensions.
 *
 * @type {Map<string, {width: number, height: number, srcHash: string}>}
 */
const intrinsicDimensionsByXPath = new Map();

/**
 * Captures the intrinsic dimensions of an element.
 *
 * @param {HTMLImageElement|HTMLVideoElement} element - Element.
 */
function captureIntrinsicDimensions( element ) {
	const xpath = element.getAttribute( dataXPathAttribute );
	if ( element instanceof HTMLImageElement ) {
		intrinsicDimensionsByXPath.set( xpath, {
			width: element.naturalWidth,
			height: element.naturalHeight,
			srcHash: element.getAttribute( dataSrcHashAttribute ),
		} );
	} else if ( element instanceof HTMLVideoElement ) {
		intrinsicDimensionsByXPath.set( xpath, {
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
export async function initialize() {
	/** @type NodeListOf<HTMLImageElement> */
	const imgElements = document.querySelectorAll(
		`img[ ${ dataXPathAttribute } ][ ${ dataSrcHashAttribute } ]`
	);
	for ( /** @type {HTMLImageElement} */ const element of imgElements ) {
		if ( element.complete ) {
			captureIntrinsicDimensions( element );
		} else {
			element.addEventListener(
				'load',
				( event ) =>
					captureIntrinsicDimensions(
						/** @type {HTMLImageElement} */ ( event.target )
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
			captureIntrinsicDimensions( element );
		} else {
			element.addEventListener(
				'loadedmetadata',
				( event ) =>
					captureIntrinsicDimensions(
						/** @type {HTMLVideoElement} */ ( event.target )
					),
				{ once: true }
			);
		}
	}
}

/**
 * Finalizes extension.
 *
 * @since 0.1.0
 *
 * @type {FinalizeCallback}
 * @param {FinalizeArgs} args Args.
 */
export async function finalize( { extendElementData } ) {
	for ( const [
		xpath,
		intrinsicDimensions,
	] of intrinsicDimensionsByXPath.entries() ) {
		extendElementData( xpath, { intrinsicDimensions } );
	}
}
