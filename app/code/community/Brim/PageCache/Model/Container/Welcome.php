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
 
class Brim_PageCache_Model_Container_Welcome
    extends Brim_PageCache_Model_Container_Abstract {

    protected function _construct($args) {
        // Using customer id on purpose here.  Non customers share an id 0.
        $this->_cacheKey = 'WELCOME_' . Mage::getSingleton('customer/session')->getCustomer()->getId();
    }

    /**
     * WE don't actually want a welcome block.
     *
     * @return void
     */
    protected function _createBlock() {
        return null;
    }

    /**
     * @return string
     */
    protected function _renderBlock() {
        return Mage::app()->getLayout()->createBlock('page/html_header')->getWelcome();
    }

    /**
     * Marks the welcome content. Required since the welcome content is not it's own block, but
     * a method on the page/html_header block.
     *
     * @static
     * @param $block
     * @return void
     */
    static public function setWelcomeWrapper($block) {
        $args = array(
            'name'      => 'welcome_container',
            'container' => 'Brim_PageCache_Model_Container_Welcome'
        );

        $welcome = Mage::getSingleton('brim_pagecache/engine')->markContent($args, $block->getWelcome());

        $block->setWelcome($welcome);
    }
}