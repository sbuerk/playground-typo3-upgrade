<?php

declare(strict_types=1);

/**
 * WORKSHOP: this TCA is deliberately written in pre-v12 style.
 * TYPO3 silently auto-migrates it on every request - see Lab 03.
 */
return [
    'ctrl' => [
        'title' => 'LLL:EXT:flight_ops/Resources/Private/Language/locallang_db.xlf:departure',
        'label' => 'flight_number',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
    ],
    'columns' => [
        'flight_number' => [
            'label' => 'Flight number',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'trim,required',
            ],
        ],
        'destination' => [
            'label' => 'Destination',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'crew_mail' => [
            'label' => 'Crew mail',
            'config' => [
                'type' => 'input',
                'eval' => 'trim,email',
            ],
        ],
        'seats' => [
            'label' => 'Seats',
            'config' => [
                'type' => 'input',
                'eval' => 'int',
            ],
        ],
        'scheduled' => [
            'label' => 'Scheduled',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ],
        ],
        'booking_link' => [
            'label' => 'Booking link',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
            ],
        ],
        'livery_color' => [
            'label' => 'Livery colour',
            'config' => [
                'type' => 'input',
                'renderType' => 'colorpicker',
            ],
        ],
        'status' => [
            'label' => 'Status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['On time', 'ontime'],
                    ['Delayed', 'delayed'],
                    ['Cancelled', 'cancelled'],
                ],
            ],
        ],
        'notes' => [
            'label' => 'Notes',
            'config' => [
                'type' => 'text',
                'renderType' => 't3editor',
                'format' => 'html',
            ],
        ],
        'spacer' => [
            'label' => 'Spacer',
            'config' => [
                'type' => 'none',
                'cols' => 20,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'flight_number, destination, crew_mail, seats, scheduled, booking_link, livery_color, status, notes, spacer'],
    ],
];
