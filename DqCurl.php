<?php

/**
 *  定义http请求类
 */

class DqCurl {
    const CRLF = "\r\n";
    public $cookies = array();
    public $headers = array();
    public $post_fields = array();   
    public $query_fields = array();
    public $has_upload = false;

    public $url;
    public $method = false;
    public $host_name;
    public $host_port = "80";
    public $is_ssl = false;
    public $actual_host_ip;
    public $no_body = false;
    public $req_range = array();
    public $query_string = '';

    public $response_state;
    public $curl_info;
    public $error_msg;
    public $error_no;
    public $response_header;
    public $response_content;

    public $debug = false;
    public $urlencode = "urlencode_rfc3986";

    public $connect_timeout = 1000;
    public $timeout = 1000;

    private $ch = null;
    private $curl_id = false;

    private $callback_method;
    private $callback_obj;

    public $curl_cli;

    public $gzip = false;

    public $user = null;
    public $psw = null;

    public function __construct($url = "") {
        if (!empty($url)) {
            $this->set_url($url);
        }
    }

    public function set_url($url) {
        if (!empty($this->url)) {
            throw new Exception("url be setted");
        }

        $url_element = parse_url($url);

        if ($url_element["scheme"] == "https") {
            $this->is_ssl = true;
            $this->host_port = '443';
        } elseif ($url_element["scheme"] != "http") {
            throw new Exception("only support http now");
        }

        $this->host_name = $url_element['host'];

        $this->url = $url_element['scheme'] . '://' . $this->host_name;
        if (isset($url_element['port'])) {
            $this->host_port = $url_element['port'];
            $this->url .= ':' . $url_element['port'];
        }
        if (isset($url_element['path'])) {
            $this->url .= $url_element['path'];
        }

        if (!empty($url_element['query'])) {
            parse_str($url_element['query'], $query_fields);
            $keys = array_map(array($this, "run_urlencode"), array_keys($query_fields));
            $values = array_map(array($this, "run_urlencode"), array_values($query_fields));
            $this->query_fields = array_merge($this->query_fields, array_combine($keys, $values));
        }
    }

    public function set_method($method) {
        $this->method = strtoupper($method);
    }

    public function set_actual_host($ip) {
        $this->actual_host_ip = $ip;
    }

    public function set_connect_timeout($timeout) {
        $this->connect_timeout = (int)$timeout;
    }

    public function set_timeout($timeout) {
        $this->timeout = (int)$timeout;
    }

    public function set_request_range($start, $end) {
        $this->req_range = array($start, $end);
    }


    public function set_urlencode($urlencode) {
        $this->urlencode = $urlencode;
    }

    public function set_callback($method, $obj) {
        $this->callback_method = $method;
        $this->callback_obj = $obj;
    }

    public function add_header($primary, $secondary, $urlencode = false) {
        $primary = $this->run_urlencode($primary, $urlencode);
        $secondary = $this->run_urlencode($secondary, $urlencode);
        $this->headers[$primary] = $secondary;
    }

    public function add_userpsw($user, $psw) {
        $this->user = $user;
        $this->psw = $psw;
    }

    public function add_cookie($name, $value, $urlencode = false) {
        $name = $this->run_urlencode($name, $urlencode);
        $value = $this->run_urlencode($value, $urlencode);
        $this->cookies[$name] = $value;
    }

    public function add_query_field($name, $value, $urlencode = false) {
        $name = $this->run_urlencode($name, $urlencode);
        $value = $this->run_urlencode($value, $urlencode);
        $this->query_fields[$name] = $value;
    }

    public function add_post_field($name, $value, $urlencode = false) {
        $name = $this->run_urlencode($name, $urlencode);
        $value = $this->run_urlencode($value, $urlencode);
        $this->post_fields[$name] = $value;
    }

    public function add_post_file($name, $path) {
        $this->has_upload = true;
        $name = $this->run_urlencode($name);
        $this->post_fields[$name] = '@' . $path;
    }

    public function run_urlencode($input, $urlencode = false) {
        if ($urlencode) {
            return $this->{$urlencode}($input);
        } elseif ($this->urlencode) {
            return $this->{$this->urlencode}($input);
        } else {
            return $input;
        }
    }

    public function curl_init() {
        if ($this->ch !== null) {
            throw new Exception('curl init already');
        }
        $ch = curl_init();
        $this->curl_id = self::fetch_curl_id($ch);
        $this->ch = $ch;
        $this->curl_cli = 'curl -v ';
        $this->curl_setopt();
    }

    public function get_ch() {
        return $this->ch;
    }

    public function get_curl_id() {
        return $this->curl_id;
    }

