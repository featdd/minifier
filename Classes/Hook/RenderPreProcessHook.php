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
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RenderPreProcessHook
{
    const ASSET_PREFIX = 'minifier-';
    const INTEGRITY_ALGORITHM = 'sha384';

    /**
     * @var \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var array
     */
    protected $extConf = array(
        'disableMinifier' => false,
        'minifyCDN' => false,
        'concatenate' => false,
        'integrityHash' => false,
    );

    /**
     * @return \Featdd\Minifier\Hook\RenderPreProcessHook
     */
    public function __construct()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['minifier']);

        if (is_array($extConf)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->extConf, $extConf);
        }
    }

    /**
     * @param array $params
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
     * @param string $relativeFilePath
     * @param string $integrityHash
     * @return string
     */
    protected function renderJavaScriptTag($relativeFilePath, $integrityHash = null)
    {
        return '<script' .
        ' src="' . $relativeFilePath . '"' .
        ' type="text/javascript"' .
        (false === empty($integrityHash) && true === (bool) $this->extConf['integrityHash'] ? ' integrity="' . self::INTEGRITY_ALGORITHM . '-' . $integrityHash . '"' : '') .
        '></script>';
    }

    /**
     * @param string $relativeFilePath
     * @param string $integrityHash
     * @return string
     */
    protected function renderStylesheetTag($relativeFilePath, $integrityHash = null)
    {
        return '<link' .
        ' rel="stylesheet"' .
        ' type="text/css"' .
        ' href="' . $relativeFilePath . '"' .
        ' media="all"' .
        (false === empty($integrityHash) && true === (bool) $this->extConf['integrityHash'] ? ' integrity="' . self::INTEGRITY_ALGORITHM . '-' . $integrityHash . '"' : '') .
        '>';
    }

    /**
     * @param string $relativeFilePath
     * @return string
     */
    protected function getGeneratedFileIntegrityHash($relativeFilePath)
    {
        if (false === file_exists(PATH_site . $relativeFilePath . '.' . self::INTEGRITY_ALGORITHM)) {
            return null;
        } else {
            return base64_encode(
                file_get_contents(PATH_site . $relativeFilePath . '.' . self::INTEGRITY_ALGORITHM)
            );
        }
    }

    /**
     * @param string $relativeFilePath
     * @return void
     */
    protected function generateHashFile($relativeFilePath)
    {
        if (false === file_exists(PATH_site . $relativeFilePath . '.' . self::INTEGRITY_ALGORITHM)) {
            file_put_contents(
                PATH_site . $relativeFilePath . '.' . self::INTEGRITY_ALGORITHM,
                hash_file(self::INTEGRITY_ALGORITHM, PATH_site . $relativeFilePath, true)
            );
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

                    if ($this->extConf['integrityHash']) {
                        $this->generateHashFile($minifiedFilePath);
                    }
                }

                if (
                    true === $file['forceOnTop'] ||
                    'css' === $fileExtension
                ) {
                    if ('css' === $fileExtension) {
                        $this->pageRenderer->addHeaderData(
                            $this->renderStylesheetTag(
                                $minifiedFilePath,
                                $this->getGeneratedFileIntegrityHash($minifiedFilePath)
                            )
                        );
                    } else {
                        $this->pageRenderer->addHeaderData(
                            $this->renderJavaScriptTag(
                                $minifiedFilePath,
                                $this->getGeneratedFileIntegrityHash($minifiedFilePath)
                            )
                        );
                    }
                } else {
                    $this->pageRenderer->addFooterData(
                        $this->renderJavaScriptTag(
                            $minifiedFilePath,
                            $this->getGeneratedFileIntegrityHash($minifiedFilePath)
                        )
                    );
                }

                unset($files[$key]);
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
                    false === in_array($fileExtension, array('js', 'css', 'scss')) ||
                    false === (boolean) $this->extConf['minifyCDN'] &&
                    (
                        null !== parse_url($file['file'], PHP_URL_SCHEME) ||
                        '//' === substr($file['file'], 0, 2)
                    )
                ) {
                    continue;
                }

                if ('scss' === $fileExtension) {
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

        if ($this->extConf['integrityHash']) {
            $this->generateHashFile($minifiedFilePath . 'css');
        }

        if (true === file_exists(PATH_site . $minifiedFilePath . 'css')) {
            $this->pageRenderer->addHeaderData(
                $this->renderStylesheetTag(
                    $minifiedFilePath . 'css',
                    $this->getGeneratedFileIntegrityHash($minifiedFilePath . 'css')
                )
            );
        }

        if (
            0 < count($js) &&
            false === file_exists(PATH_site . $minifiedFilePath . 'js')
        ) {
            file_put_contents(PATH_site . $minifiedFilePath . 'js', implode(PHP_EOL, $js));
        }

        if ($this->extConf['integrityHash']) {
            $this->generateHashFile($minifiedFilePath . 'js');
        }

        if (true === file_exists(PATH_site . $minifiedFilePath . 'js')) {
            $this->pageRenderer->addFooterData(
                $this->renderJavaScriptTag(
                    $minifiedFilePath . 'js',
                    $this->getGeneratedFileIntegrityHash($minifiedFilePath . 'js')
                )
            );
        }

        if (
            0 < count($jsforceOnTop) &&
            false === file_exists(PATH_site . $minifiedFilePathforceOnTop . 'js')
        ) {
            file_put_contents(PATH_site . $minifiedFilePathforceOnTop . 'js', implode(PHP_EOL, $jsforceOnTop));
        }

        if ($this->extConf['integrityHash']) {
            $this->generateHashFile($minifiedFilePathforceOnTop . 'js');
        }

        if (true === file_exists(PATH_site . $minifiedFilePathforceOnTop . 'js')) {
            $this->pageRenderer->addHeaderData(
                $this->renderJavaScriptTag(
                    $minifiedFilePathforceOnTop . 'js',
                    $this->getGeneratedFileIntegrityHash($minifiedFilePathforceOnTop . 'js')
                )
            );
        }
    }
}
