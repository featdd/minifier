<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Minifier',
    'description' => 'Automatic HTML,CSS & JS minifier',
    'category' => 'plugin',
    'author' => 'Daniel Dorndorf',
    'author_email' => 'dorndorf@featdd.de',
    'author_company' => '',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 1,
    'lockType' => '',
    'version' => '0.9.6',
    'constraints' => array(
        'depends' => array(
            'extbase' => '6.2.0',
            'fluid' => '6.2.0',
            'typo3' => '6.2.0-7.6.99',
            'php' => '5.4.0',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
);
