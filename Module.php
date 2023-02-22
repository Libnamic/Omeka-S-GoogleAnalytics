<?php

/**
 * GoogleAnalytics
 *
 * Includes simple support for Google Analytics in Omeka S
 *
 * @copyright Jesús Bocanegra Linares, Libnamic, 2021
 * @license MIT License
 *
 * This software is governed by the MIT License, included with the source code.
 */

namespace GoogleAnalytics;

use Laminas\Validator;
use Laminas\Validator\Callback;
use Laminas\Form\Fieldset;
use Omeka\Module\AbstractModule;
use GoogleAnalytics\Form\ConfigForm;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{

    protected $validator;

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
        // Global settings
        $sharedEventManager->attach(
            'Omeka\Form\SettingForm',
            'form.add_elements',
            [$this, 'addGlobalSettings']
        );
        $sharedEventManager->attach(
            'Omeka\Form\SettingForm',
            'form.add_input_filters',
            [$this, 'addSettingsFilters']
        );



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
        $html .= '<div style="display: flex; gap: 1em;margin: -1em 0 2em;"><p style="margin: 0">Thank you for using this Module!</p><a target="blank" href="https://support.google.com/analytics/answer/1008080#trackingID">Where can I find my tracking code?</a></div>';
        $html .= '<div style="display: flex; flex-wrap: wrap; gap: 3em;"><div style="flex: 0 1 30em;"><h3 style="margin-bottom: 1rem;text-align: center;">Developed by <a style="color: #584949;" target="blank" href="https://libnamic.link/r/D74?ref=GAModuleOmekaS">Libnamic - Digital Humanities</a></h3><a target="blank" href="https://libnamic.link/r/D74?ref=GAModuleOmekaS"><img style="max-height: 78px; display: block; margin: 0.5em 0.1em" src="https://libnamic.link/r/gIs?ref=GAModuleOmekaS" alt="Libnamic Digital Humanities"></a><p style="margin: 0;font-weight: bold;text-align: center;">We provide Omeka S consultancy, design and development</p><p style="display: flex; gap: 1.5em; justify-content: center;margin: 0.5em;"><a style="color: #212529" target="blank" href="https://libnamic.link/r/s71?ref=GAModuleOmekaS">Projects</a><a style="color: #212529" target="blank" href="https://libnamic.link/r/XOj?ref=GAModuleOmekaS">Blog</a><a style="color: #212529" target="blank" href="https://libnamic.link/r/2l3?ref=GAModuleOmekaS">Contact</a></p></div><div style="flex: 1 1 15em;background-color: #ECE8DD;color: #212529;padding: 1.33em 1.5em 0.5em;margin: 0 1em;"><h4>Libnamic Suite for Omeka S</h4><ul><li>Advanced block builder with powerful new blocks, multi-column arrangements and complex layouts</li><li>True multilingual support for Omeka S</li><li>Advanced metadata features: create and modify your own ontologies, get your collection to Europeana...</li><li>Configurable theme with multi-language support and dozens of variations</li><li>Advanced features such as child themes and permalinks</li></ul><a style="color: #584949;" class="button" target="blank" href="https://libnamic.link/r/SyZ?ref=GAModuleOmekaS">Try it now!</a></div></div>';
        $html .= '<h5><a href="mailto:omeka@libnamic.com" target="blank">¿Do you have any suggestions or need support?</a></h5><h6><a target="blank" href="https://github.com/Libnamic/Omeka-S-GoogleAnalytics/issues">Github issues page</a></h6>';
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

        $code = $params['googleanalytics_code'];
        if (preg_match("/^([G][A]{0,1}[-]\w*|[U][A]{1}[-]\w*[-]\w*)/", $code) == 0) {

            $controller->messenger()->addErrors(['The format of Google Analytics Code is incorrect']); //@translate
            return false;
        }

        if (!$form->isValid()) {
            $controller->messenger()->addErrors([$form->getMessages()]);
            return false;
        }


        $params = $form->getData();
        $settings->set('googleanalytics', $params);
    }


    // global settings
    public function addGlobalSettings($event)
    {

        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');

        $form = $event->getTarget();

        $fieldset = new Fieldset('libnamic_googleanalytics');
        $fieldset->setLabel('Global Settings Google Analytics'); // @translate
        $fieldset->setAttribute('action', 'libnamic_googleanalytics/settings');
        $fieldset->add([
            'name' => 'google_analytics_internal_user',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Allow track visit from login users in Google Analytics.', // @translate
            ],
            'attributes' => [
                'required' => false,
                'value' => $globalSettings->get('google_analytics_internal_user', ''),
            ],
        ]);
        $form->add($fieldset);
    }

    public function addSettingsFilters($event)
    {
        // Input filters
        $inputFilter = $event->getParam('inputFilter');


        $googleAnaluyticsInputFilter = $inputFilter->get('libnamic_googleanalytics');

        $googleAnaluyticsInputFilter->add([
            'name' => 'google_analytics_internal_user',


        ]);
    }
    // Site settings
    public function addSiteSettings($event)
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $form = $event->getTarget();

        $fieldset = new Fieldset('libnamic_googleanalytics_site');
        $fieldset->setLabel('Libnamic Google Analytics');
        $fieldset->setAttribute('action', 'libnamic_googleanalytics/settings');

        $fieldset->add([
            'name' => 'googleanalytics_code',
            'type' => 'Text',
            'options' => [
                'label' => 'Google Analytics', // @translate
                'info' => 'Google Analytics tracking code for this site. Input "-" if none should be used (not even the global code)', // @translate

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


        $moduleInputFilter = $inputFilter->get('libnamic_googleanalytics_site');


        $moduleInputFilter->add([
            'name' => 'googleanalytics_code',
            'allow_empty' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'Callback',
                    'options' => [
                        'messages' => [
                            Callback::INVALID_VALUE => 'The format of Google Analytics Code is incorrect', // @translate
                        ],
                        'callback' => [$this, 'codeIsValid'],
                    ],
                ],
            ],
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

        // Disable for upgrade requests. This avoids fatal errors before upgrading the database after updating Omeka
        $params = $view->params()->fromRoute();
        if ($params['controller'] == 'Omeka\Controller\Migrate') {
            return;
        }
        if ($view->status()->isAdminRequest())
            return;

        // Don't show if the user is logged in
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
        $track = $globalSettings->get('google_analytics_internal_user');


        if ($track == '1' || !$user) {


            // First check if the site has a Google Analytics set
            $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $sites = $api->search('sites', [])->getContent();
            $routeMatch = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch();
            $siteSlug = $routeMatch->getParam('site-slug');

            $found = false;
            $code = '';
            $extra_snippet = '';
            foreach ($sites as $site) {
                if ($site->slug() == $siteSlug) {
                    $siteSettings->setTargetId($site->id());
                    $code = $siteSettings->get('googleanalytics_code', '');
                    $extra_snippet = $siteSettings->get('additional_snippet', '');
                    break;
                }
            }

            // Check the site code, and if it's empty, use the global one
            if (empty($code)) {
                $settings = $this->getServiceLocator()->get('Omeka\Settings');
                $settings = $settings->get('googleanalytics', '');
                if ($settings != null)
                    $code = $settings['googleanalytics_code'];
            }
            if (empty($extra_snippet)) {
                $settings = $this->getServiceLocator()->get('Omeka\Settings');
                $settings = $settings->get('googleanalytics', '');
                if ($settings != null)
                    $extra_snippet = $settings['additional_snippet'];
            }


            if ((!empty($code)) && ($code != '-')) {

                // new analytics
                if (preg_match('/^([G]{1}[-]\w*|[U][A]{1}[-]\w*[-]\w*)/', $code) == 1) {

                    $view->headScript()->appendFile('https://www.googletagmanager.com/gtag/js?id=' . $code, '', array('async' => 'true'));

                    $view->headScript()->appendScript(
                        "
                    
                      window.dataLayer = window.dataLayer || [];
                      function gtag(){dataLayer.push(arguments);}
                      gtag('js', new Date());
                    
                      gtag('config', '$code');"

                    );

                    
                    // classic analytics
                } else {
                    $view->headScript()->appendFile('https://www.google-analytics.com/analytics.js', '', array('async' => 'true'));
                    $view->headScript()->appendScript("window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
                    ga('create', '$code', 'auto');
                    ga('send', 'pageview');
                    ");
                    
                }
            }
            // Future feature
            // if ((!empty($extra_snippet)) && ($extra_snippet != '-')) {

                // $view->headScript()->append($extra_snippet);
                // print_r(get_class_methods($view)); //$extra_snippet
                // $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
                // $placeholder = $viewHelperManager->get('placeholder');
                // $placeholder->getContainer('head')->set('<noscript>'.$extra_snippet.'</noscript>');
            // }
        }
    }
    public function codeIsValid($code)
    {

        $valid = '';
        if (preg_match("/^([G][A]{0,1}[-]\w*|[U][A]{1}[-]\w*[-]\w*)/", $code) == 1 || $code === '-') {
            $valid = '$valid';
        } else {
            $valid = '';
        }
        return $valid;
    }
}
