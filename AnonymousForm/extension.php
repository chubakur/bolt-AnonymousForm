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
            'email' => 'chubakur@gmail.com',
            'version' => "0.1",
            'required_bolt_version' => "1.4",
            'highest_bolt_version' => "1.5.5",
            'first_releasedate' => "2014-04-07",
            'latest_releasedate' => "2014-04-07"
        );

    }

    public function initialize()
    {
        $this->config = $this->getConfig();
        $this->app->match($this->config['prefix'] . '{contenttype}', array($this, 'formHandler'));
        $this->addTwigFunction('anonymouseFormPath', 'anonymouseFormPath');
    }

    public function anonymouseFormPath($contenttype)
    {
        if ( !in_array($contenttype, $this->config['contenttypes']) )
            throw new \Exception("Wrong contenttype");
        return $this->app['paths']['root'] . $this->config['prefix'] . $contenttype;
    }

    public function formHandler($contenttype, Request $request)
    {
        if ($request->isMethod('POST') && in_array($contenttype, $this->config['contenttypes'])) {
            $storage = $this->app['storage'];
            $content = $storage->getContentObject($contenttype);
            foreach ($request->request->all() as $key => $value) {
                $content->setValue($key, $value);
            }
            $storage->saveContent($content);

            return $this->app->redirect($request->headers->get('referer'));
        }
    }

}