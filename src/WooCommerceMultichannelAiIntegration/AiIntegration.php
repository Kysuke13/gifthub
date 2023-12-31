<?php

namespace Automattic\WooCommerce\Pinterest\WooCommerceMultichannelAiIntegration;

use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;

// Check if the setting is enabled ( managed by the Store Manager AI plugin )
use Automattic\WooCommerce\MultichannelProductAi\PluginSettings;

class AiIntegration {

    public const ATTRIBUTES = array(
        'google_product_category',
        'condition',
    );

    public function init() {
        add_filter( 'woocommerce_multichannel_product_ai_request_attributes', array( $this, 'request_attributes' ), 10, 2 );
        add_action( 'woocommerce_multichannel_product_ai_attributes_ready', array( $this, 'attributes_ready' ), 10, 3 );
        add_filter( 'store_manager_ai_settings_sections', array( $this, 'add_settings_section' ) );
    }

    public function request_attributes( $attributes ) {
        return array_merge(
            $attributes,
            array( 'pinterest_for_woocommerce' => self::ATTRIBUTES )
        );
    }

    public function add_settings_section( $sections ) {
        $sections['Pinterest'] = self::ATTRIBUTES;
        return $sections;
    }

    public function should_update_attribute( $attribute ) {
        if( ! in_array( $attribute, self::ATTRIBUTES ) ) {
            return false;
        }

        if ( class_exists( PluginSettings::class ) ) {
            return PluginSettings::get_setting_value( 'pinterest', $attribute ) === '1';
        }

        return false;
    }

    public function attributes_ready( $plugin, $attributes, $product_id ) {

        if ( $plugin !== 'pinterest_for_woocommerce' ) {
            return $attributes;
        }

        foreach ( $attributes as $attribute => $value ) {
            if ( ! $this->should_update_attribute( $attribute ) ) {
                continue;
            }
            if ( $attribute === 'google_product_category' ) {
                $this->update_google_product_category_for_a_product( $product_id, $value );
                continue;
            }
            if ( $attribute === 'condition' ) {
                $this->update_condition_for_a_product( $product_id, $value );
                continue;
            }
        }
        return $attributes;
    }

    // Update the condition for a product.
    private function update_condition_for_a_product( $product_id, $new_condition_value ) {

        // Instantiate the AttributeManager.
        $attributeManager = AttributeManager::instance();

        // Get the WooCommerce product.
        $product = wc_get_product( $product_id );

        // Create a Condition attribute object with the new condition value.
        $conditionAttribute = new Condition( $new_condition_value );

        // Update the condition for the product.
        $attributeManager->update( $product, $conditionAttribute );

    }

    private function update_google_product_category_for_a_product( $product_id, $new_category_value ) {

        // Instantiate the AttributeManager.
        $attributeManager = AttributeManager::instance();

        // Get the WooCommerce product.
        $product = wc_get_product ($product_id );

        // Create a GoogleCategory attribute object with the new category value.
        $googleCategoryAttribute = new GoogleCategory( $new_category_value );

        // Update the google_product_category for the product.
        $attributeManager->update( $product, $googleCategoryAttribute );

    }
}

