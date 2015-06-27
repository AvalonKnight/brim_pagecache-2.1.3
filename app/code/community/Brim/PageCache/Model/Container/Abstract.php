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

class Brim_PageCache_Model_Container_Abstract {

    protected $_blockArgs = null;

    /**
     * @var Mage_Core_Block_Abstract
     */
    protected $_block      = null;

    /**
     * @var string
     */
    protected $_cacheKey   = null;

    /**
     * @var simpleXML
     */
    static protected $_updateCache = null;

    /**
     * @param $args
     */
    public function __construct($args=null) {
        $this->_blockArgs = $args;
        $this->_construct($args);
    }

    /**
     * Safe constructor for children to override.
     *
     * @param $args
     * @return void
     */
    protected function _construct($args) {

    }

    /**
     * Generates a usable cache id.
     *
     * @return string
     */
    protected function _getCacheId() {
        return 'BRIM_FPC_DYNAMIC_BLOCK_'
            . Mage::app()->getStore()->getCode() . '_'
            . Mage::getDesign()->getPackageName() . '_'
            . Mage::getDesign()->getTheme('layout') . '_'
            . Mage::app()->getLocale()->getLocaleCode() . '_'
            . Mage::app()->getStore()->getCurrentCurrencyCode() . '_'
            // Separate out the cache by customer group.
            // Helps with Logged in and out users for things like account links
            . Mage::getSingleton('customer/session')->getCustomerGroupId() . '_'
            . (isset($this->_blockArgs['name']) ? $this->_blockArgs['name'] : '')
            . (($this->_cacheKey != null) ? '_' . $this->_cacheKey :  '');
    }

    /**
     * Renders the dynamic block.  Uses cache when possible.
     *
     * @return false|mixed
     */
    final public function renderBlock() {

        if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_CACHE_BLOCK_UPDATES)) {
            $id     = $this->_getCacheId();
            $cache  = Mage::app()->getCache();

            if ($id == false || !($html = $cache->load($id))) {
                $block  = $this->_createBlock();
                $html   = $this->_renderBlock();
                if ($id != false) {
                    Mage::getSingleton('brim_pagecache/engine')->debug('Saving object with cache id: ' . $id);
                    $cache->save(
                        $html,
                        $id,
                        array('BRIM_FPC_DYNAMIC_BLOCK'),
                        Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_EXPIRES)
                    );
                }
            } else {
                //Mage::getSingleton('brim_pagecache/engine')->debug('Block Hit : ' . $id);
            }
        } else {
            $block  = $this->_createBlock();
            $html   = $this->_renderBlock();
        }

        return $html;
    }

    /**
     * Determines if a block requires an update.
     *
     * @return bool
     */
    public function blockRequiresUpdate() {
        return true;
    }

    /**
     * Creates block encoded in the marker for further processing.
     *
     * @return Mage_Core_Block_Abstract|null
     */
    protected function _createBlock() {

        $args   = $this->_blockArgs;
        $layout = new Mage_Core_Model_Layout($args['layout']);
        $layout->generateBlocks();

        return ($this->_block = $layout->getBlock($args['name']));
    }

    /**
     * Safe method for children to overrride w/o affecting the cache.
     *
     * @return string
     */
    protected function _renderBlock() {

        if (!$this->_block) {
            return null;
        }

        if(($html = $this->_block->toHtml()) == '') {
            // saves at least a space to prevent additional generation for the same cache key
            $html = ' ';
        }

        return $html;
    }

    /**
     * returns values to be serialized 
     *
     * @static
     * @param $block
     * @return array
     */
    public static function getContainerArgs($block) {
        return array(
            'block'     => get_class($block),
            'name'      => $block->getNameInLayout(),
            'template'  => $block->getTemplate(),
            'layout'    => self::_generateBlockLayoutXML($block->getNameInLayout())
        );
    }

    protected static function _generateBlockLayoutXML($blockName) {

        $engine = Mage::getSingleton('brim_pagecache/engine');

        // get layout sections references our block
        if (self::$_updateCache == null) {
            self::$_updateCache = Mage::app()->getLayout()->getUpdate()->asSimplexml();
        }
        $sections   = self::$_updateCache->xpath("//block[@name='{$blockName}'] | //reference[@name='{$blockName}']");

        // convert section into it's own layout.
        $layoutXml  = "<layout>";
        foreach($sections as $section) {
            $layoutXml .= self::_generateSubBlockLayoutXml($section);
        }
        $layoutXml .= "</layout>";

        return $layoutXml;
    }

    protected static function _generateSubBlockLayoutXml($section) {
        $layoutXml = $section->asXML();
        foreach ($section->xpath("block") as $block) {
            foreach (self::$_updateCache->xpath("//reference[@name='{$block->getBlockName()}']") as $subSection) {
                $layoutXml .= self::_generateSubBlockLayoutXml($subSection);

            }
        }

        return $layoutXml;
    }
}