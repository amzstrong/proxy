<?php

class ProxyServer {
    private $_client = [];
    private $_server;
    
    
    protected function log($message) {
        echo $message . PHP_EOL;
        return;
    }
    
    protected function init() {
        $this->_server = new swoole_server("0.0.0.0", 9509);
        $this->_server->set([
            "worker_num" => 6
        ]);
    }
    
    public function httpResponse($header = [], $data = '') {
        $response = array('HTTP/1.1 200');
        $headers  = array(
            'Server' => 'SwooleServer',
            'Content-Type' => 'text/html;charset=utf8',
            'Content-Length' => strlen($data),
        );
        $headers  = array_merge($headers, $header);
        foreach ($headers as $key => $val) {
            $response[] = $key . ':' . $val;
        }
        $response[] = '';
        $response[] = $data;
        $send_data  = join("\r\n", $response);
        return $send_data;
    }
    
    public function run() {
        
        $this->init();
        
        $this->_server->on('connect', function ($server, $fd) {
            $this->log("Server connection open: {$fd}");
        });
        $this->_server->on('receive', function ($server, $fd, $reactor_id, $buffer) {
            
            $res = preg_match('/proxy-check:(.*)\n/', $buffer, $match);
            if ($res && isset($match[1])) {
                $check_code = base64_decode($match[1]);
                if ($check_code !== "test:test") {
                    $this->_server->send($fd, $this->httpResponse([], 'account  error'));
                    return;
                }
            }
            
            //判断是否为新连接
            if (!isset($this->_client[$fd])) {
                //判断代理模式
                list($method, $url) = explode(' ', $buffer, 3);
                $url = parse_url($url);
                $this->log("协议:" . $method);
                //解析host+port
                $host = $url['host']??"";
                $port = $url['port']??80;
                
                $this->_client[$fd] = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
                
                if ($method == 'CONNECT') {
                    $this->_client[$fd]->on("connect", function (swoole_client $client) use ($fd) {
                        $this->log("隧道模式-连接成功!");
                        //告诉客户端准备就绪，可以继续发包
                        $this->_server->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
                    });
                } else {
                    $this->_client[$fd]->on("connect", function (swoole_client $client) use ($buffer) {
                        $this->log("正常模式-连接成功!");
                        //直接转发数据
                        $client->send($buffer);
                    });
                }
                
                $this->_client[$fd]->on("receive", function (swoole_client $client, $buffer) use ($fd) {
                    //将收到的数据转发到客户端
                    if ($this->_server->exist($fd)) {
                        $this->_server->send($fd, $buffer);
                    }
                });
                
                $this->_client[$fd]->on("error", function (swoole_client $client) use ($fd) {
                    $this->log("Client {$fd} error");
                });
                
                $this->_client[$fd]->on("close", function (swoole_client $client) use ($fd) {
                    $this->log("Client {$fd} connection close");
                });
                
                $this->_client[$fd]->connect($host, $port);
            } else {
                //已连接，正常转发数据
                if ($this->_client[$fd]->isConnected()) {
                    $this->_client[$fd]->send($buffer);
                }
            }
        });
        
        $this->_server->on('close', function ($server, $fd) {
            $this->log("Server connection close: {$fd}");
            unset($this->_client[$fd]);
        });
        
        $this->_server->start();
    }
}


$server = new ProxyServer();
$server->run();