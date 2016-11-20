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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RenderPreProcessHook
{
    const ASSET_FOLDER_ABSOLUTE = PATH_site . 'typo3temp/tx_minifier/';
    const ASSET_FOLDER = 'typo3temp/tx_minifier/';
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
        'disableMinifierInDevelopment' => false,
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

        if (
            'FE' === TYPO3_MODE &&
            false === (bool) $this->extConf['disableMinifier'] &&
            false === (
                true === (bool) $this->extConf['disableMinifierInDevelopment'] &&
                true === GeneralUtility::getApplicationContext()->isDevelopment()
            )
        ) {
            if (false === is_dir(self::ASSET_FOLDER_ABSOLUTE)) {
                mkdir(self::ASSET_FOLDER_ABSOLUTE, 0755);
            }

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
     * @param string $filename
     * @param string $integrityHash
     * @return string
     */
    protected function renderJavaScriptTag($filename, $integrityHash = null)
    {
        return '<script' .
        ' src="' . self::ASSET_FOLDER . $filename . '"' .
        ' type="text/javascript"' .
        (false === empty($integrityHash) && true === (bool) $this->extConf['integrityHash'] ? ' integrity="' . self::INTEGRITY_ALGORITHM . '-' . $integrityHash . '"' : '') .
        '></script>';
    }

    /**
     * @param string $filename
     * @param string $integrityHash
     * @return string
     */
    protected function renderStylesheetTag($filename, $integrityHash = null)
    {
        return '<link' .
        ' rel="stylesheet"' .
        ' type="text/css"' .
        ' href="' . self::ASSET_FOLDER . $filename . '"' .
        ' media="all"' .
        (false === empty($integrityHash) && true === (bool) $this->extConf['integrityHash'] ? ' integrity="' . self::INTEGRITY_ALGORITHM . '-' . $integrityHash . '"' : '') .
        '>';
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function getGeneratedFileIntegrityHash($filename)
    {
        if (false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $filename . '.' . self::INTEGRITY_ALGORITHM)) {
            return null;
        } else {
            return base64_encode(
                file_get_contents(self::ASSET_FOLDER_ABSOLUTE . $filename . '.' . self::INTEGRITY_ALGORITHM)
            );
        }
    }

    /**
     * @param string $filename
     * @return void
     */
    protected function generateHashFile($filename)
    {
        if (false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $filename . '.' . self::INTEGRITY_ALGORITHM)) {
            file_put_contents(
                self::ASSET_FOLDER_ABSOLUTE . $filename . '.' . self::INTEGRITY_ALGORITHM,
                hash_file(self::INTEGRITY_ALGORITHM, self::ASSET_FOLDER_ABSOLUTE . $filename, true)
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
                $minifiedFilename = self::ASSET_PREFIX . md5($file['file']) . '.' . $fileExtension;

                if (false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename)) {
                    file_put_contents(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename, MinifierService::minifyFile($file['file']));

                    if ($this->extConf['integrityHash']) {
                        $this->generateHashFile($minifiedFilename);
                    }
                }

                if (
                    true === $file['forceOnTop'] ||
                    'css' === $fileExtension
                ) {
                    if ('css' === $fileExtension) {
                        $this->pageRenderer->addHeaderData(
                            $this->renderStylesheetTag(
                                $minifiedFilename,
                                $this->getGeneratedFileIntegrityHash($minifiedFilename)
                            )
                        );
                    } else {
                        $this->pageRenderer->addHeaderData(
                            $this->renderJavaScriptTag(
                                $minifiedFilename,
                                $this->getGeneratedFileIntegrityHash($minifiedFilename)
                            )
                        );
                    }
                } else {
                    $this->pageRenderer->addFooterData(
                        $this->renderJavaScriptTag(
                            $minifiedFilename,
                            $this->getGeneratedFileIntegrityHash($minifiedFilename)
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

        $minifiedFilename = self::ASSET_PREFIX . md5(json_encode($fileArrays)) . '.';
        $minifiedFilenameForceOnTop = self::ASSET_PREFIX . md5(json_encode($fileArrays) . 'forceOnTop') . '.';

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

                if (false === file_exists(self::ASSET_FOLDER_ABSOLUTE . (true === $file['forceOnTop'] ? $minifiedFilenameForceOnTop : $minifiedFilename) . $fileExtension)) {
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
            false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'css')
        ) {
            file_put_contents(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'css', implode(PHP_EOL, $css));

            if ($this->extConf['integrityHash']) {
                $this->generateHashFile($minifiedFilename . 'css');
            }
        }

        if (true === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'css')) {
            $this->pageRenderer->addHeaderData(
                $this->renderStylesheetTag(
                    $minifiedFilename . 'css',
                    $this->getGeneratedFileIntegrityHash($minifiedFilename . 'css')
                )
            );
        }

        if (
            0 < count($js) &&
            false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'js')
        ) {
            file_put_contents(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'js', implode(PHP_EOL, $js));

            if ($this->extConf['integrityHash']) {
                $this->generateHashFile($minifiedFilename . 'js');
            }
        }

        if (true === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilename . 'js')) {
            $this->pageRenderer->addFooterData(
                $this->renderJavaScriptTag(
                    $minifiedFilename . 'js',
                    $this->getGeneratedFileIntegrityHash($minifiedFilename . 'js')
                )
            );
        }

        if (
            0 < count($jsforceOnTop) &&
            false === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilenameForceOnTop . 'js')
        ) {
            file_put_contents(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilenameForceOnTop . 'js', implode(PHP_EOL, $jsforceOnTop));

            if ($this->extConf['integrityHash']) {
                $this->generateHashFile($minifiedFilenameForceOnTop . 'js');
            }
        }

        if (true === file_exists(self::ASSET_FOLDER_ABSOLUTE . $minifiedFilenameForceOnTop . 'js')) {
            $this->pageRenderer->addHeaderData(
                $this->renderJavaScriptTag(
                    $minifiedFilenameForceOnTop . 'js',
                    $this->getGeneratedFileIntegrityHash($minifiedFilenameForceOnTop . 'js')
                )
            );
        }
    }
}
