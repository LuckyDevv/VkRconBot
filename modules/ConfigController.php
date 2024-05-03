<?php namespace modules;
class ConfigController{
    private string $filename;
    private $config = [];
    const DETECT = 0;
    const YAML = 1;
    const JSON = 2;
    const SERIALIZED = 3;
    const ENUM = 4;
    const JSON_OPS = JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE;
    private $type = ConfigController::DETECT;
    private $formats = [
        "json" => ConfigController::JSON,
        "js" => ConfigController::JSON,
        "yml" => ConfigController::YAML,
        "yaml" => ConfigController::YAML,
        "sl" => ConfigController::SERIALIZED,
        "serialize" => ConfigController::SERIALIZED
        /* !! Other formats are an instance of the ENUM type !! */
    ];
    public function __construct(string $filename = 'default.txt', $type = ConfigController::DETECT, $default = []){
        $this->init($filename, $type, $default);
    }
    private function init(string $filename, $type, $default): void{
        $this->filename = $filename;
        $this->type = $type;
        if($type == ConfigController::DETECT){
            $this->type = ConfigController::ENUM;
            if(strpos($filename, '.')){
                $expansion = trim(strtolower(explode('.', $filename)));
                if(in_array($expansion, $this->formats)){
                    $this->type = $this->formats[$expansion];
                }
            }
        }
        if(!file_exists($filename)){
            $this->config = $default;
            $this->save();
        }else{
            $content = file_get_contents($filename);
            switch($type){
                case ConfigController::YAML:
                    $content = preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $content); // FIX YAML INDEX
                    $this->config = yaml_parse($content);
                    break;
                case ConfigController::JSON:
                    $this->config = json_decode($content, true);
                    break;
                case ConfigController::SERIALIZED:
                    $this->config = unserialize($content);
                    break;
                case ConfigController::ENUM:
                    $this->config = array_fill_keys($this->parseList($content), true);
                    break;
                default:
                    return;
            }
            if(!is_array($this->config)){
                $this->config = $default;
            }
        }
    }

    /**
     * @throws \ErrorException
     */
    public function save(): bool{
        $content = [];
        switch($this->type){
            case ConfigController::YAML:
                $content = yaml_emit($this->config, YAML_UTF8_ENCODING);
                break;
            case ConfigController::JSON:
                $content = json_encode($this->config, ConfigController::JSON_OPS);
                break;
            case ConfigController::SERIALIZED:
                $content = serialize($this->config);
                break;
            case ConfigController::ENUM:
                $content = implode("\n", array_keys($this->config));
                break;
            default:
                throw new \ErrorException("An attempt was made to save an unsupported configuration format");
        }
        return (file_put_contents($this->filename, $content) !== false);
    }
    public function getFileName(): string{
        return $this->filename;
    }
    public function get($key, $default = false){
        return $this->config[$key] ?? $default;
    }
    public function getAll(bool $keys = false): array{
        return ($keys ? array_keys($this->config) : $this->config);
    }
    public function set($key, $value = true): void{
        $this->config[$key] = $value;
    }
    public function setAll($value): void{
        $this->config = $value;
    }
    public function remove($key): void{
        if(isset($this->config[$key])){
            unset($this->config[$key]);
        }
    }
    public function exists($key): bool{
        return isset($this->config[$key]);
    }
    private function parseList($content): array{
        $result = [];
        foreach(explode("\n", trim(str_replace("\r\n", "\n", $content))) as $v){
            $v = trim($v);
            if($v === ""){
                continue;
            }
            $result[] = $v;
            $this->config[$v] = true;
        }
        return $result;
    }
}