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
    /**
     * @param string $filePath
     * @return string
     */
    public static function minifyFile($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        // remove slash on path beginning
        if ('//' !== substr($filePath, 0, 2) && '/' === substr($filePath, 0, 1)) {
            $filePath = substr($filePath, 1);
        }

        $urlSchema = parse_url($filePath, PHP_URL_SCHEME);

        if (null !== $urlSchema) {
            $fullFilePath = $filePath;
        } elseif ('//' === substr($filePath, 0, 2)) {
            $fullFilePath = 'https:' . $filePath;
        } else {
            $fullFilePath = PATH_site . $filePath;
        }

        if (false === file_exists($fullFilePath)) {
            return false;
        }

        switch ($fileExtension) {
            case 'js':
                return self::minifyJS(file_get_contents($fullFilePath));
            case 'css':
                return self::minifyCSS(file_get_contents($fullFilePath));
            case 'scss':
                $compiler = new Compiler();
                $compiler->setFormatter(Compressed::class);
                $compiler->addImportPath(pathinfo(PATH_site . $filePath, PATHINFO_DIRNAME));
                return $compiler->compile(file_get_contents(PATH_site . $filePath));
            default:
                return false;
        }
    }

    /**
     * @param string $data
     * @return string
     */
    public static function minifyJS($data)
    {
        $minifier = new Minify\JS();
        $minifier->add($data);

        return $minifier->minify();
    }

    /**
     * @param string $data
     * @return string
     */
    public static function minifyCSS($data)
    {
        $minifier = new Minify\CSS();
        $minifier->add($data);

        return $minifier->minify();
    }

    /**
     * @param string $html
     */
    public static function minifyHTML(&$html)
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
