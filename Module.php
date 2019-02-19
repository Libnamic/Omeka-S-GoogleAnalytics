<?php
/**
 * GoogleAnalytics
 *
 * Includes simple support for Google Analytics in Omeka S
 *
 * @copyright Jesús Bocanegra Linares, Libnamic, 2019
 * @license MIT License
 *
 * This software is governed by the MIT License, included with the source code.
 */
namespace GoogleAnalytics;

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
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'printScript']
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
        $html .= '<h4><a target="_blank" href="https://support.google.com/analytics/answer/1008080#trackingID">Where can I find my tracking code?</a></h4>';
        $html .= '<p style="margin: 2em 0 0.2em">Thank you for using this Module</p><h4>Developed by <a target="_blank" href="https://libnamic.com/?ref=GAModuleOmekaS&amp;pos=config">Libnamic</a></h4>';
        $html .= '<a target="_blank" href="https://libnamic.com/?ref=GAModuleOmekaS&amp;pos=config_logo"><img style="max-height: 78px; display: block; margin: 0.5em 0.1em" src="https://assets.libnamic.com/logos/libnamic.png?ref=GAModuleOmekaS&amp;pos=config_logo" alt="Libnamic"></a>';
        $html .= '<h5><a href="mailto:support@libnamic.com">Support</a></h5>';
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
            $settings = $this->getServiceLocator()->get('Omeka\Settings');        
            $settings = $settings->get('googleanalytics', '');
            $code = $settings['googleanalytics_code'];

            if(!empty($code))
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
