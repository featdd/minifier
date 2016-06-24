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
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RenderPreProcessHook
{
    const KEY_ORIGINAL = 'original';
    const KEY_PATH = 'path';
    const ASSET_PREFIX = 'minifier-';

    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * @return RenderPreProcessHook
     */
    public function __construct()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['minifier']);

        if (is_array($extConf)) {
            $this->extConf = $extConf;
        }
    }

    /**
     * @param array        $params
     * @param PageRenderer $pageRenderer
     */
    public function main(array &$params, PageRenderer $pageRenderer)
    {
        if ('FE' === TYPO3_MODE) {
            if (true === (boolean) $this->extConf['concatenate']) {
                $this->replaceAssetsConcatinated($params['cssFiles'], 'css', self::KEY_PATH);
                $this->replaceAssetsConcatinated($params['jsFiles'], 'js', PageRenderer::PART_FOOTER, self::KEY_PATH);
                $this->replaceAssetsConcatinated($params['jsLibs'], 'js', PageRenderer::PART_HEADER,
                    self::KEY_ORIGINAL);

                if (true === is_array($params['cssLibs'])) {
                    $this->replaceAssetsConcatinated($params['cssLibs'], 'css', self::KEY_ORIGINAL);
                }
            } else {
                $this->replaceAssets($params['cssFiles'], self::KEY_PATH);
                $this->replaceAssets($params['jsFiles'], self::KEY_PATH);
                $this->replaceAssets($params['jsLibs'], self::KEY_ORIGINAL);

                if (true === is_array($params['cssLibs'])) {
                    $this->replaceAssets($params['cssLibs'], self::KEY_ORIGINAL);
                }
            }
        }
    }

    /**
     * @param array  $files
     * @param string $keyToUse
     */
    protected function replaceAssets(array &$files, $keyToUse = self::KEY_ORIGINAL)
    {
        foreach ($files as $key => $file) {
            if (
                false === (boolean) $this->extConf['minifyCDN'] &&
                (
                    null !== parse_url($file['file'], PHP_URL_SCHEME) ||
                    '//' === substr($file['file'], 0, 2)
                )
            ) {
                continue;
            }

            $fileExtension = pathinfo($file['file'], PATHINFO_EXTENSION);
            $fileExtension = 'scss' === $fileExtension ? 'css' : $fileExtension;
            $minifiedFilePath = 'typo3temp/' . self::ASSET_PREFIX . md5($file['file']) . '.' . $fileExtension;

            file_put_contents(PATH_site . $minifiedFilePath, MinifierService::minifyFile($file['file']));

            if ($keyToUse === self::KEY_ORIGINAL) {
                $file['file'] = $minifiedFilePath;
                $files[$key] = $file;
            } else {
                $newFile = $file;
                $newFile['file'] = $minifiedFilePath;
                $files[$minifiedFilePath] = $file;
                unset($files[$file['file']]);
            }
        }
    }

    /**
     * @param array   $files
     * @param string  $fileExtension
     * @param integer $section
     * @param string  $keyToUse
     */
    protected function replaceAssetsConcatinated(array &$files, $fileExtension, $section = PageRenderer::PART_HEADER, $keyToUse = self::KEY_ORIGINAL)
    {
        $concatinations = array();

        $minifiedFilePath = 'typo3temp/' . self::ASSET_PREFIX . md5(json_encode($files)) . '.' . $fileExtension;

        foreach ($files as $key => $file) {
            if (
                false === (boolean) $this->extConf['minifyCDN'] &&
                (
                    null !== parse_url($file['file'], PHP_URL_SCHEME) ||
                    '//' === substr($file['file'], 0, 2)
                )
            ) {
                continue;
            }

            if (false === file_exists(PATH_site . $minifiedFilePath)) {
                $concatinations[] = MinifierService::minifyFile($file['file']);
            }

            unset($files[$key]);
        }

        if (false === file_exists(PATH_site . $minifiedFilePath)) {
            if (0 === count($concatinations)) {
                return;
            }

            file_put_contents(PATH_site . $minifiedFilePath, implode(PHP_EOL, $concatinations));
        }

        if ($keyToUse === self::KEY_ORIGINAL) {
            $key = 'minifier-concatinated';
        } else {
            $key = $minifiedFilePath;
        }

        if ('css' === $fileExtension) {
            $files[$key] = array(
                'file' => $minifiedFilePath,
                'rel' => 'stylesheet',
                'media' => 'all',
                'title' => '',
                'compress' => true,
                'forceOnTop' => false,
                'allWrap' => '',
                'excludeFromConcatenation' => false,
                'splitChar' => '|',
            );
        } else {
            $files[$key] = array(
                'file' => $minifiedFilePath,
                'type' => 'text/javascript',
                'section' => $section,
                'compress' => true,
                'forceOnTop' => false,
                'allWrap' => '',
                'excludeFromConcatenation' => false,
                'splitChar' => '|',
                'async' => false,
                'integrity' => '',
            );
        }
    }
}
