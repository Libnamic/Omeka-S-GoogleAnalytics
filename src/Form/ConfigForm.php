<?php
namespace GoogleAnalytics\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'googleanalytics_code',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Google Analytics code', // @translate
                'info' => 'Google Analytics tracking ID for all sites, unless otherwise specified in site settings', // @translate
            ],
            'attributes' => [
                'id' => 'googleanalytics',
            ],
        ]);

        // $this->add([
        //     'name' => 'additional_snippet',
        //     'type' => Element\Textarea::class,
        //     'options' => [
        //         'label' => 'Additional snippet', // @translate
        //         'info' => 'Snippet to insert in all sites, unless otherwise specified in site settings. Can be used for tracking and analytics systems other than Google Analytics', // @translate
        //     ],
        //     'attributes' => [
        //         'id' => 'additional_snippet',
        //     ],
        // ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'googleanalytics_code',
            'required' => false,
        ]);
        // $inputFilter->add([
        //     'name' => 'additional_snippet',
        //     'required' => false,
        // ]);
    }
}
