<?php
/**
 * Copyright (c) 2012-2013 Michael Sauter <mail@michaelsauter.net>
 * Orchestra originated from a TripleTime project of SitePoint.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace Orchestra;

use Symfony\Component\Validator\Validation;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Orchestra\Twig\WordpressProxy;
use Orchestra\Twig\PluginBaseExtension;

/**
 * Central class of Orchestra
 *
 * Plugins can use this class to tap into the functionality of Orchestra.
 * To do this, they need to call Framework::setupPlugin() during the "admin_menu" hook.
 * To get the returned content of Orchestra, they need to call Framework::getResponse().
 */
class Framework
{
    /**
     * Front controller
     *
     * @var Orchestra\FrontController
     */
    private static $frontController;

    /**
     * Namespace of the active plugin
     *
     * @var string
     */
    public static $pluginNamespace;

    /**
     * Current request
     *
     * @var Symfony\Component\HttpFoundation\Request
     */
    private static $request;

    /**
     * Determines if the calling plugin is currently active (that is, it was requested).
     * If so, configure Orchestra for this plugin (e.g. setting namespace, directories etc.)
     * and construct a front controller to handle the current request.
     *
     * @param $pluginNamespace
     * @param $pluginDirectory
     * @param array $additionalNamespaces
     * @param array $additionalPrefixes
     * @param array $directories
     * @return mixed
     */
    public static function setupPlugin($pluginNamespace, $pluginDirectory, $additionalNamespaces = array(), $additionalPrefixes = array(), $directories = array('src' => '/src', 'views' => '/resources/views', 'cache' => '/data/cache'))
    {
        global $orchestraConfig;
        global $orchestraClassLoader;

        // If there is no $request set yet, create one from globals
        // This needs to be done only once because the request is the
        // same, no matter which plugin called Framework::setupPlugin()
        // Also, make sure to undo WP addslasjes madness
        // Code is taken from Request::createFromGlobals()
        if (!self::$request) {
            $request = new Request(stripslashes_deep($_GET), stripslashes_deep($_POST), array(), stripslashes_deep($_COOKIE), stripslashes_deep($_FILES), stripslashes_deep($_SERVER));
            if (0 === strpos($request->headers->get('CONTENT_TYPE'), 'application/x-www-form-urlencoded')
                && in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), array('PUT', 'DELETE', 'PATCH'))
            ) {
                parse_str($request->getContent(), $data);
                $request->request = new ParameterBag($data);
            }
            self::$request = $request;
        }

        // Generate plugin identifier based on the namespace by stripping the backslashes
        $pluginIdentifier = str_replace('\\', '', $pluginNamespace);

        // Only proceed if the requested page is the calling plugin
        if (self::$request->query->get('page') == $pluginIdentifier) {

            // Register the namespace of the calling plugin
            // and any additional namespaces passed
            $additionalNamespaces[$pluginNamespace] = $pluginDirectory.$directories['src'].'/';
            $orchestraClassLoader->registerNamespaces($additionalNamespaces);

            // Register prefixes is passed
            if (count($additionalPrefixes) > 0) {
                $orchestraClassLoader->registerPrefixes($additionalPrefixes);
            }

            self::$pluginNamespace = $pluginNamespace;

            // Boot Doctrine and use the configuration of the active plugin
            if (!class_exists("Doctrine\Common\Version", false)) {
                include_once($pluginDirectory.'/doctrine-config.php');
                include_once(__DIR__.'/../../includes/bootstrap-doctrine.php');
            }

            // Setup Twig
            $translator = new Translator($orchestraConfig['language']);
            $translator->addLoader('xlf', new XliffFileLoader());
            $translator->addResource('xlf', $orchestraConfig['vendorDir'].'/symfony/form/Symfony/Component/Form/Resources/translations/validators.'.$orchestraConfig['language'].'.xlf', $orchestraConfig['language'], 'validators');
            $translator->addResource('xlf', $orchestraConfig['vendorDir'].'/symfony/validator/Symfony/Component/Validator/Resources/translations/validators.'.$orchestraConfig['language'].'.xlf', $orchestraConfig['language'], 'validators');
            $loader = new \Twig_Loader_Filesystem(array(
                realpath($pluginDirectory.$directories['views']),
                $orchestraConfig['vendorDir'].'/symfony/twig-bridge/Symfony/Bridge/Twig/Resources/views/Form',
            ));
            $twigFormEngine = new TwigRendererEngine(array('form_div_layout.html.twig'));
            $twigEnvironmentOptions = array();
            if ($orchestraConfig['env'] == 'prod') {
                $twigEnvironmentOptions['cache'] = realpath($pluginDirectory.$directories['cache']);
            } else {
                $twigEnvironmentOptions['cache'] = false;
            }
            $twig = new \Twig_Environment($loader, $twigEnvironmentOptions);
            $twig->addGlobal('wp', new WordpressProxy());
            $twig->addExtension(new PluginBaseExtension(self::$request));
            $twig->addExtension(new TranslationExtension($translator));
            $twig->addExtension(new FormExtension(new TwigRenderer($twigFormEngine, null)));
            $twigFormEngine->setEnvironment($twig);

            // Setup the form factory with all CSRF and validator extensions
            $csrfProvider = new DefaultCsrfProvider($orchestraConfig['csrfSecret']);
            $validator = Validation::createValidatorBuilder()
                ->enableAnnotationMapping()
                ->getValidator();
            $formFactory = Forms::createFormFactoryBuilder()
                ->addExtension(new CsrfExtension($csrfProvider))
                ->addExtension(new ValidatorExtension($validator))
                ->getFormFactory();

            // Instantiate FrontController
            self::$frontController = new FrontController(self::$request, $em, $twig, $formFactory);
        }

        // Return the generated plugin identifier for use
        // inside the plugin, e.g. in add_menu_page()
        return $pluginIdentifier;
    }

    /**
     * Returns the response stored in the front controller
     *
     * @return mixed
     */
    public static function getResponse()
    {
        return self::$frontController->getResponse();
    }

    public static function displayError($exception)
    {
        wp_die('<div style="overflow: scroll;"><p><strong>Error!</strong></p><p>'.$exception->getFile().':'.$exception->getLine().'</p><p>'.$exception->getMessage().'</p><pre>'.$exception->getTraceAsString().'</pre></div>');
    }
}