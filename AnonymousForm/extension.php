<?php

namespace AnonymousForm;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\Translation\Loader as TranslationLoader;
use Symfony\Component\Translation\Dumper\YamlFileDumper;
use Symfony\Component\Yaml\Dumper as YamlDumper,
    Symfony\Component\Yaml\Parser as YamlParser,
    Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\HttpFoundation\Request;

class Extension extends \Bolt\BaseExtension
{
    private $authorized = false;
    private $backupDir;
    private $translationDir;
    public $config;

    /**
     * @return array
     */
    public function info()
    {

        return array(
            'name' => "AnonymousForm",
            'description' => "a form handler for non-administrators",
            'tags' => array('general', 'tool'),
            'type' => "General",
            'author' => "Andrey Pitko",
            'link' => "http://artvisio.com",
            'email' => 'andrey.p@artvisio.com',
            'version' => "0.2",
            'required_bolt_version' => "1.4",
            'highest_bolt_version' => "1.5.5",
            'first_releasedate' => "2014-04-07",
            'latest_releasedate' => "2014-04-16"
        );

    }

    public function initialize()
    {
        $this->config = $this->getConfig();
        $this->app->match($this->config['prefix'] . '{contenttype}', array($this, 'formHandler'));
        $this->addTwigFunction('anonymouseFormPath', 'anonymouseFormPath');
        $this->addTwigFunction('getCaptchaFormField', 'getCaptchaFormField');
    }

    public function getCaptchaFormField()
    {
        require_once('recaptcha/recaptchalib.php');

        return recaptcha_get_html($this->config['captcha']['public']);
    }

    public function anonymouseFormPath($contenttype)
    {
        if (!in_array($contenttype, $this->config['contenttypes'])) {
            throw new \Exception("Wrong contenttype");
        }

        return $this->app['paths']['root'] . $this->config['prefix'] . $contenttype;
    }

    protected function returnAfterSubmit($contenttype, $params = array())
    {
        if (isset($this->config['render'])) {
            foreach ($this->config['render'] as $key => $value) {
                if ($key == $contenttype) {
                    foreach( $value as $type=>$arg){
                        switch($type){
                            case 'template': return $this->app['twig']->render($arg, $params);
                            case 'path': return $this->app->redirect($arg);
                            case 'route': return $this->app['url_generator']->generate($arg);
                        }
                    }
                }
            }
        }

        return $this->app->redirect($this->app['request']->headers->get('referer'));
    }

    public function formHandler($contenttype, Request $request)
    {
        if ($request->isMethod('POST') && in_array($contenttype, $this->config['contenttypes'])) {
            if (isset($this->config['captcha']['enabled'])
                && is_array($this->config['captcha']['enabled'])
                && in_array($contenttype, $this->config['captcha']['enabled'])
            ) {
                require_once('recaptcha/recaptchalib.php');
                $resp = recaptcha_check_answer(
                    $this->config['captcha']['private'],
                    $_SERVER["REMOTE_ADDR"],
                    $request->request->get('recaptcha_challenge_field'),
                    $request->request->get('recaptcha_response_field')
                );
                if (!$resp->is_valid) {
                    return $this->returnAfterSubmit(
                        $contenttype,
                        array('error' => $this->config['captcha']['error_message'])
                    );
                }
            }
            $storage = $this->app['storage'];
            $content = $storage->getContentObject($contenttype);
            foreach ($request->request->all() as $key => $value) {
                $content->setValue($key, $value);
            }
            $storage->saveContent($content);

            return $this->returnAfterSubmit($contenttype);
        }
    }

}