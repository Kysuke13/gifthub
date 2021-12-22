// Import WP-scripts presets to extend them,
// see https://developer.wordpress.org/block-editor/packages/packages-scripts/#advanced-information-11.
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

const wcPackagesNeedTransform = [
	'components',
	'currency',
	'data',
	'date',
	'navigation',
	'number',
	'tracks',
	'experimental',
].join( '|' );

module.exports = {
	...defaultConfig,
	// Workaround https://github.com/woocommerce/woocommerce-admin/issues/6483.
	transformIgnorePatterns: [
		`<rootDir>/node_modules/(?!@woocommerce/(${ wcPackagesNeedTransform })(/node_modules/@woocommerce/(${ wcPackagesNeedTransform }))?/build/)`,
	],
	moduleNameMapper: {
		// Transform our `.~/` alias.
		'^\\.~/(.*)$': '<rootDir>/assets/source/$1',
		'@woocommerce/settings':
			'<rootDir>/assets/source/tests/dependencies/woocommerce/settings',
	},
	// Exclude e2e tests from unit testing.
	testPathIgnorePatterns: [ '/node_modules/' ],
	globals: {
		wcSettings: {
			pinterest_for_woocommerce: {
				pluginVersion: '1.2.3',
				pinterestLinks: {
					adsManager: 'https://example.com',
					preLaunchNotice: 'https://example.com',
					adsAvailability: 'https://example.com',
				},
			},
		},
	},
};
