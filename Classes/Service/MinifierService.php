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
     * @var array
     */
    protected $extConf = array();

    /**
     * @return MinifierService
     */
    public function __construct()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['minifier']);

        if (is_array($extConf)) {
            $this->extConf = $extConf;
        }
    }

    /**
     * @param string $html
     */
    public function compressPage(&$html)
    {
        $callback = function ($match) {
            $filePath = $match[2];
            $cleanFilePath = preg_replace('#\?.*#i', '', $filePath);
            $filePath = $this->minificator($cleanFilePath);

            return $match[1] . $filePath . $match[3];
        };

        $html = preg_replace_callback(
            '#(<link\s+(?:[^>]*?\s+)?href=")([^"]*)(")#i',
            $callback,
            $html
        );

        $html = preg_replace_callback(
            '#(<script\s+(?:[^>]*?\s+)?src=")([^"]*)(")#i',
            $callback,
            $html
        );

        $this->minifyHTML($html);
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
                    $urlSchema = parse_url($filePath, PHP_URL_SCHEME);

                    if (
                        true === (boolean) $this->extConf['dontMinifyCDN'] &&
                        (
                            null !== $urlSchema ||
                            '//' === substr($filePath, 0, 2)
                        )
                    ) {
                        return $filePath;
                    }

                    if (null !== $urlSchema) {
                        $fullFilePath = $filePath;
                    } elseif ('//' === substr($filePath, 0, 2)) {
                        $fullFilePath = 'https:' . $filePath;
                    } else {
                        $fullFilePath = $documentRoot . $filePath;
                    }

                    $minifier = 'css' === $fileExtension ?
                        new Minify\CSS() :
                        new Minify\JS();

                    $minifier->add(file_get_contents($fullFilePath));
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

    /**
     * @param string $html
     */
    protected function minifyHTML(&$html)
    {
        // Remove extra white-space(s) between HTML attribute(s)
        $html = preg_replace_callback(
            '#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s',
            function ($matches) {
                return '<' . $matches[1] . preg_replace(
                    '#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s',
                    ' $1$2',
                    $matches[2]
                ) . $matches[3] . '>';
            },
            str_replace("\r", "", $html)
        );

        // Minify inline CSS declaration(s)
        if (false !== strpos($html, ' style=')) {
            $minifier = new Minify\CSS();

            $html = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s',
                function ($matches) use ($minifier) {
                    $minifier->add($matches[3]);
                    return '<' . $matches[1] . ' style=' . $matches[2] . $minifier->minify() . $matches[2];
                },
                $html
            );
        }

        $html = preg_replace(
            array(
                // t = text
                // o = tag open
                // c = tag close
                // Keep important white-space(s) after self-closing HTML tag(s)
                '#<(img|input)(>| .*?>)#s',
                // Remove a line break and two or more white-space(s) between tag(s)
                '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
                // t+c || o+t
                '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s',
                // o+o || c+c
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s',
                // c+t || t+o || o+t -- separated by long white-space(s)
                '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s',
                // empty tag
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s',
                // reset previous fix
                '#<(img|input)(>| .*?>)<\/\1\x1A>#s',
                // clean up ...
                '#(&nbsp;)&nbsp;(?![<\s])#',
                // Force line-break with `&#10;` or `&#xa;`
                '#&\#(?:10|xa);#',
                // Force white-space with `&#32;` or `&#x20;`
                '#&\#(?:32|x20);#',
                // Remove HTML comment(s) except IE comment(s)
                '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s',
            ),
            array(
                "<$1$2</$1\x1A>",
                '$1$2$3',
                '$1$2$3',
                '$1$2$3$4$5',
                '$1$2$3$4$5$6$7',
                '$1$2$3',
                '<$1$2',
                '$1 ',
                "\n",
                ' ',
                "",
            ),
            $html
        );
    }
}
