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
 
class Brim_PageCache_Model_Container_Compared extends Brim_PageCache_Model_Container_Abstract {

    protected function _construct($args) {
        $key = 'COMPARED_' . Mage::getSingleton('log/visitor')->getId();

        $items = Mage::helper('catalog/product_compare')->getItemCollection();
        if (count($items) > 0) {
            foreach ($items as $item) {
                $key .= '_' . $item->getId();
            }
        } else {
            $key .= '_NO_COMPARE';
        }

        $this->_cacheKey = $key;
    }
}