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

require_once 'abstract.php';

class Brim_PageCache_Shell extends Mage_Shell_Abstract
{
    public function run() {

        $cache      = Mage::app()->getCache();

        if ($this->getArg('clean')) {
            if (($tags = $this->getArg('tags'))) {
                $tagsArray = explode(',', $tags);
                $result = $cache->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tagsArray);

                echo "\n -- Clean Tags Completed \n\n";
            } else {
                echo $this->usageHelp();
            }
        } else {
            echo $this->usageHelp();
        }
    }

    public  function usageHelp() {
        return <<<USAGE
Usage:  php -f brim-fpc.php -- [options]

  clean --tags <tags>           Clean cache by tags
  help                          This help

  <tags>                        csv list of cache tags, ex: BRIM_FPC_PRODUCT_1


USAGE;

    }
}

$shell = new Brim_PageCache_Shell();
$shell->run();