    public function send() {
        $this->curl_init();
        $content = curl_exec($this->ch);
        if (curl_errno($this->ch) === 0) {
            $rtn = true;
            $this->set_response_state(true, "", curl_errno($this->ch));
        } else {
            $this->set_response_state(false, curl_error($this->ch), curl_errno($this->ch));
            $rtn = false;
        }
        $this->set_response($content, curl_getinfo($this->ch));
        $this->reset_ch();
        return $rtn;
    }

    public function reset_ch() {
        $this->ch = null;
        $this->curl_id = false;
    }

    public function get_curl_cli() {
        return $this->curl_cli;
    }

    public function set_response_state($state, $error_msg, $error_no) {
        $this->response_state = $state;
        $this->error_msg = $error_msg;
        $this->error_no = $error_no;
    }

    public function set_response($content, $info, $invoke_callback = true) {
        $this->curl_info = $info;

        if (empty($content)) {
            return;
        }

        $section_separator = str_repeat(self::CRLF, 2);
        $section_separator_length = strlen($section_separator);
        // pick out http 100 status header
        $http_100 = "HTTP/1.1 100 Continue" . $section_separator;
        if (false !== strpos($content, $http_100)) {
            $content = substr($content, strlen($http_100));
        }

        $last_header_pos = 0;
        // put header and content into each var, 3xx response will generate many header :(
        for($i = 0, $pos = 0; $i <= $this->curl_info['redirect_count']; $i ++) {
            if ($i + 1 > $this->curl_info['redirect_count'] && $pos) {
                $last_header_pos = $pos + $section_separator_length;
            }
            $pos += $i > 0 ? $section_separator_length : 0;
            $pos = strpos($content, $section_separator, $pos);
        }

        $this->response_content = substr($content, $pos + $section_separator_length);
        $headers = substr($content, $last_header_pos, $pos - $last_header_pos);
        $headers = explode(self::CRLF, $headers);
        foreach ($headers as $header) {
            if (false !== strpos($header, "HTTP/1.1")) {
                continue;
            }

            $tmp = explode(":", $header, 2);
            $response_header_key = strtolower(trim($tmp[0]));
            if(!isset($this->response_header[$response_header_key])){
                $this->response_header[$response_header_key] = trim($tmp[1]);
            }
            else{
                if(!is_array($this->response_header[$response_header_key])){
                    $this->response_header[$response_header_key] = (array)$this->response_header[$response_header_key];
                }
                $this->response_header[$response_header_key][] = trim($tmp[1]);
            }
        }
        // is there callback?
        if ($invoke_callback && !empty($this->callback_obj) && !empty($this->callback_method)) {
            call_user_func_array(array($this->callback_obj, $this->callback_method), array($this));
        }
    }

    public function get_response_state() {
        return $this->response_state;
    }

    public function get_error_msg() {
        return $this->error_msg;
    }

    public function get_error_no() {
        return $this->error_no;
    }

    public function get_response_time() {
        return $this->get_response_info('total_time');
    }

    public function get_response_info($key = "") {
        if (empty($key)) {
            return $this->curl_info;
        } else {
            if (isset($this->curl_info[$key])) {
                return $this->curl_info[$key];
            } else {
                throw new Exception("info: " . $key . " not exists");
            }
        }
    }

    public function get_response_header($key = "") {
        if (empty($key)) {
            return $this->response_header;
        } else {
            if (isset($this->response_header[$key])) {
                return $this->response_header[$key];
            } else {
                throw new Exception("header: " . $key . " not exists");
            }
        }
    }

    public function get_response_content() {
        return $this->response_content;
    }

    public function get_method(){
        if(isset($this->method)){
            return $this->method;
        }
        return NULL;
    }

    public function get_url() {
        if(isset($this->url)){
            return $this->url;
        }
        return '';
    }

    public static function urlencode($input) {
        if (is_array($input)) {
            return array_map(array('DqCurl', 'urlencode'), $input);
        } else if (is_scalar($input)) {
            return urlencode($input);
        } else {
            return '';
        }
    }

    public static function urlencode_raw($input) {
        if (is_array($input)) {
            return array_map(array('DqCurl', 'urlencode_raw'), $input);
        } else if (is_scalar($input)) {
            return rawurlencode($input);
        } else {
            return '';
        }
    }

