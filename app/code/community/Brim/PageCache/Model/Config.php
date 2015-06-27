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

class Brim_PageCache_Model_Config {

    const XML_PATH_ENABLED                  = 'brim_pagecache/settings/enabled';

    const XML_PATH_ENABLE_BLOCK_UPDATES     = 'brim_pagecache/settings/enable_block_updates';

    const XML_PATH_CACHE_BLOCK_UPDATES      = 'brim_pagecache/settings/cache_block_updates';

    const XML_PATH_ENABLE_MINIFY_HTML       = 'brim_pagecache/settings/enable_minify_html';

    const XML_PATH_EXPIRES                  = 'brim_pagecache/settings/expires';

    const XML_PATH_INVALIDATE               = 'brim_pagecache/settings/invalidate_clean';

    const XML_PATH_DEBUG                    = 'brim_pagecache/settings/debug';

    const XML_PATH_DEBUG_RESPONSE           = 'brim_pagecache/settings/debug_response';



    const INVALIDATE_FLAG                   = 0;
    const FORCE_CLEAN_FLAG                  = 1;

}