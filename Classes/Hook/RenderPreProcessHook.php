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
     * @var \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * @return \Featdd\Minifier\Hook\RenderPreProcessHook
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
     * @return void
     */
    public function main(array &$params, PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;

        if ('FE' === TYPO3_MODE) {
            $paramFiles = array(
                &$params['cssFiles'],
                &$params['jsFiles'],
                &$params['jsLibs'],
            );

            if (true === is_array($params['cssLibs'])) {
                $paramFiles[] = &$params['cssLibs'];
            }

            if (true === (boolean) $this->extConf['concatenate']) {
                $this->replaceAssetsConcatinated($paramFiles);
            } else {
                $this->replaceAssets($paramFiles);
            }
        }
    }

    /**
     * @param array $fileArrays
     * @return void
     */
    protected function replaceAssets(array &$fileArrays)
    {
        foreach ($fileArrays as &$files) {
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

                if (false === file_exists(PATH_site . $minifiedFilePath)) {
                    file_put_contents(PATH_site . $minifiedFilePath, MinifierService::minifyFile($file['file']));
                }

                if ($key !== $file['file']) {
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
    }

    /**
     * @param array $fileArrays
     * @return void
     */
    protected function replaceAssetsConcatinated(array &$fileArrays)
    {
        $css = array();
        $js = array();
        $jsforceOnTop = array();

        $minifiedFilePath = 'typo3temp/' . self::ASSET_PREFIX . md5(json_encode($fileArrays)) . '.';
        $minifiedFilePathforceOnTop = 'typo3temp/' . self::ASSET_PREFIX . md5(json_encode($fileArrays) . 'forceOnTop') . '.';

        foreach ($fileArrays as &$files) {
            foreach ($files as $key => $file) {
                $fileExtension = pathinfo($file['file'], PATHINFO_EXTENSION);

                if (
                    false === in_array($fileExtension, array('js', 'css', 'scss', 'sass')) ||
                    false === (boolean) $this->extConf['minifyCDN'] &&
                    (
                        null !== parse_url($file['file'], PHP_URL_SCHEME) ||
                        '//' === substr($file['file'], 0, 2)
                    )
                ) {
                    continue;
                }

                if ('scss' === $fileExtension || 'sass' === $fileExtension) {
                    $fileExtension = 'css';
                }

                if (false === file_exists(PATH_site . (true === $file['forceOnTop'] ? $minifiedFilePathforceOnTop : $minifiedFilePath) . $fileExtension)) {
                    $minifiedFile = MinifierService::minifyFile($file['file']);

                    if ('css' === $fileExtension) {
                        $css[] = $minifiedFile;
                    } elseif (true === $file['forceOnTop']) {
                        $jsforceOnTop[] = $minifiedFile;
                    } else {
                        $js[] = $minifiedFile;
                    }
                }

                unset($files[$key]);
            }
        }

        if (
            0 < count($css) &&
            false === file_exists(PATH_site . $minifiedFilePath . 'css')
        ) {
            file_put_contents(PATH_site . $minifiedFilePath . 'css', implode(PHP_EOL, $css));
        }

        if (true === file_exists(PATH_site . $minifiedFilePath . 'css')) {
            $this->pageRenderer->addCssFile($minifiedFilePath . 'css');
        }

        if (
            0 < count($js) &&
            false === file_exists(PATH_site . $minifiedFilePath . 'js')
        ) {
            file_put_contents(PATH_site . $minifiedFilePath . 'js', implode(PHP_EOL, $js));
        }

        if (true === file_exists(PATH_site . $minifiedFilePath . 'js')) {
            $this->pageRenderer->addJsFooterFile($minifiedFilePath . 'js');
        }

        if (
            0 < count($jsforceOnTop) &&
            false === file_exists(PATH_site . $minifiedFilePathforceOnTop . 'js')
        ) {
            file_put_contents(PATH_site . $minifiedFilePathforceOnTop . 'js', implode(PHP_EOL, $jsforceOnTop));
        }

        if (true === file_exists(PATH_site . $minifiedFilePathforceOnTop . 'js')) {
            $this->pageRenderer->addJsFile($minifiedFilePathforceOnTop . 'js');
        }
    }
}
