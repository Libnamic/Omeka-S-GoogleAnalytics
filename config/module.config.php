<?php
namespace GoogleAnalytics;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'googleanalytics' => [
        'config' => [
            'googleanalytics'=>[
                'googleanalytics_code'=>''
            ],
        ],
    ],
];
