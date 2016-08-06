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

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ClearCachePostProcHook
{
    /**
     * @param array       $params
     * @param DataHandler $dataHandler
     */
    public function main(array &$params, DataHandler $dataHandler)
    {
        if ('pages' === $params['cacheCmd'] || 'all' === $params['cacheCmd']) {
            $cacheFiles = glob(GeneralUtility::getFileAbsFileName('typo3temp/' . RenderPreProcessHook::ASSET_PREFIX . '*'));

            if (false !== $cacheFiles) {
                foreach ($cacheFiles as $cacheFile) {
                    unlink($cacheFile);
                }
            }
        }
    }
}
