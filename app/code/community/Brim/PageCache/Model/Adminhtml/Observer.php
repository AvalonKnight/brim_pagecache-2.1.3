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

class Brim_PageCache_Model_Adminhtml_Observer extends Varien_Event_Observer {
    /**
      * Observes:
      * controller_action_postdispatch_adminhtml_system_config_save,
      * controller_action_postdispatch_adminhtml_catalog_product_save,
      * controller_action_postdispatch_adminhtml_catalog_product_action_attribute_save,
      * controller_action_postdispatch_adminhtml_catalog_product_massStatus,
      * catalogrule_after_apply

      * @param $observer
      * @return
      */
    public function invalidateCache($observer) {
        $engine = Mage::getSingleton('brim_pagecache/engine');

        if (!$engine->isEnabled()) {
            return;
        }

        $engine->debug(__METHOD__);
        $engine->debug($observer->getEvent()->getName());

        if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_INVALIDATE) == Brim_PageCache_Model_Config::INVALIDATE_FLAG) {
            Mage::app()->getCacheInstance()->invalidateType('brim_pagecache');
        } else {
            Mage::app()->getCacheInstance()->cleanType('brim_pagecache');
        }
    }

    /**
     * Clean cache of any and all cached pages.
     *
     * Observes:
     * application_clean_cache
     *
     * @param $observer
     * @return void
     */
    public function cleanPageCache($observer) {
        $engine = Mage::getSingleton('brim_pagecache/engine');

        if (!$engine->isEnabled()) {
            return;
        }

        $engine->debug(__METHOD__);
        $engine->debug($observer->getEvent()->getName());

        Mage::app()->getCacheInstance()->cleanType('brim_pagecache');
//        $cache      = Mage::app()->getCache();
//        $cache->clean(
//            Zend_Cache::CLEANING_MODE_MATCHING_TAG,
//            array(Brim_PageCache_Model_Engine::FPC_TAG)
//        );
    }
}