/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useEffect,
	useState,
	createInterpolateElement,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@woocommerce/components';
import {
	Button,
	Card,
	CardBody,
	Notice,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import StepStatus from '../components/StepStatus';
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const StaticError = ( { reqError } ) => {
	if ( reqError?.data?.pinterest_code === undefined ) {
		return null;
	}

	const staticErrors = [ 71, 75 ]; // See https://developers.pinterest.com/docs/redoc/#tag/API-Response-Codes

	if ( ! staticErrors.includes( reqError.data.pinterest_code ) ) {
		return null;
	}

	const message = createInterpolateElement(
		sprintf(
			// translators: %s: error reason returned by Pinterest when verifying website claim fail.
			__(
				'<strong>We were unable to verify this domain.</strong> %s',
				'pinterest-for-woocommerce'
			),
			reqError.message
		),
		{
			strong: <strong />,
		}
	);

	return (
		<Notice status="error" isDismissible={ false }>
			{ message }
		</Notice>
	);
};

const ClaimWebsite = ( { goToNextStep, view } ) => {
	const [ status, setStatus ] = useState( 'idle' );
	const [ reqError, setReqError ] = useState();
	const isDomainVerified = useSettingsSelect( 'isDomainVerified' );
	const setAppSettings = useSettingsDispatch( view === 'wizard' );
	const createNotice = useCreateNotice();

	useEffect( () => {
		if ( status !== 'pending' && isDomainVerified ) {
			setStatus( 'success' );
		}
	}, [ status, isDomainVerified ] );

	const handleClaimWebsite = async () => {
		setStatus( 'pending' );
		setReqError();

		try {
			const results = await apiFetch( {
				path:
					wcSettings.pinterest_for_woocommerce.apiRoute +
					'/domain_verification',
				method: 'POST',
			} );

			await setAppSettings( { account_data: results.account_data } );

			setStatus( 'success' );
		} catch ( error ) {
			setStatus( 'error' );
			setReqError( error );

			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t verify your domain.',
						'pinterest-for-woocommerce'
					)
			);
		}
	};

	const StepButton = () => {
		const buttonLabels = {
			idle: __( 'Start Verification', 'pinterest-for-woocommerce' ),
			pending: __( 'Verifying Domain', 'pinterest-for-woocommerce' ),
			error: __( 'Try Again', 'pinterest-for-woocommerce' ),
			success: __( 'Continue', 'pinterest-for-woocommerce' ),
		};

		return (
			<Button
				isPrimary
				disabled={ status === 'pending' }
				onClick={
					status === 'success' ? goToNextStep : handleClaimWebsite
				}
			>
				{ buttonLabels[ status ] }
			</Button>
		);
	};

	return (
		<div className="woocommerce-setup-guide__claim-website">
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Claim your website',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step Two', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={
							view === 'wizard'
								? __(
										'Claim your website',
										'pinterest-for-woocommerce'
								  )
								: __(
										'Verified domain',
										'pinterest-for-woocommerce'
								  )
						}
						description={ __(
							'Claim your website to get access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site and let people know where they can find more of your content.'
						) }
						link={
							wcSettings.pinterest_for_woocommerce.pinterestLinks
								.claimWebsite
						}
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						{ undefined !== isDomainVerified ? (
							<CardBody size="large">
								<Text variant="subtitle">
									{ __(
										'Verify your domain to claim your website',
										'pinterest-for-woocommerce'
									) }
								</Text>
								<Text variant="body">
									{ __(
										'This will allow access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site, and let people know where they can find more of your content.',
										'pinterest-for-woocommerce'
									) }
								</Text>

								<StepStatus
									label={
										wcSettings.pinterest_for_woocommerce
											.homeUrlToVerify
									}
									status={ status }
								/>

								<StaticError reqError={ reqError } />

								{ view === 'settings' && ! isDomainVerified && (
									<StepButton />
								) }
							</CardBody>
						) : (
							<CardBody size="large">
								<Spinner />
							</CardBody>
						) }
					</Card>

					{ view === 'wizard' && (
						<div className="woocommerce-setup-guide__footer-button">
							<StepButton />
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default ClaimWebsite;
