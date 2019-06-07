<?php
namespace GoogleAnalytics\Form;

use Zend\Form\Element;
use Zend\Form\Form;

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

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'googleanalytics_code',
            'required' => false,
        ]);
    }
}
