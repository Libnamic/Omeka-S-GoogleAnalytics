<?php
namespace GoogleAnalytics;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
],
'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'googleanalytics' => [
        'config' => [
            'googleanalytics'=>[
                'googleanalytics_code'=>''
            ],
            'additional_snippet'=>[
                'additional_snippet'=>''
            ],
        ],
    ],
];
