<?php
/**
 * Brim LLC Commercial Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Brim LLC Commercial Extension License
 * that is bundled with this package in the file Brim-LLC-Magento-License.pdf.
 * It is also available through the world-wide-web at this URL:
 * http://ecommerce.brimllc.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@brimllc.com so we can send you a copy immediately.
 *
 * @category   Brim
 * @package    Brim_PageCache
 * @copyright  Copyright (c) 2011-2012 Brim LLC
 * @license    http://ecommerce.brimllc.com/license
 */
 
class Brim_PageCache_Model_Container_Wishlist extends Brim_PageCache_Model_Container_Abstract {

    /**
     * Initializes the wishlist container.
     *
     * @param $args
     * @return void
     */
    protected function _construct($args) {
        //
        $customer   = Mage::getSingleton('customer/session');

        /**
         * @var $wishlistHelper Mage_Wishlist_Helper_Data
         */
        $wishlistHelper = Mage::helper('wishlist');

        if ($wishlistHelper->hasItems()) {
            $hash   = array();
            $items  = $wishlistHelper->getItemCollection();
            foreach ($items as $item) {
                $hash[] = $item->getId();
            }
            $this->_cacheKey = $customer->getCustomerGroupId()
                . '_' . md5(join('-', $hash));
        } else {
            $this->_cacheKey = $customer->getCustomerGroupId() . '_' . 'empty_wishlist';
        }
    }
}
