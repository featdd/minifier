<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] = \Featdd\Minifier\Hook\ClearCachePostProcHook::class . '->main';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] = \Featdd\Minifier\Hook\ContentPostProcAllHook::class . '->main';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess'][] = \Featdd\Minifier\Hook\RenderPreProcessHook::class . '->main';

require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'vendor/autoload.php');
