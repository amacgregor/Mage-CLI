<?php
declare(ticks = 1);
require_once 'abstract.php';

class Magento_Shell_Mirc extends Mage_Shell_Abstract
{
    protected static $instance = null;
    protected $historyFile = null;
    protected $histSize    = 150;
    protected $history     = array();

    public function __construct()
    {
        if ($this->_includeMage) {
            require_once $this->_getRootPath() . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';
            Mage::app($this->_appCode, $this->_appType);
        }

        $this->_applyPhpVariables();
        $this->_parseArgs();
        $this->_construct();
        $this->_validate();
        $this->_showHelp();

        $rootPath    = $this->_getRootPath();
        $this->historyFile = $rootPath . 'var/log/magento_mirc.log';

        if (!file_exists($this->historyFile)) {
            file_put_contents($this->historyFile, '');
        }
        readline_read_history($this->historyFile);
        $this->history = explode(file_get_contents($this->historyFile), "\n");
        if (isset($_ENV['HISTSIZE']) && $_ENV['HISTSIZE'] > 0) {
            $this->histSize = $_ENV['HISTSIZE'];
        }

        readline_completion_function(array($this, 'completeCallback'));
        register_shutdown_function(array($this, 'fatalErrorShutdown'));
        # // Catch Ctrl+C, kill and SIGTERM
        pcntl_signal(SIGTERM, array($this, 'sigintShutdown'));
        pcntl_signal(SIGINT, array($this, 'sigintShutdown'));
    }

    public function run()
    {
        // TODO: Implement run() method.
        $multiline = '';
        $is_multiline = 0;

        while (true) {
            $line = readline('magento > ');
            $linereverse = strrev( $line );
            if($linereverse[0] != ';') {
                $is_multiline = 1;
            }
            if(!$is_multiline){
                if ($line == 'exit') {
                    $this->quit();
                }
                if ($line == 'help') {

                    $this->usageHelp();
                }
                if (!empty($line)) {
                    $this->addToHistory($line);
                    eval($line);
                    echo "\n";
                }
            }else {
                if($linereverse[0] == '}'){
                   $multiline .= ' '. $line;

                   $this->addToHistory($multiline);
                   eval($multiline);
                   $multiline ='';
                   $is_multiline = 0;

                   echo "\n";
                }else{
                   $multiline .= ' '. $line;
                }
            }

        }
    }

    public function addToHistory($line)
    {
        if ($histsize = count($this->history) > $this->histSize) {
            $this->history = array_slice($this->history, $histsize - $this->histSize);
        }
        readline_add_history($line);
    }
    protected function completeCallback($line)
    {
        if (!empty($line)) {
            $line = preg_quote($line);
            $funcs = get_defined_functions();
            $constants = get_defined_constants();//use these?
            $avail = array_merge(get_declared_classes(),$funcs['user'], $funcs['internal'], array());
            $matches =  preg_grep("/^$line/", $avail);
            if (!empty($matches)) {//will segfault if we return empty array after 3 times...
                return $matches;
            }
        }
    }

    public function fatalErrorShutdown()
    {
        $this->quit();
    }
    public function sigintShutdown($signal)
    {
        if ($signal === SIGINT || $signal === SIGTERM) {
            $this->quit();
        }
    }
    public function __destruct()
    {
        if (!empty($this->historyFile) && is_writable($this->historyFile)) {
            readline_write_history($this->historyFile);
        }
    }
    public function quit($code=0)
    {
        $this->__destruct();//just to be safe, if eval causes fatal error we have to call explicitly
        exit($code);
    }
}
$shell = new Magento_Shell_Mirc();
$shell->run();