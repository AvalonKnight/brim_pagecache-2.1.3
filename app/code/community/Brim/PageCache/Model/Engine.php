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
 
class Brim_PageCache_Model_Engine {

    const RESPONSE_HEADER               = 'X-Fpc';

    const RESPONSE_HEADER_EXPIRES_DATE  = 'X-Fpc-Expires-Date';

    const RESPONSE_HEADER_EXPIRES       = 'X-Fpc-Expires-Length';

    const RESPONSE_HEADER_CONDITIONS    = 'X-Fpc-Conditions';

    const RESPONSE_HEADER_STORE         = 'X-Fpc-Store';

    const RESPONSE_HEADER_ORIG_TIME     = 'X-Fpc-Orig-Time';

    const RESPONSE_HEADER_MISS          = 'X-Fpc-Miss';

    const RESPONSE_HEADER_FAILED        = 'X-Fpc-Reason';

    const FPC_TAG       = 'BRIM_FPC';

    const CACHE_HIT     = 'Hit';
    const CACHE_MISS    = 'Miss';

    const DEBUG_LOG = 'brim-fpc-debug.log';

    /**
     * @var array status of conditionals for caching pages.
     */
    protected $_conditions = array(
        'logged_out'    => 0,
        'empty_cart'    => 0,
        'no_messages'   => 0,
    );

    /**
     * @var bool condition initialization flag
     */
    protected $_initConditions = false;

    /**
     * @var null Cached fpc cache id, prevents the need to generate multiple times.
     */
    protected $_fpcCacheId  = null;

    /**
     * @var array Contains tags page will be cache with
     */
    protected $_pageTags    = array(self::FPC_TAG);

    /**
     * @var array Holds arguments to generate block update containers.
     */
    protected $_blockUpdateData = array();

    /**
     * Time tryServe was entered.  Used for debugging.
     * @var null
     */
    protected static $_start_time = null;

    /**
     * @var array contains the conditionals that failed for debugging.
     */
    protected $_failed_conditions = null;

    /**
     * Constructs engine.
     *
     * @return void
     */
    public function __construct() {

    }

