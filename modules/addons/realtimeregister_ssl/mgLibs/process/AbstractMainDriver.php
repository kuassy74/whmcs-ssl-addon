<?php

namespace MGModule\RealtimeRegisterSsl\mgLibs\process;
use MGModule\RealtimeRegisterSsl as main;
use MGModule\RealtimeRegisterSsl\mgLibs;

/**
 * Main Abstract Controller 
 * 
 * @author Michal Czech <michael@modulesgarden.com>
 * @SuppressWarnings(PHPMD)
 */
abstract class AbstractMainDriver
{
    /**
     * Single Ton Instance
     * 
     * @var mainController
     */
    static private $_instance;
    
    /**
     * This Var define Debug Mode in Module
     * 
     * @var boolean
     */
    public $_debug = false;
    
    /**
     * Mark 
     * 
     * $var boolean
     */
    public $isLoaded;
        
    /**
     * Load Configuration
     * 
     * @var Configuration
     */
    private $_configuration;
    
    /**
     * Is Loaded From Admin Area
     * 
     * @var boolean 
     */
    private $_isAdmin = false;

    /**
     * Key For Data Encryption
     * 
     * @var string 
     */
    private $encryptSecureKey;
    
    /**
     * Main Namespace
     * 
     * @var string
     */
    private $_mainNamespace;
    
    private $_mainDIR;
    
    /**
     * Disable Contruct && Clone
     */
    final function __construct() {;}
    final function __clone() {;}
    
    /**
     * Get SingleTon Instance
     * 
     * @return AbstractMainDriver
     */
    public static function I($force = false, $configs = [])
    {
        if(empty(self::$_instance) || $force) {
            $class = get_called_class();
            
            MainInstance::setInstanceName($class);
                        
            self::$_instance = new $class();
            self::$_instance->_mainNamespace = substr(__NAMESPACE__,0,  strpos(__NAMESPACE__, '\mgLibs'));
            self::$_instance->_mainDIR = call_user_func([$class,'getMainDIR']);

            $class= self::$_instance->_mainNamespace.'\Configuration';
            
            self::$_instance->_configuration = new $class();
            
            foreach($configs as $name => $value) {
                self::$_instance->_configuration->{$name} = $value;
            }
            
            self::$_instance->isLoaded = true;
            
            if(!empty(self::$_instance->_configuration->debug)) {
                self::$_instance->_debug = true;
            }
                      
            main\mgLibs\MySQL\Query::useCurrentConnection();
            main\mgLibs\Lang::getInstance(self::$_instance->_mainDIR.DS.'langs');
        }
        
        return self::$_instance;
    }

    /**
     * 
     * @return main\Configuration
     */
    function configuration()
    {
        return $this->_configuration;
    }
    
    public function isAdmin($status = null)
    {
        if($status !== null) {
            $this->_isAdmin = $status;
        }
        return $this->_isAdmin;
    }
    
    public function isDebug()
    {
        return $this->_debug;
    }

    /**
     * Return Main Namespace
     * 
     * @return string
     */
    function getMainNamespace()
    {
        return $this->_mainNamespace;
    }
    
    /**
     * Return Enrypt Key
     * 
     * @return string
     */
    public function getEncryptKey()
    {
        if(empty($this->encryptSecureKey)) {
            $this->encryptSecureKey = hash(
                'sha256',
                $GLOBALS['cc_encryption_hash'].$this->configuration()->encryptHash,
                true
            );
        }
        
        return $this->encryptSecureKey;
    }
        
    function setMainLangContext()
    {
        mgLibs\Lang::setContext($this->getType().($this->isAdmin()?'AA':'CA'));
    }
    
    /**
     * Process Controllers
     * 
     * @param string $controller controller name
     * @param array $input input array
     * @param string $type type of request
     * @return array
     * @throws main\mgLibs\exceptions\System
     * @throws main\mgLibs\exceptions\System
     */
    function runControler($controller,$action = 'index',$input = [], $type = 'HTML')
    {
        try {
            $className = $this->getMainNamespace() . "\\controllers\\" . $this->getType(
                ) . "\\" . ($this->_isAdmin ? 'admin' : 'clientarea') . "\\" . $controller;

            if (!class_exists($className)) {
                throw new main\mgLibs\exceptions\System("Unable to find page");
            }

            $controllerOBJ = new $className($input);

            // display the page or not
            if (method_exists($controllerOBJ, "isActive") && !$controllerOBJ->{"isActive"}())
                throw new mgLibs\exceptions\System("No access to this page");

            if (!method_exists($controllerOBJ, $action.$type)) {
                throw new main\mgLibs\exceptions\System("Unable to find Action: ".$action.$type);
            }

            main\mgLibs\Lang::stagCurrentContext('generate'.$controller);
                       
            main\mgLibs\Lang::addToContext(lcfirst ($controller));

            main\mgLibs\Smarty::I()->setTemplateDir(
                self::I()->getModuleTemplatesDir() . DS . 'pages' . DS . lcfirst($controller)
            );

            $result = $controllerOBJ->{$action.$type}($input);

            switch ($type) {
                case 'HTML':
                    if(empty($result['tpl'])) {
                        throw new main\mgLibs\exceptions\System("Provide Template Name");
                    }
                    
                    $success = isset($result['vars']['success'])?$result['vars']['success']:false;
                    $error = isset($result['vars']['error'])?$result['vars']['error']:false;
                    $result = main\mgLibs\Smarty::I()->view($result['tpl'], $result['vars']);
                break;
                default:
                    $success = isset($result['success'])?$result['success']:false;
                    $error = isset($result['error'])?$result['error']:false;
            }

            main\mgLibs\Lang::unstagContext('generate'.$controller);

            return [
                $result,
                $success,
                $error
            ];
        } catch (\Exception $ex) {
            main\mgLibs\Lang::unstagContext('generate'.$controller);
            throw $ex;
            return false;
        }
    }
    
    /**
     * Dump data
     * 
     * @param mixed $input
     */
    static function dump($input)
    {
        if(self::I()->isDebug()) {
            echo "<pre>";
            print_r($input);
            echo "</pre>";
        }
    }
    
    abstract public function getAssetsURL();
    abstract public function getType();
    
    public static function getMainDIR()
    {
        return false;
    }

    public static function getUrl()
    {
        return false;
    }
    
    public function isActive()
    {
        return true;
    }
}
