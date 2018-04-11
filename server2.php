<?php
class Sock
{
    public $sockets; //socket的連接池，即client連接進來的socket標記
    public $users;  //所有client連接進來的訊息，包括socket、client名字等
    public $master; //socket的resource，即前期初始化socket時返回的socket資源

    public function __construct($address, $port)
    {
    //創建socket並把保存socket資源在$this->master
        $this->master = $this->WebSocket($address, $port);
        $this->sockets = array($this->master);
    }
}
//傳相應的IP與埠進行創建socket操作
function WebSocket($address, $port)
{   //建立連線
    $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    //1表示接受所有的數據包 設定socket選項
    socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
    //綁定ip port
    socket_bind($server, $address, $port);
    //進行監聽
    socket_listen($server);
    $this->e('Server Started : ' . date('Y-m-d H:i:s'));
    $this->e('Listening on  : ' . $address . ' port ' . $port);
    return $server;
}


//對創建的socket循環進行監聽，處理數據
//對創建的socket循環進行監聽，處理數據
function run()
{
    //循環，直到socket斷開
    while (true) {
        socket_select($changes, $write, $except, null);
         //循環取client傳過來的資料
        foreach ($changes as $sock) {
             //如果有新的client連接進來，則
            if ($sock == $this->master) {
              //接受一個socket連接
                $client = socket_accept($this->master);
              //给新連接進來的socket一個唯一的ID
                $key = uniqid();
                $this->sockets[] = $client; //將新連接進來的socket存進連接池
                $this->users[$key] = array(
                    'socket' => $client, //紀錄新連接進來client的socket訊息
                    'shou' => false    //標記該socket資源沒有完成握手
                );
            } else {
                $len = 0;
                $buffer = '';
              //讀取該socket的訊息，注意：第二個參數是引用傳參即接收數據，第三個參數是接收數據的長度
                do {
                    $l = socket_recv($sock, $buf, 1000, 0);
                    $len += $l;
                    $buffer .= $buf;
                } while ($l == 1000);

              //根據socket在user池裡面查找相應的$k,即ID
                $k = $this->search($sock);
              //如果接收的訊息長度小於7，則該client的socket為斷開連接

                if ($len < 7) {
                  //给該client的socket進行斷開操作，並在$this->sockets和$this->users裡面進行删除
                    $this->send2($k);
                    continue;
                }
              //判断該socket是否已經握手
                if (!$this->users[$k]['shou']) {
              //如果沒有握手，則進行握手處理
                    $this->woshou($k, $buffer);
                } else {
              //走到這裡就是該client發送訊息了，對接受到的訊息進行uncode處理
                    $buffer = $this->uncode($buffer, $k);
                    if ($buffer == false) {
                        continue;
                    }
              //如果不為空，則進行消息推送操作
                    $this->send($k, $buffer);
                }
            }
        }
    }
}

//根據sock在users裡面查找相應的$k
function search($sock)
{
    foreach ($this->users as $k => $v) {
        if ($sock == $v['socket'])
            return $k;
    }
    return false;
}

 /*
 * 函數說明：對client的請求進行回應，即握手操作
 * @$k clien的socket對應的鍵，即每個用戶有唯一$k並對應socket
 * @$buffer 接收client請求的所有訊息
 */
function woshou($k, $buffer)
{

    //截取Sec-WebSocket-Key的值並加密
    $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);

    $key = trim(substr($buf, 0, strpos($buf, "\r\n")));
    $new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

    //按照協議组合訊息進行返回
    $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
    $new_message .= "Upgrade: websocket\r\n";
    $new_message .= "Sec-WebSocket-Version: 13\r\n";
    $new_message .= "Connection: Upgrade\r\n";
    $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
    socket_write($this->users[$k]['socket'], $new_message, strlen($new_message));

    //對已經握手的client做標記
    $this->users[$k]['shou'] = true;
    return true;

}

//用戶加入或client發送訊息
function send($k, $msg)
{
    //將查詢字符串解析到第二個參數變量中，以數組的形式保存如：parse_str("name=Bill&age=60",$arr)
    parse_str($msg, $g);
    $ar = array();

    if ($g['type'] == 'add') {
      //第一次進入添加聊天名字，把姓名保存在相應的users裡面
        $this->users[$k]['name'] = $g['ming'];
        $ar['type'] = 'add';
        $ar['name'] = $g['ming'];
        $key = 'all';
    } else {
      //發送訊息行為，其中$g['key']表示面對大家還是個人，是前段傳過來的訊息
        $ar['nrong'] = $g['nr'];
        $key = $g['key'];
    }
    //推送信息
    $this->send1($k, $ar, $key);
}

//$k 發訊息人的socketID $key接受人的 socketID ，根據這個socketID可以查找相應的client進息訊息發送，即指定client進行發送
function send1($k, $ar, $key = 'all')
{
    $ar['code1'] = $key;
    $ar['code'] = $k;
    $ar['time'] = date('m-d H:i:s');
    //對發送訊息進行編碼處理
    $str = $this->code(json_encode($ar));
    //面對家即所有在線者發送訊息
    if ($key == 'all') {
        $users = $this->users;
      //如果是add表示新加的client
        if ($ar['type'] == 'add') {
            $ar['type'] = 'madd';
            $ar['users'] = $this->getusers();    //取出所有在線者，用於顯示在在線用戶列表中
            $str1 = $this->code(json_encode($ar)); //單獨對新client進行編碼處理，數據不一樣
        //對新client自己單獨發送，因為有些數據是不一樣的
            socket_write($users[$k]['socket'], $str1, strlen($str1));
        //上面已經對client自己單獨發送的，後面就無需再次發送，故unset
            unset($users[$k]);
        }
      //除了新client外，對其他client進行發送訊息。數據量大時，就要考慮延時等問題了
        foreach ($users as $v) {
            socket_write($v['socket'], $str, strlen($str));
        }
    } else {
      //單獨對個人發送訊息，即雙方聊天
        socket_write($this->users[$k]['socket'], $str, strlen($str));
        socket_write($this->users[$key]['socket'], $str, strlen($str));
    }
}

//用戶退出 推撥訊息
function send2($k)
{
    $this->close($k);
    $ar['type'] = 'rmove';
    $ar['nrong'] = $k;
    $this->send1(false, $ar, 'all');
} 

//指定關閉$k對應的socket
function close($k)
{
    //斷開相應socket
    socket_close($this->users[$k]['socket']);
    //删除相應的user訊息
    unset($this->users[$k]);
    //重新定義sockets連接池
    $this->sockets = array($this->master);
    foreach ($this->users as $v) {
        $this->sockets[] = $v['socket'];
    }

}