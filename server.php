<?php
ob_implicit_flush();
$addrss = '127.0.0.1';
$port = 9111;
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $addrss, $port);
socket_listen($server, 10);

$clients[] = $server;
tip("server started and listening on $port $server\n");

$blank = false;
while (true) {
    socket_select($clients, $write = null, $except = null, null);
//echo "e\n";
    foreach ($clients as $k => $sock) {
//连接主机的client
        if ($sock == $server) {
            $client = socket_accept($server);
            if ($client < 0) {
                echo 'socket_accept() failed\n';
                continue;
            } else {
                $clients[] = $client;
                echo "connect client\n";
                continue;
            }
        } else {
//$len = socket_recv($sock , $buffer , 2048 , 0);
            $len = socket_recv($sock, $buffer, 2048, 0);
            if ($len < 7) {
                unset($clients[$k]);
                continue;
            }
            if (!$blank) {
                $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
                $key = trim(substr($buf, 0, strpos($buf, "\r\n")));

                $new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

                $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
                $new_message .= "Upgrade: websocket\r\n";
                $new_message .= "Sec-WebSocket-Version: 13\r\n";
                $new_message .= "Connection: Upgrade\r\n";
                $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
                socket_write($sock, $new_message, strlen($new_message));
                $blank = true;
            } else {//echo 11111111111111111111111111111;die;
                $str = decode($buffer);
                echo "received data:$str\n";
                $msg = 'hello client';
                $msg = code($msg);
                socket_write($sock, $msg, strlen($msg));
            }

        }
//$client = socket_accept($server);
//$buffer = socket_read($client, 8192);
    }
}
//}
function tip($tip)
{
    $tip = date('Y-m-d H:i:s') . ' : ' . $tip;
    echo iconv('utf-8', 'gbk//IGNORE', $tip);
}

function decode($buffer)
{
    $len = $masks = $data = $decoded = null;
    $len = ord($buffer[1]) & 127;

    if ($len === 126) {
        $masks = substr($buffer, 4, 4);
        $data = substr($buffer, 8);
    } else if ($len === 127) {
        $masks = substr($buffer, 10, 4);
        $data = substr($buffer, 14);
    } else {
        $masks = substr($buffer, 2, 4);
        $data = substr($buffer, 6);
    }
    for ($index = 0; $index < strlen($data); $index++) {
        $decoded .= $data[$index] ^ $masks[$index % 4];
    }
    return $decoded;
}

function code($msg)
{
    $msg = preg_replace(array('/\r$/', '/\n$/', '/\r\n$/', ), '', $msg);
    $frame = array();
    $frame[0] = '81';
    $len = strlen($msg);
    $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
    $frame[2] = ord_hex($msg);
    $data = implode('', $frame);
    return pack("H*", $data);
}

function ord_hex($data)
{
    $msg = '';
    $l = strlen($data);
    for ($i = 0; $i < $l; $i++) {
        $msg .= dechex(ord($data {
            $i}));
    }
    return $msg;
}
