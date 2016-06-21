<?php
namespace Featdd\Minifier\Service;

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

use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Formatter\Compressed;
use MatthiasMullie\Minify;
use TYPO3\CMS\Core\SingletonInterface;

/**
 *
 * @package minifier
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class MinifierService implements SingletonInterface
{
    const ASSET_PREFIX = 'minifier-';

    /**
     * @param string $page
     */
    public function compressPage(&$page)
    {
        $callback = function ($match) {
            $filePath = $match[2];
            $cleanFilePath = preg_replace('#\?.*#i', '', $filePath);
            $filePath = $this->minificator($cleanFilePath);

            return $match[1] . $filePath . $match[3];
        };

        $page = preg_replace_callback(
            '#(<link\s+(?:[^>]*?\s+)?href=")([^"]*)(")#i',
            $callback,
            $page
        );

        $page = preg_replace_callback(
            '#(<script\s+(?:[^>]*?\s+)?src=")([^"]*)(")#i',
            $callback,
            $page
        );
    }

    /**
     * @param string $filePath
     * @return string
     */
    protected function minificator($filePath)
    {
        $documentRoot = substr(PATH_site, 0, strlen(PATH_site) - 1);
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch ($fileExtension) {
            case 'js':
            case 'css':
                $minifiedFilePath = '/typo3temp/' . self::ASSET_PREFIX . md5($filePath) . '.' . $fileExtension;

                if (false === file_exists($documentRoot . $minifiedFilePath)) {
                    $minifier = 'css' === $fileExtension ?
                        new Minify\CSS($documentRoot . $filePath) :
                        new Minify\JS($documentRoot . $filePath);
                    $minifier->minify($documentRoot . $minifiedFilePath);
                }

                break;
            case 'scss':
                $minifiedFilePath = '/typo3temp/' . self::ASSET_PREFIX . md5($filePath) . '.css';

                if (false === file_exists($documentRoot . $minifiedFilePath)) {
                    $compiler = new Compiler();
                    $compiler->setFormatter(Compressed::class);
                    $compiler->addImportPath(pathinfo($documentRoot . $filePath, PATHINFO_DIRNAME));

                    file_put_contents(
                        $documentRoot . $minifiedFilePath,
                        $compiler->compile(file_get_contents($documentRoot . $filePath))
                    );
                }

                break;
            default:
                return $filePath;
        }

        return $minifiedFilePath;
    }
}
