<?php
namespace Featdd\Minifier\Hook;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Daniel Dorndorf <dorndorf@featdd.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Featdd\Minifier\Service\MinifierService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ContentPostProcAllHook
{
    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * @return ContentPostProcAllHook
     */
    public function __construct()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['minifier']);

        if (is_array($extConf)) {
            $this->extConf = $extConf;
        }
    }

    /**
     * @param array                        $params
     * @param TypoScriptFrontendController $typoScriptFrontendController
     */
    public function main(array &$params, TypoScriptFrontendController $typoScriptFrontendController)
    {
        if (false === (boolean) $this->extConf['disableMinifier']) {
            MinifierService::minifyHTML($typoScriptFrontendController->content);
        }
    }
}
