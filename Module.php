<?php
/**
 * GoogleAnalytics
 *
 * Includes simple support for Google Analytics in Omeka S
 *
 * @copyright Jesús Bocanegra Linares, Libnamic, 2020
 * @license MIT License
 *
 * This software is governed by the MIT License, included with the source code.
 */
namespace GoogleAnalytics;

use Zend\Form\Fieldset;
use Zend\Form\Text;
use Omeka\Module\AbstractModule;
use GoogleAnalytics\Form\ConfigForm;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');

        // Delete site settings
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites', [])->getContent();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');

        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $siteSettings->delete('googleanalytics_code');
        }
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                     $settings->delete($name);
                    break;
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Insert Google Analytics tracking code
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'printScript']
        );
    
        // Site settings
        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_elements',
            [$this, 'addSiteSettings']
        );
        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_input_filters',
            [$this, 'addSiteSettingsFilters']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = $settings->get('googleanalytics', ['']);

        
        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        $html .= '<h4><a target="blank" href="https://support.google.com/analytics/answer/1008080#trackingID">Where can I find my tracking code?</a></h4>';
        $html .= '<p style="margin: 2em 0 0.2em">Thank you for using this Module!</p><h4>Developed by <a target="blank" href="https://omeka.libnamic.com/?ref=GAModuleOmekaS&amp;pos=config">Libnamic</a></h4>';
        $html .= '<a target="blank" href="https://omeka.libnamic.com/?ref=GAModuleOmekaS&amp;pos=config_logo"><img style="max-height: 78px; display: block; margin: 0.5em 0.1em" src="https://assets.libnamic.com/logos/libnamic.png?ref=GAModuleOmekaS&amp;pos=config_logo" alt="Libnamic"></a>';
        $html .= '<h5><a href="mailto:support@libnamic.com" target="blank">¿Do you have any suggestions or need support?</a></h5><h6><a target="blank" href="https://github.com/Libnamic/Omeka-S-GoogleAnalytics/issues">Github issues page</a></h6>';
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();
        $settings->set('googleanalytics', $params);
    }

    // Site settings
    public function addSiteSettings($event)
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $form = $event->getTarget();
        
        $fieldset = new Fieldset('libnamic_googleanalytics');
        $fieldset->setLabel('Libnamic Google Analytics');
        $fieldset->setAttribute('action', 'libnamic_googleanalytics/settings');

        $fieldset->add([
            'name' => 'googleanalytics_code',
            'type' => 'Text',
            'options' => [
                'label' => 'Google Analytics tracking code for this site. Input "-" if none should be used (not even the global code)', // @translate
            ],
            'attributes' => [
                'required' => false,
                'value' => $siteSettings->get('googleanalytics_code', ''),
            ],
        ]);


        $form->add($fieldset);
    }



    public function addSiteSettingsFilters($event)
    {
        // Input filters
        $inputFilter = $event->getParam('inputFilter');
        
        
        $moduleInputFilter = $inputFilter->get('libnamic_googleanalytics');

        $moduleInputFilter->add([
            'name' => 'googleanalytics_code',
            'allow_empty' => true,
        ]);
    }

 
    /**
     * Print script for Google Analytics.
     *
     * @param Event $event
     */
    public function printScript(Event $event)
    {
        $view = $event->getTarget();
        
        // Don't show if the user is logged in
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        if (!$user) {


            // First check if the site has a Google Analytics set
            $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $sites = $api->search('sites', [])->getContent();
            $routeMatch = $this->getServiceLocator()->get('Application')
                    ->getMvcEvent()->getRouteMatch();
            $siteSlug = $routeMatch->getParam('site-slug');

            $found = false;
            foreach ($sites as $site) {
                if($site->slug()==$siteSlug)
                {
                    $siteSettings->setTargetId($site->id());
                    $code = $siteSettings->get('googleanalytics_code', '');
                    break;
                }
            }

            // Check the site code, and if it's empty, use the global one
            if(empty($code))
            {
                $settings = $this->getServiceLocator()->get('Omeka\Settings');        
                $settings = $settings->get('googleanalytics', '');
                if($settings!=null)
                    $code = $settings['googleanalytics_code'];
            }

            if((!empty($code))&&($code!='-'))
            {
                $view->headScript()->appendScript("window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
                ga('create', '$code', 'auto');
                ga('send', 'pageview');
                ");
                $view->headScript()->appendFile('https://www.google-analytics.com/analytics.js', '', array('async'=>'true'));
            }   
        }
    }
}
