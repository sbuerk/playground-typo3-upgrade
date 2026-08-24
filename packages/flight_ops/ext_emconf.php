<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Flight Operations',
    'description' => 'Flight operations planning for TYPO3',
    'category' => 'plugin',
    'author' => 'Stefan Bürk',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '2.4.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
        ],
    ],
];
