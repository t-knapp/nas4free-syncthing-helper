#!/usr/local/bin/php-cgi -f
<?php

error_reporting(E_ALL);

function clog($logLevel, $message){
    return date("Y-m-d H:i:s") . " [{$logLevel}] {$message}" . PHP_EOL;
}

function info($message){
    echo clog("INFO", $message);
}

function err($message){
    echo clog("ERR", $message);
}

class LatestReleases {

    //Load list of latest five releases
    //private $GITHUBPATH = "https://api.github.com/repos/syncthing/syncthing/releases?per_page=5";
    //Fix: Exclude development and test releases.
    private $GITHUBPATH = "https://api.github.com/repos/syncthing/syncthing/releases/latest";

    public function __construct(){
        info("LatestRelease::__construct()");
        info("Fetching latest Releases.");
        exec("fetch --no-verify-hostname -o {$this->jsonFile} '{$this->GITHUBPATH}'");
    }

    public function getLatestRelease(){
        if(!isset($this->object) or $this->object === null){
            $this->loadJson();
        }
        $this->latestVersion = trim($this->object->name);
        return $this->latestVersion;
    }

    public function getLatestReleaseURL(){
        if(!isset($this->latestVersion) or $this->latestVersion === null){
            $this->getLatestRelease();
        }
        $searchFile = $this->getArchiveName();
        foreach ($this->object->assets as $asset) {
            if ($asset->name === $searchFile){
                return $asset->browser_download_url;
            }
        }
    }

    public function getArchiveName(){
        return "syncthing-freebsd-amd64-{$this->latestVersion}.tar.gz";
    }

    public function downloadLatestRelease($targetDirectory){
        if(!is_dir($targetDirectory)){
            err("Path must be a directory");
            return;
        }
        $url = $this->getLatestReleaseURL();
        exec("fetch --no-verify-hostname -o {$targetDirectory} '{$url}'");
    }

    private function loadJson(){
        $jsonSource = file_get_contents($this->jsonFile);
        $array = json_decode($jsonSource);
        $this->object = &$array;
    }

    private $jsonFile = "releases.json";
    private $object = null;
    private $latestVersion = null;
}

class CurrentRelease {
    
    private $LOGFILEPATH = "/mnt/stick/opt/syncthing/syncthing-log.log";
    
    public function __construct($pathToSyncting, $configPath) {
        if(file_exists($pathToSyncting) and file_exists($configPath)){
            if(is_executable($pathToSyncting)){
                $this->syncthing = $pathToSyncting;
            }
            if(is_dir($configPath)){
                $this->configPath = $configPath;
            }
        }
    }
    
    public function getVersion(){
        return trim(exec("{$this->syncthing} -version | awk '{print $2}'"));
    }
    
    public function stop(){
        info("Stopping all running syncthing instances ...");
        exec("killall -15 syncthing");
        $return_val = 0;
        while( $return_val == 0 ) { 
            sleep(1); 
            exec('ps acx | grep syncthing', $output, $return_val);
            info("Waiting for all instances being killed.");
        }
    }
    
    public function start(){
        info("Starting...");
        info("{$this->syncthing} -no-browser -logfile=\"{$this->LOGFILEPATH}\" -home=\"{$this->configPath}\" > /dev/null 2>/dev/null &");
        exec("{$this->syncthing} -no-browser -logfile=\"{$this->LOGFILEPATH}\" -home=\"{$this->configPath}\" > /dev/null 2>/dev/null &");
    }
    
    public function upgradeTo($URL){
        // /mnt/stick/opt/syncthing/syncthing -no-browser -home="/mnt/stick/opt/syncthing/.config/syncthing" -upgrade-to="http://localhost/syncthing/syncthing-freebsd-amd64-v0.11.23.tar.gz"
        info("{$this->syncthing} -no-browser -logfile=\"{$this->LOGFILEPATH}\" -home=\"{$this->configPath}\" -upgrade-to=\"{$URL}\"");
        $output = exec("{$this->syncthing} -no-browser -logfile=\"{$this->LOGFILEPATH}\" -home=\"{$this->configPath}\"  -upgrade-to=\"{$URL}\"");
        print_r($output);
        echo PHP_EOL;
    }
    
    private $syncthing;
    private $configPath;
}

//
// Main
//

// /mnt/stick/opt/syncthing/syncthing -no-browser -home="/mnt/stick/opt/syncthing/.config/syncthing"

$release = new LatestReleases();
$currentRelease = new CurrentRelease("/mnt/stick/opt/syncthing/syncthing", "/mnt/stick/opt/syncthing/.config/syncthing");

$currentVersion = $currentRelease->getVersion();
//$currentVersion = "v0.11.22";
$latestVersion  = $release->getLatestRelease();

info("Current version is {$currentVersion} latest version is {$latestVersion}");

//Check if update must be done
if(strcmp($currentVersion, $latestVersion) !== 0){
    info("Update needed.");
    
    //Download
    $url = $release->getLatestReleaseURL();
    info("Downloading {$url}");
    $release->downloadLatestRelease("/mnt/stick/www/syncthing/");
    
    //Stop
    $currentRelease->stop();
    
    //Upgrade
    $currentRelease->upgradeTo("http://localhost/syncthing/{$release->getArchiveName()}");
}

//Start
$currentRelease->start();


?>
