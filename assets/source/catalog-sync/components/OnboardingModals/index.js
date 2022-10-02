/**
 * Internal dependencies
 */
import { useSettingsSelect } from '../../../setup-guide/app/helpers/effects';
import OnboardingModal from './OnboardingModal';
import OnboardingSuccessModal from './OnboardingSuccessModal';

/**
 * Ads Onboarding Modal.
 *
 * @param {Object} options
 * @param {Function} options.onCloseModal Action to call when the modal gets closed.
 *
 * @return {JSX.Element} rendered component
 */
const OnboardingModals = ( { onCloseModal } ) => {
	const adsCampaignIsActive = useSettingsSelect()?.ads_campaign_is_active;

	return (
		<>
			{ adsCampaignIsActive ? (
				<OnboardingModal onCloseModal={ onCloseModal } />
			) : (
				<OnboardingSuccessModal onCloseModal={ onCloseModal } />
			) }
		</>
	);
};

export default OnboardingModals;
