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

class Brim_PageCache_Model_Container_Recentlyviewed
    extends Brim_PageCache_Model_Container_Abstract
{
    const COOKIE = 'BRIM_FPC_RECENTLY_VIEWED';

    const COOKIE_SEPARATOR = '_';

    protected $_productIds  = null;

    /**
     * Init's the enclosed block.
     *
     * @return void
     */
    protected function _construct($args) {
        $productIds = $this->_getProductIds();
        $request    = Mage::app()->getRequest();

        if ($request->getModuleName() == 'catalog'
            && $request->getControllerName() == 'product'
            && $request->getActionName() == 'view') {
            // Simulates current product to support excluded products for the recently viewed abstract model
            Mage::register('current_product', new Varien_Object(array('id' => $request->getParam('id'))));

            // removes current product from the list.
            $productIds = array_diff($productIds, array($request->getParam('id')));
        }

        $this->_productIds  = array_slice(
            $productIds,
            0,
            Mage::getStoreConfig(Mage_Reports_Block_Product_Viewed::XML_PATH_RECENTLY_VIEWED_COUNT)
        );
        $this->_cacheKey    = join('_', $productIds);
    }

    /**
     *
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _createBlock() {
        parent::_createBlock();

        /*
         * Sets product ids for a lighter DB Query. We remove and add products back to simulate the order in which they
         * were first viewed.  Due to the small size of the cookie products may cycle through the recently viewed products
         * list more often than normal.
         */
        $this->_block->setProductIds($this->_productIds);

        $_collection = $this->_block->getItemsCollection();
        if (is_object($_collection)) {
            foreach ($this->_productIds as $_productId) {
                $_item = $_collection->getItemById($_productId);
                $_collection->removeItemByKey($_productId);
                if ($_item) {
                    $_collection->addItem($_item);
                }
            }
        }

        return $this->_block;
    }

    /**
     * Returns the last viewed products for the user.
     *
     * @return array
     */
    protected function _getProductIds() {
        $cookieValue = trim(Mage::app()->getCookie()->get(self::COOKIE), ' ' . self::COOKIE_SEPARATOR);

        $cleanValues = array();
        foreach (explode(self::COOKIE_SEPARATOR, $cookieValue) as $value) {
            if ((int)$value > 0) {
                $cleanValues[] = (int)$value;
            }
        }

        return $cleanValues;
    }

    /**
     * Adds products to the recently viewed list.
     *
     * @static
     * @param Varien_Object $args
     * @return
     */
    public static function addProductViewed(Varien_Object $args) {

        if (($newId = $args->getId()) != null) {
            $cookie = Mage::app()->getCookie();
            $ids = explode(self::COOKIE_SEPARATOR, $cookie->get(self::COOKIE));

            if (!in_array($newId, $ids)) {
                array_unshift($ids, $newId);
            }
            $ids    = array_slice(
                array_unique($ids),
                0,
                //stores double the viewable products
                Mage::getStoreConfig(Mage_Reports_Block_Product_Viewed::XML_PATH_RECENTLY_VIEWED_COUNT)*2
            );

            $cookie->set(self::COOKIE, trim(join(self::COOKIE_SEPARATOR, $ids), ' ,' . self::COOKIE_SEPARATOR));
        }

        return true;
    }
}