    public static function urlencode_rfc3986($input) {
        if (is_array($input)) {
            return array_map(array('DqCurl', 'urlencode_rfc3986'), $input);
        } else if (is_scalar($input)) {
            return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($input)));
        } else {
            return '';
        }
    }

    public static function fetch_curl_id($ch) {
        // TODO optimize by trim, rodin?
        preg_match('/[^\d]*(\d+)[^\d]*/', (string)$ch, $matches);
        return $matches[1];
    }

    /**
     * 拼装http查询串（不经过urlencode）
     */
    public static function http_build_query($query_data = array()){
        if(empty($query_data)){
            return '';
        }
        $pairs = array();
        foreach ($query_data as $key => $value){
            $pairs[] = "{$key}={$value}";
        }
        $query_string = implode("&", $pairs);
        return $query_string;
    }

    private function get_host_id() {
        return $this->host_name . ':' . $this->host_port;
    }

    private function curl_setopt() {
        curl_setopt($this->ch, CURLOPT_URL, $this->url);
        // -v
        curl_setopt($this->ch, CURLOPT_HEADER, true);

        if ($this->is_ssl) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            $this->curl_cli .= " -k";
        }

        if ($this->no_body) {
            curl_setopt($this->ch, CURLOPT_NOBODY, true);
        }

        if (!empty($this->req_range)) {
            curl_setopt($this->ch, CURLOPT_RANGE, $this->req_range[0]."-".$this->req_range[1]);
        }

        // -v
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        // default
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        // not use
        curl_setopt($this->ch, CURLOPT_USERAGENT, "Swift framework HttpRequest class");

        if ($this->debug) {
            // -v
            curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
        }

        if ($this->gzip) {
            curl_setopt($this->ch, CURLOPT_ENCODING, "gzip");
            $this->curl_cli .= " --compressed ";
        }

        //curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($this->ch, CURLOPT_MAXREDIRS, 1);
        //$this->curl_cli .= " --max-redirs 1";


        $version = curl_version();
        if (version_compare($version["version"], "7.16.2") < 0) {
            //如果timeout为0，则curl将wait indefinitely.故此处将意外设置timeout < 1sec的情况，重新
            //设置为1s
            $timeout = floor($this->connect_timeout / 1000);
            if($this->connect_timeout > 0 && $timeout <= 0){
                $timeout = 1;
            }
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
        } else {
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connect_timeout);
            curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        }
        unset($version);
        $this->curl_cli .= " --connect-timeout " . round($this->connect_timeout / 1000, 3);
        $this->curl_cli .= " -m " . round($this->timeout / 1000, 3);

        // -x
        if (!empty($this->actual_host_ip)) {
            curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($this->ch, CURLOPT_PROXY, $this->actual_host_ip);
            curl_setopt($this->ch, CURLOPT_PROXYPORT, $this->host_port);
            $this->curl_cli .= " -x " . $this->actual_host_ip . ":" . $this->host_port;
        }

        $this->load_cookies();
        $this->load_headers();
        $this->load_query_fields();
        $this->load_post_fields();
        $this->load_userpwd();

        if ($this->method) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, strtoupper($this->method));
            $this->curl_cli .= " -X \"{$this->method}\"";
        }
        $this->curl_cli .= " \"" . $this->url . ($this->query_string ? '?' . $this->query_string : '') . "\"";
    }

    private function load_userpwd() {
        if(is_null($this->user) || is_null($this->psw)) {
            return;
        }
        $str_userpwd = $this->user . ':' . $this->psw;
        $this->curl_cli .= "-u \"$str_userpwd\" ";
        curl_setopt($this->ch, CURLOPT_USERPWD, $str_userpwd);
    }

    private function load_cookies() {
        if (empty($this->cookies)) {
            return;
        }

        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        $cookie = implode('; ', $pairs);
        curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
        $this->curl_cli .= " -b \"" . $cookie . "\"";
    }

    private function load_headers() {
        if (empty($this->headers)) {
            return;
        }
        $headers = array();
        foreach ($this->headers as $k => $v) {
            $tmp = $k . ":" . $v;
            $this->curl_cli .= " -H '" . $tmp . "'";
            $headers[] = $tmp;
        }

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }

    private function load_query_fields() {
        $this->query_string = '';
        if (empty($this->query_fields)) {
            return;
        }

        foreach ($this->query_fields as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        if($pairs){
            $this->query_string = implode('&', $pairs);
        }
        curl_setopt($this->ch, CURLOPT_URL, $this->url . '?' . $this->query_string);
    }

    private function load_post_fields() {
        if (empty($this->post_fields)) {
            return;
        }
        if(true == $this->has_upload){
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->post_fields);
        }
        else{
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, self::http_build_query($this->post_fields));
        }
        foreach ($this->post_fields as $name => $value) {
            if ($this->has_upload) {
                $this->curl_cli .= " --form \"" . $name . '=' . $value . "\"";
            } else {
                $pairs[] = $name . '=' . $value;
            }
        }

        if (!empty($pairs)) {
            $this->curl_cli .= " -d \"" . implode('&', $pairs) . "\"";
        }
    }
}
