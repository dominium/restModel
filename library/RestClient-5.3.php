<?php

class RestFetch extends SimpleXMLElement
{
}

class RestQuery
{
    public static function get ($id = null)
    {
        $class = get_called_class();
        print_r($class);exit;
        if (isset($class)) {
            $mode = $class::$__name;
            $params = $class::getAttributes();
            if ($params !== false) {
                $new_array = array_map(
                        create_function('$key, $value', 
                                'return $key."=".$value."";'), 
                        array_keys($params), array_values($params));
                $param_url = implode("&", $new_array);
                $url = str_replace(" ", "", 
                        REST_SERVER . $mode . "?" . $param_url);
                $res = RestClient::get($url, null, REST_LOGIN, REST_PASSWORD);
                var_dump($res);
            } else 
                if (intval($id) > 0) {
                    $url = REST_SERVER . $mode . "/" . intval($id);
                    $res = RestClient::get($url, null, REST_LOGIN, 
                            REST_PASSWORD);
                } else {
                    $url = REST_SERVER . $mode;
                    $res = RestClient::get($url, null, REST_LOGIN, 
                            REST_PASSWORD);
                }
            
            return RestQuery::getByXml($res->getResponse());
        }
        return null;
    }

    public static function set ()
    {
        $class = get_called_class();
        if (isset($class)) {
            $mode = $class::$__name;
            $params = $class::getAttributes();
            $url = REST_SERVER . $mode;
            $xml = ArrayToXML::toXml($params, $mode);
            $res = RestClient::post($url, $xml, REST_LOGIN, REST_PASSWORD);
            return RestQuery::getByXml($res->getResponse());
        }
        return false;
    }

    public static function delete ($id)
    {
        $class = get_called_class();
        if (isset($class)) {
            $mode = $class::$__name;
            $url = REST_SERVER . $mode . "/" . intval($id);
            $res = RestClient::delete($url, null, REST_LOGIN, REST_PASSWORD);
            return RestQuery::getByXml($res->getResponse());
        }
    }

    public static function update ($id)
    {
        $class = get_called_class();
        if (isset($class)) {
            $mode = $class::$__name;
            $params = $class::getAttributes();
            $url = REST_SERVER . $mode . "/" . intval($id);
            $xml = ArrayToXML::toXml($params, $mode);
            $res = RestClient::put($url, $xml, REST_LOGIN, REST_PASSWORD);
            return RestQuery::getByXml($res->getResponse());
        }
        return false;
    }

    public static function setAttribute ($name = null, $value = null)
    {
        if ($name != "__name") {
            $class = get_called_class();
            if (isset($class)) {
                $class_vars = get_class_vars($class);
                if (array_key_exists($name, $class_vars)) {
                    $class::${$name} = $value;
                }
            }
        }
        return false;
    }

    public static function getAttributes ()
    {
        $class = get_called_class();
        if ($class) {
            $class_vars = get_class_vars($class);
            unset($class_vars['__name']);
            foreach ($class_vars as $key => $var) {
                if (! empty($var)) {
                    $return[$key] = $class::${$key};
                }
            }
            return (($return == NULL) ? false : $return);
        }
        return false;
    }

    public static function getAttribute ($name)
    {
        if ($name != "__name") {
            $class = get_called_class();
            if (isset($class)) {
                $class_vars = get_class_vars($class);
                
                if (array_key_exists($name, $class_vars)) {
                    return (($class::${$name} == NULL) ? false : $class::${$name});
                }
            }
        }
        return false;
    }

    public static function getByXml ($response)
    {
        $xml_array = explode("\n", $response);
        array_shift($xml_array);
        $xml_string = implode("\n", $xml_array);
        $xml = simplexml_load_string($xml_string, RestFetch);
        return $xml;
    }

    public static function array_flip ($array)
    {
        foreach ($array as $key => $value) {
            $return[$value] = $key;
        }
        
        return $return;
    }
}

class RestClient
{

    private $curl;

    private $url;

    private $response = "";

    private $headers = array();

    private $method = "GET";

    private $params = null;

    private $contentType = null;

    private $file = null;

