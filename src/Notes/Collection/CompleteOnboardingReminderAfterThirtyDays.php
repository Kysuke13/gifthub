<?php
/**
 * Pinterest for WooCommerce CompleteOnboardingAfterThirtyDays class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

defined( 'ABSPATH' ) || exit;

/**
 * Class CompleteOnboardingAfterThirtyDays.
 *
 * Class responsible for admin Inbox notification after three days from setup.
 *
 * @since x.x.x
 */
class CompleteOnboardingReminderAfterThirtyDays extends CompleteOnboardingReminderAfterSevenDays {

	const DELAY = 30;

}