    /**
     * Determines if we are enabled.
     *
     * @return bool
     */
    public function isEnabled() {
        return Mage::app()->useCache('brim_pagecache')
               && Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_ENABLED);
    }

    /**
     * Checks to see if a page can be full page cached.
     *
     * @return bool
     */
    public function isPageCachable() {
        $rootBlock = Mage::app()->getLayout()->getBlock('root');
        if ($rootBlock && $rootBlock->getCachePageFlag()) {
            return true;
        }
        
        return false;
    }

    /**
     * Handles logic for caching pages.  Currently checks the root block for the
     * "CachePageFlag".  Pages are only cached if the user is not logged in and does
     * not have items in their cart.
     *
     * @param $observer
     * @return void
     */
    public function cachePage(Zend_Controller_Response_Http $response) {

        // Check if cache flag is set
        $rootBlock = Mage::app()->getLayout()->getBlock('root');
        if ($rootBlock && $rootBlock->getCachePageFlag()) {
            if ($this->passesConditions($rootBlock->getCachePageConditions())) {

                $cache      = Mage::app()->getCache();
                $id         = $this->generateFPCId();

                if ($rootBlock->getCachePageExpires() > 0) {
                    $expires = $rootBlock->getCachePageExpires();
                } else {
                    //default expires
                    $expires = Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_EXPIRES);
                }

                $storageObject = Mage::getModel('brim_pagecache/storage');

                if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_ENABLE_MINIFY_HTML)) {
                    // Minify the response body.  Helps save on cache storage space
                    // Basic grouped product page 34k in size was about 28K minified,
                    $minifyBody = Brim_PageCache_Model_Minify_HTML::minify($response->getBody());
                    $response->setBody($minifyBody);
                }

                $storageObject->setResponse($response);

                /**
                 * Block update data contains partial layouts for each block.  Allows us to regenerate each one
                 * with out loosing customizations.
                 */
                $storageObject->setBlockUpdateData($this->getBlockUpdateData());

                // Set the expected expires time for this page
                $date      = Mage::app()->getLocale()->date()->addSecond($expires);
                $storageObject->setData(
                    self::RESPONSE_HEADER_EXPIRES_DATE,
                    $date->get(Zend_Date::RFC_1123)
                );
                $storageObject->setData(
                    self::RESPONSE_HEADER_EXPIRES,
                    $expires
                );
                $storageObject->setData(
                    self::RESPONSE_HEADER_CONDITIONS,
                    $rootBlock->getCachePageConditions()
                );
                $storageObject->setData(
                    self::RESPONSE_HEADER_STORE,
                    Mage::app()->getStore()->getCode()
                );

                $storageObject->setData(
                    self::RESPONSE_HEADER_ORIG_TIME,
                    microtime(true) - self::$_start_time
                );

                $this->debug('Saving page with cache id : ' . $id);

                if (($product = Mage::registry('product')) != null) {
                    $this->devDebug('Registering Tag: '. self::FPC_TAG . '_PRODUCT_' . $product->getId());
                    $this->registerPageTags(self::FPC_TAG . '_PRODUCT_' . $product->getId());
                }
                if (($category = Mage::registry('current_category')) != null && $product == null) {
                    $this->devDebug('Registering Tag: '. self::FPC_TAG . '_CATEGORY_' . $category->getId());
                    $this->registerPageTags(self::FPC_TAG . '_CATEGORY_' . $category->getId());
                }

                $cache->save(serialize($storageObject), $id, $this->getPageTags(), $expires);
            } else {
                // failed conditions
                if ($response->canSendHeaders(false)) {
                    $response->setHeader(
                        self::RESPONSE_HEADER_FAILED,
                        $this->_failed_conditions
                    );
                }
            }
        } else {
            if ($response->canSendHeaders(false)) {
                // page not set to cache
                $response->setHeader(
                    self::RESPONSE_HEADER_FAILED,
                    'no_cache'
                );
            }
        }
    }

    /**
     * Added cache tags to page.
     *
     * @param array $tags
     */
    public function registerPageTags($tags) {
        if (is_string($tags)) {
            $tags = array($tags);
        }

        foreach ($tags as $tag) {
            $this->_pageTags[] = $tag;
        }
    }

    /**
     * Returns cache tags for the page page.
     *
     * @return array
     */
    public function getPageTags() {
        return $this->_pageTags;
    }

    /**
     * Checks cache for cached pages.  If found the original response is served,
     * otherwise normal processing will occur.
     *
     * @param $observer
     * @return void
     */
    public function servePage() {
        if (!$this->isEnabled()) {
            return;
        }

        Varien_Profiler::start('Brim_PageCache::servepage');

        self::$_start_time = microtime(true);

        //
        $cache  = Mage::app()->getCache();
        $id     = $this->generateFPCId();

        try {
            // Process Action Events
            $this->processFPCActions();

            $response = Mage::app()->getResponse();

            // Check for cached page
            $this->debug('Checking page cache with Id : ' . $id);
            //if (false && ($cachedData = $cache->load($id))) {
            if (empty($_REQUEST['FORCE_MISS']) && ($cachedData = $cache->load($id))) {
                $cachedStorage = unserialize($cachedData);
                if ($cachedStorage instanceof Brim_PageCache_Model_Storage) {

                    /**
                     * @var $cachedResponse Zend_Controller_Response_Http
                     */
                    $cachedResponse = $cachedStorage->getResponse();

                    // Check page conditions
                    if ($this->passesConditions($cachedStorage[self::RESPONSE_HEADER_CONDITIONS])) {

                        $this->debug('Serving cached page.');

                        $response->setHeader(self::RESPONSE_HEADER, self::CACHE_HIT);

                        if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_DEBUG_RESPONSE)) {
                            $response->setHeader(
                                self::RESPONSE_HEADER_EXPIRES_DATE,
                                $cachedStorage[self::RESPONSE_HEADER_EXPIRES_DATE]
                            );
                            $response->setHeader(
                                self::RESPONSE_HEADER_EXPIRES,
                                $cachedStorage[self::RESPONSE_HEADER_EXPIRES]
                            );
                            $response->setHeader(
                                self::RESPONSE_HEADER_CONDITIONS,
                                $cachedStorage[self::RESPONSE_HEADER_CONDITIONS]
                            );
                            $response->setHeader(
                                self::RESPONSE_HEADER_STORE,
                                $cachedStorage[self::RESPONSE_HEADER_STORE]
                            );
                            $response->setHeader(
                                self::RESPONSE_HEADER_ORIG_TIME,
                                $cachedStorage[self::RESPONSE_HEADER_ORIG_TIME]
                            );
                        }

                        // Apply cached response status header to current response
                        foreach ($cachedResponse->getHeaders() as $header) {
                            if (strcasecmp($header['name'], 'status') === 0) {
                                $response->setHeader($header['name'], $header['value']);
                                if (($responseCode = (int) $header['value']) > 0) {
                                    // response code must be parsed from the status header as the cached header
                                    // stores a 200 in http_response_code even when the page was a 404
                                    $response->setHttpResponseCode($responseCode);
                                }
                            } else if (strcasecmp($header['name'], 'location') === 0) {
                                $response->setHeader($header['name'], $header['value']);
                            }
                        }

                        // apply dynamic block updates
                        $body = $cachedResponse->getBody();

                        // Matches and updates block as needed.
                        if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_ENABLE_BLOCK_UPDATES)) {

                            Varien_Profiler::start('Brim_PageCache::blockupdate');

                            if (($_blockUpdateData = $cachedStorage->getBlockUpdateData()) != null) {
                                $this->setBlockUpdateData($_blockUpdateData);
                            }

                            $newBody = preg_replace_callback(
                                '/\<!\-\- BRIM_FPC ([\w\d\=\+\.\_\-]*) ([\w\d\=\+]*) \-\-\>(.*)\<!\-\- \/BRIM_FPC \1 \-\-\>/si',
                                'Brim_PageCache_Model_Engine::applyDynamicBlockUpdates',
                                $body
                            );

                            // Double check the new proccessed body content to ensure no fatal errors occurred in applyDynamicBlockUpdates
                            if ($newBody !== null) { $body  = $newBody; }

                            Varien_Profiler::stop('Brim_PageCache::blockupdate');
                        }

                        $response->setBody($body);
                        $response->sendResponse();

                        // Dispatch post dispatch event which is used for
                        // logging requests, visitors etc.
                        try {
                            $mockControllerAction = new Varien_Object(array(
                                'request' => Mage::app()->getRequest()
                            ));
                            Mage::dispatchEvent(
                                'controller_action_postdispatch',
                                array('controller_action' => $mockControllerAction)
                            );
                        } catch(Exception $postEventException) {
                            Mage::logException($postEventException);
                        }

                        Varien_Profiler::stop('Brim_PageCache::servepage');

                        exit;
                    } else {
                        // failed conditions
                        $response->setHeader(
                            self::RESPONSE_HEADER_MISS,
                            $this->_failed_conditions
                        );
                    }
                } else {
                    $response->setHeader(
                        self::RESPONSE_HEADER_MISS,
                        'invalid_object'
                    );
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        $response = Mage::app()->getResponse();
        $response->setHeader(self::RESPONSE_HEADER, self::CACHE_MISS);

        Varien_Profiler::stop('Brim_PageCache::servepage');
    }

    /**
     * Generates a cache id for requests.
     *
     * @param Zend_Controller_Request_Http $request
     * @return string
     */
    public function generateFPCId($request=null) {
        if ($request == null) {
            $request= Mage::app()->getRequest()->getOriginalRequest();
        }

        if ($this->_fpcCacheId == null) {
            $this->_fpcCacheId = 'BRIM_FPC_'
                . Mage::app()->getStore()->getCode() . '_'
                . Mage::getDesign()->getPackageName() . '_'
                . Mage::getDesign()->getTheme('layout') . '_'
                . Mage::app()->getLocale()->getLocaleCode() . '_'
                . Mage::app()->getStore()->getCurrentCurrencyCode() . '_'
                // Separate out the cache by customer group.
                // Helps with Logged in and out users for things like account links
                . Mage::getSingleton('customer/session')->getCustomerGroupId() . '_'
                // using sha1 hash to help limit the key size
                . sha1(
                    $request->getRequestUri() . '_'
                    . $request->getHttpHost() . '_'
                    . $request->getScheme()
                )
            ;
            $this->debug('Generated Id : '. $this->_fpcCacheId);
        }


        return $this->_fpcCacheId;
    }

    /**
     * @param string $conditionsToCheck
     * @return bool
     */
    public function passesConditions($conditionsToCheck = 'all') {

        if ($this->_initConditions === false) {
            if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_ENABLE_BLOCK_UPDATES) == 0) {
                // Extra checks are needed when block updates are off
                $this->_conditions['logged_out']  = !Mage::getSingleton('customer/session')->isLoggedIn();
                $this->_conditions['empty_cart']  = !Mage::getSingleton('checkout/session')->getQuote()->hasItems();
            }

            // TODO : Scan the raw session for messages.
            $messageTotal = Mage::getSingleton('core/session')->getMessages()->count()
                + Mage::getSingleton('checkout/session')->getMessages()->count()
                + Mage::getSingleton('customer/session')->getMessages()->count()
                + Mage::getSingleton('catalog/session')->getMessages()->count();
            $this->_conditions['no_messages'] = ($messageTotal == 0);

            $this->_initConditions = true;
        }

        if ($conditionsToCheck == 'all' || $conditionsToCheck == '') {
            $conditionsToCheck = array_keys($this->_conditions);
        }

        if(is_string($conditionsToCheck)) {
            $conditionsToCheck = explode(',', $conditionsToCheck);
            foreach ($conditionsToCheck as $key => $text) {
                $conditionsToCheck[$key] = trim($text);
            }
        };

        $failed = array();
        foreach ($conditionsToCheck as $conditionName) {
            if ($this->_conditions[$conditionName] == false) {
                $failed[] = $conditionName;
            }
        }

        if (count($failed) >0){
            $this->_failed_conditions = join(',', $failed);
            return false;
        }

        return true;
    }

    /**
     * Calls methods to perform actions as needed.
     *
     *  Ex: Brim_PageCache_Model_Container_Recentlyviewed::addProductViewed - records
     *      products viewed to block updates.
     *
     * @return bool
     */
    public function processFPCActions() {
        $config     = Mage::app()->getConfig();

        $request    = Mage::app()->getRequest();

        $actionKey  = $request->getModuleName() . '_'
                . $request->getControllerName() . '_'
                . $request->getActionName();

        if (($actions = $config->getNode('frontend/brim_pagecache/actions/' . $actionKey)) != null) {
            $params = new Varien_Object($request->getParams());
            foreach ($actions->children() as $key => $action) {
                $class = (string)$action->class;
                $method = (string)$action->method;
                // using call_user_func for pre PHP 5.3 compat
                call_user_func("$class::$method", $params);
            }
        }

        return true;
    }

    /**
     * Callback for dynamic block updates.  Return the new content from the container.
     *
     * @static
     * @param $match
     * @return string
     */
    static public function applyDynamicBlockUpdates($match) {
        $originalWrapper= $match[0];
        $blockName      = $match[1];
        $blockUpdateKey = $match[2];
        $originalContent= $newContent = $match[3];

        $engine         = Mage::getSingleton('brim_pagecache/engine');

        try {
            if (($args = $engine->getBlockUpdateData($blockUpdateKey)) !== false) {
                $engine->debug("Dynamic block update container : {$args['container']}");

                $model  = Mage::getModel($args['container'], $args);

                if ($model->blockRequiresUpdate()) {
                    $newContent = "<!-- BRIM_FPC {$args['name']} {$blockUpdateKey} -->\n"
                        .  $model->renderBlock() . "\n"
                        . "<!-- /BRIM_FPC {$args['name']} -->";
                }
            } else {
                $engine->debug("Block update key not found : $blockUpdateKey");
            }
        } catch (Exception $e) {
            $engine->debug($e->__toString());
        }

        return $newContent;
    }

    /**
     * Wrapper for Mage::log.  Only log if brim page cache debug setting is enabled.
     *
     * @param $message
     * @return void
     */
    public function debug() {
        if (Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_DEBUG)) {
            $messages = func_get_args();
            foreach ($messages as $message) {
                Mage::log($message, Zend_Log::DEBUG, self::DEBUG_LOG, true);
            }
        }
    }

    /**
     * debug messages for developers
     *
     * @param $message
     */
    public function devDebug() {
        if (Mage::getIsDeveloperMode())  {
            $args = func_get_args(); // function can not be used as a param prior to 5.3.0
            call_user_func_array(array($this, 'debug'), $args);
        }
    }

    /**
     * Checks if Brim's FPC debug mode is turned on.
     * @return mixed
     */
    public function isDebug() {
        return Mage::getStoreConfig(Brim_PageCache_Model_Config::XML_PATH_DEBUG);
    }


    /**
     * Mark content with a dynamic wrapper.
     *
     * @param $containerArgs
     * @param $html
     * @return string
     */
    public function markContent($containerArgs, $html) {
        $html = "<!-- BRIM_FPC {$containerArgs['name']} {$this->registerBlockUpdateData($containerArgs)} -->\n"
            .  $html . "\n"
            . "<!-- /BRIM_FPC {$containerArgs['name']} -->";

        return $html;
    }

    /**
     * Mark content via the event transport mechanism. Magento 1.4.1+ 
     *
     * @param $containerArgs array
     * @param $transport Varien_Object
     * @return void
     */
    public function markContentViaTransport($containerArgs, Varien_Object $transport) {
        $transport->setHtml(
            "<!-- BRIM_FPC {$containerArgs['name']} {$this->registerBlockUpdateData($containerArgs)} -->\n"
            .  $transport->getHtml() . "\n"
            . "<!-- /BRIM_FPC {$containerArgs['name']} -->"
        );
    }

    /**
     * Mark content via the blocks frame tags. Magento 1.4.0.0
     *
     * @param $containerArgs array
     * @param $block Mage_Core_Block_Abstract
     * @return void
     */
    public function markContentViaFrameTags($containerArgs, Mage_Core_Block_Abstract $block) {
        $openTag = "!-- BRIM_FPC {$containerArgs['name']} {$this->registerBlockUpdateData($containerArgs)} --";
        $closeTag = "!-- /BRIM_FPC {$containerArgs['name']} --";
        $block->setFrameTags($openTag, $closeTag);
    }

    /**
     * Get block update data for use in the FPC storage object or for generating block updates.
     *
     * @param null $key
     * @return array|bool
     */
    public function getBlockUpdateData($key=null) {
        if ($key == null) {
            return $this->_blockUpdateData;
        }

        if (array_key_exists($key, $this->_blockUpdateData)) {
            return $this->_blockUpdateData[$key];
        }

        return false;
    }

    /**
     * Init the block update storage.
     *
     * @param $data
     */
    public function setBlockUpdateData($data) {
        $this->_blockUpdateData = $data;
    }

    /**
     * Register block update data with the FPC storage object.
     *
     * @param $value
     * @return string Key value to look up block update data
     */
    public function registerBlockUpdateData($value) {

        if (array_key_exists('name', $value)) {
            $key = ($value['name'] . '-' . microtime(true));
        } else {
            $key = ((is_string($value)) ? $value : serialize($value));
        }
        $key = md5($key);

        $this->_blockUpdateData[$key] = $value;

        return $key;
    }
}