    private function __construct ()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_AUTOREFERER, true);
        // curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_HEADER, true);
    }

    public function execute ()
    {
        if ($this->method === "POST") {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->params);
        } else 
            if ($this->method == "GET") {
                curl_setopt($this->curl, CURLOPT_HTTPGET, true);
                $this->treatURL();
            } else 
                if ($this->method === "PUT") {
                    curl_setopt($this->curl, CURLOPT_PUT, true);
                    $this->treatURL();
                    $this->file = tmpFile();
                    fwrite($this->file, $this->params);
                    fseek($this->file, 0);
                    curl_setopt($this->curl, CURLOPT_INFILE, $this->file);
                    curl_setopt($this->curl, CURLOPT_INFILESIZE, 
                            strlen($this->params));
                } else {
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 
                            $this->method);
                }
        if ($this->contentType != null) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, 
                    array(
                            "Content-Type: " . $this->contentType
                    ));
        }
        curl_setopt($this->curl, CURLOPT_URL, $this->url);
        $r = curl_exec($this->curl);
        $this->treatResponse($r); // Extract the headers and response
        return $this;
    }

    private function treatURL ()
    {
        if (is_array($this->params) && count($this->params) >= 1) {
            if (! strpos($this->url, '?'))
                $this->url .= '?';
            foreach ($this->params as $k => $v) {
                $this->url .= "&" . urlencode($k) . "=" . urlencode($v);
            }
        }
        return $this->url;
    }

    private function treatResponse ($r)
    {
        if ($r == null or strlen($r) < 1) {
            return;
        }
        $parts = explode("\n\r", $r); // HTTP packets define that Headers end in
                                      // a blank line (\n\r) where starts the
                                      // body
        while (preg_match('@HTTP/1.[0-1] 100 Continue@', $parts[0]) or
                 preg_match("@Moved@", $parts[0])) {
                    // Continue header must be bypass
                    for ($i = 1; $i < count($parts); $i ++) {
                        $parts[$i - 1] = trim($parts[$i]);
            }
            unset($parts[count($parts) - 1]);
        }
        preg_match("@Content-Type: ([a-zA-Z0-9-]+/?[a-zA-Z0-9-]*)@", $parts[0], 
                $reg);
        $this->headers['content-type'] = $reg[1];
        preg_match("@HTTP/1.[0-1] ([0-9]{3}) ([a-zA-Z ]+)@", $parts[0], $reg);
        $this->headers['code'] = $reg[1];
        $this->headers['message'] = $reg[2];
        $this->response = "";
        for ($i = 1; $i < count($parts); $i ++) {
            if ($i > 1) {
                $this->response .= "\n\r";
            }
            $this->response .= $parts[$i];
        }
    }

    public function getHeaders ()
    {
        return $this->headers;
    }

    public function getResponse ()
    {
        return $this->response;
    }

    public function getResponseCode ()
    {
        if (! empty($this->headers['code'])) {
            return (int) $this->headers['code'];
        } else {
            return 0;
        }
    }

    public function getResponseMessage ()
    {
        return $this->headers['message'];
    }

    public function getResponseContentType ()
    {
        return $this->headers['content-type'];
    }

    public function setNoFollow ()
    {
        curl_setopt($this->curl, CURLOPT_AUTOREFERER, false);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, false);
        return $this;
    }

    public function close ()
    {
        curl_close($this->curl);
        $this->curl = null;
        if ($this->file != null) {
            fclose($this->file);
        }
        return $this;
    }

    public function setUrl ($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setContentType ($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function setCredentials ($user, $pass)
    {
        if ($user != null) {
            curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->curl, CURLOPT_USERPWD, "{$user}:{$pass}");
        }
        return $this;
    }

    public function setMethod ($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setParameters ($params)
    {
        $this->params = $params;
        return $this;
    }

    public static function createClient ($url = null)
    {
        $client = new RestClient();
        if ($url != null) {
            $client->setUrl($url);
        }
        return $client;
    }

    public static function post ($url, $params = null, $user = null, $pwd = null, 
            $contentType = "multipart/form-data")
    {
        return self::call("POST", $url, $params, $user, $pwd, $contentType);
    }

    public static function put ($url, $body, $user = null, $pwd = null, 
            $contentType = null)
    {
        return self::call("PUT", $url, $body, $user, $pwd, $contentType);
    }

    public static function get ($url, array $params = null, $user = null, $pwd = null)
    {
        return self::call("GET", $url, $params, $user, $pwd);
    }

    public static function delete ($url, array $params = null, $user = null, $pwd = null)
    {
        return self::call("DELETE", $url, $params, $user, $pwd);
    }

    public static function call ($method, $url, $body, $user = null, $pwd = null, 
            $contentType = null)
    {
        return self::createClient($url)->setParameters($body)
            ->setMethod($method)
            ->setCredentials($user, $pwd)
            ->setContentType($contentType)
            ->execute()
            ->close();
    }
}

class ArrayToXML
{

    public static function toXml ($data, $rootNodeName = 'dokument', $xml = null)
    {
        if ($xml == null) {
            $xml = simplexml_load_string(
                    "<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
        }
        
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = "unknown_" . (string) $key;
            }
            
            $key = preg_replace('/[^a-z_]/i', '', $key);
            
            if (is_array($value)) {
                $node = $xml->addChild($key);
                ArrayToXML::toXml($value, $rootNodeName, $node);
            } else {
                $xml->addChild($key, $value);
            }
        }
        return $xml->asXML();
    }
}
?>
