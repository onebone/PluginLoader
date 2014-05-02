<?php

/*
__PocketMine Plugin__
name=PluginLoader
version=1.0
author=onebone
apiversion=10,11
class=PluginLoader
*/

class PluginLoader implements Plugin{
	private $api, $plugins;
	public function __construct(ServerAPI $api, $server = false){
		$this->api=$api;
   $this->plugins = array();
	}
	public function init(){
   PluginLoaderAPI::set($this);
		$this->api->console->register("load", "<plugin name>", array($this, "commandHandler"),15);
	}								
	public function __destruct(){}
	public function commandHandler($cmd, $params){
	switch($cmd){
		case "load":
		if(count($params) == 0){
		console("[PluginLoader] /load <plugin name>");
		}else{
			$plugin = implode(" ",$params);
			if(file_exists(DATA_PATH."/plugins/$plugin.php")){
				$this->load(DATA_PATH."plugins/".$plugin.".php");
       console("[PluginLoader] Plugin was loaded");
			}elseif(file_exists(DATA_PATH."/plugins/$plugin.pmf")){
			$this->load(DATA_PATH."/plugins/".$plugin.".pmf");
			}else{
			console("[ERROR] Plugin $plugin doesn't exists!!");
			}
			}
		}
	}
  
	public function load($file){ // Code from PluginAPI
		if(strtolower(substr($file, -3)) === "pmf"){
			$pmf = new PMFPlugin($file);
			$info = $pmf->getPluginInfo();
		}else{
			$content = file_get_contents($file);
			$info = strstr($content, "*/", true);
			$content = str_repeat(PHP_EOL, substr_count($info, "\n")).substr(strstr($content, "*/"),2);
			if(preg_match_all('#([a-zA-Z0-9\-_]*)=([^\r\n]*)#u', $info, $matches) == 0){ //false or 0 matches
				console("[ERROR] Failed parsing of ".basename($file));
				return false;
			}
			$info = array();
			foreach($matches[1] as $k => $i){
				$v = $matches[2][$k];
				switch(strtolower($v)){
					case "on":
					case "true":
					case "yes":
						$v = true;
						break;
					case "off":
					case "false":
					case "no":
						$v = false;
						break;
				}
				$info[$i] = $v;
			}
			$info["code"] = $content;
			$info["class"] = trim(strtolower($info["class"]));
		}
		if(!isset($info["name"]) or !isset($info["version"]) or !isset($info["class"]) or !isset($info["author"])){
			console("[ERROR] Failed parsing of ".basename($file));
			return false;
		}
		console("[INFO] Loading plugin \"".FORMAT_GREEN.$info["name"].FORMAT_RESET."\" ".FORMAT_AQUA.$info["version"].FORMAT_RESET." by ".FORMAT_AQUA.$info["author"].FORMAT_RESET);
		if($info["class"] !== "none" and class_exists($info["class"])){
			console("[ERROR] Failed loading plugin: class already exists");
			return false;
		}
		if(eval($info["code"]) === false or ($info["class"] !== "none" and !class_exists($info["class"]))){
			console("[ERROR] Failed loading {$info['name']}: evaluation error");
			return false;
		}
		
		$className = $info["class"];
		$apiversion = array_map("intval", explode(",", (string) $info["apiversion"]));
		if(!in_array((string) CURRENT_API_VERSION, $apiversion)){
			console("[WARNING] Plugin \"".$info["name"]."\" may not be compatible with the API (".$info["apiversion"]." != ".CURRENT_API_VERSION.")! It can crash or corrupt the server!");
		}
		
		if($info["class"] !== "none"){			
			$object = new $className($this->api, false);
			if(!($object instanceof Plugin)){
				console("[ERROR] Plugin \"".$info["name"]."\" doesn't use the Plugin Interface");
				if(method_exists($object, "__destruct")){
					$object->__destruct();
				}
				$object = null;
				unset($object);
			}else{
      $object->init();
				$this->plugins[$className] = array($object, $info);
			}
		}else{
			$this->plugins[md5($info["name"])] = array(new DummyPlugin($this->server->api, false), $info);
		}
	}
}

class PluginLoaderAPI{
  public static $object;
  public static function set(PluginLoader $obj){
    self::$object = $obj;
  }
}