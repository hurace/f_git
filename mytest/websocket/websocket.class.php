<?php
/*
������websocket($config);
$config�ṹ:
$config=array(
  'address'=>'192.168.0.200',//�󶨵�ַ
  'port'=>'8000',//�󶨶˿�
  'event'=>'WSevent',//�ص������ĺ�����
  'log'=>true,//��������ʾ��¼
);
 
�ص������������ݸ�ʽ
function WSevent($type,$event)
 
$type�ַ��� �¼���������������
in  �ͻ��˽���
out �ͻ��˶Ͽ�
msg �ͻ�����Ϣ����
��ΪСд
 
$event ����
$event['k']�����û��б���userid;
$event['sign']�ͻ���ʾ
$event['msg']�յ�����Ϣ $type='msg'ʱ���и���Ϣ
 
����:
run()����
search(��ʾ)����ȡ�øñ�ʾ��id
close(��ʾ)�Ͽ�����
write(��ʾ,��Ϣ)������Ϣ
idwrite(id,��Ϣ)������Ϣ
 
����:
$users �ͻ��б�
�ṹ:
$users=array(
[�û�id]=>array('socket'=>[��ʾ],'hand'=[�Ƿ�����-����ֵ]),
[�û�id]=>arr.....
)
*/
 
class websocket{
    public $log;
    public $event;
    public $signets;
    public $users;  
    public $master; 
    public function __construct($config){
        if (substr(php_sapi_name(), 0, 3) !== 'cli') {
            die("��ͨ��������ģʽ����!");
        }
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush();
        $this->event = $config['event'];
        $this->log = $config['log'];
        $this->master=$this->WebSocket($config['address'], $config['port']);
        $this->sockets=array('s'=>$this->master);
    }
    function WebSocket($address,$port){
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $address, $port);
        socket_listen($server);
        $this->log('��ʼ����: '.$address.' : '.$port);
        return $server;
    }
  function run(){
    while(true){
      $changes=$this->sockets;
      @socket_select($changes,$write=NULL,$except=NULL,NULL);
      foreach($changes as $sign){
        if($sign==$this->master){
          $client=socket_accept($this->master);
          $this->sockets[]=$client;
          $user = array(
            'socket'=>$client,
            'hand'=>false,
          );
          $this->users[] = $user;
          $k=$this->search($client);
          $eventreturn = array('k'=>$k,'sign'=>$sign);
          $this->eventoutput('in',$eventreturn);
        }else{
          $len=socket_recv($sign,$buffer,2048,0);
          $k=$this->search($sign);
          $user=$this->users[$k];
          if($len<7){
            $this->close($sign);
            $eventreturn = array('k'=>$k,'sign'=>$sign);
            $this->eventoutput('out',$eventreturn);
            continue;
          }
          if(!$this->users[$k]['hand']){//û�����ֽ�������
            $this->handshake($k,$buffer);
          }else{
            $buffer = $this->uncode($buffer);
            $eventreturn = array('k'=>$k,'sign'=>$sign,'msg'=>$buffer);
            $this->eventoutput('msg',$eventreturn);
          }
        }
      }
    }
  }
  function search($sign){//ͨ����ʾ������ȡid
    foreach ($this->users as $k=>$v){
      if($sign==$v['socket'])
      return $k;
    }
    return false;
  }
  function close($sign){//ͨ����ʾ�Ͽ�����
    $k=array_search($sign, $this->sockets);
    socket_close($sign);
    unset($this->sockets[$k]);
    unset($this->users[$k]);
  }
  function handshake($k,$buffer){
    $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
    $key  = trim(substr($buf,0,strpos($buf,"\r\n")));
    $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
    $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
    $new_message .= "Upgrade: websocket\r\n";
    $new_message .= "Sec-WebSocket-Version: 13\r\n";
    $new_message .= "Connection: Upgrade\r\n";
    $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
    socket_write($this->users[$k]['socket'],$new_message,strlen($new_message));
    $this->users[$k]['hand']=true;
    return true;
  }
  function uncode($str){
    $mask = array();  
    $data = '';  
    $msg = unpack('H*',$str);  
    $head = substr($msg[1],0,2);  
    if (hexdec($head{1}) === 8) {  
      $data = false;  
    }else if (hexdec($head{1}) === 1){  
      $mask[] = hexdec(substr($msg[1],4,2));
      $mask[] = hexdec(substr($msg[1],6,2));
      $mask[] = hexdec(substr($msg[1],8,2));
      $mask[] = hexdec(substr($msg[1],10,2));
      $s = 12;  
      $e = strlen($msg[1])-2;  
      $n = 0;  
      for ($i=$s; $i<= $e; $i+= 2) {  
        $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));  
        $n++;  
      }  
    }  
    return $data;
  }
    function code($msg){
      $msg = preg_replace(array('/\r$/','/\n$/','/\r\n$/',), '', $msg);
      $frame = array();  
      $frame[0] = '81';  
      $len = strlen($msg);  
      $frame[1] = $len<16?'0'.dechex($len):dechex($len);
      $frame[2] = $this->ord_hex($msg);
      $data = implode('',$frame);
      return pack("H*", $data);
    }
    function ord_hex($data)  {  
      $msg = '';  
      $l = strlen($data);  
      for ($i= 0; $i<$l; $i++) {  
        $msg .= dechex(ord($data{$i}));  
      }  
      return $msg;  
    }
 
    function idwrite($id,$t){//ͨ��id������Ϣ
      if(!$this->users[$id]['socket']){return false;}//û�������ʾ
      $t=$this->code($t);
      return socket_write($this->users[$id]['socket'],$t,strlen($t));
    }
    function write($k,$t){//ͨ����ʾ������Ϣ
      $t=$this->code($t);
      return socket_write($k,$t,strlen($t));
    }
    function eventoutput($type,$event){//�¼��ص�
      call_user_func($this->event,$type,$event);
    }
    function log($t){//����̨���
      if($this->log){
        $t=$t."\r\n";
        fwrite(STDOUT, iconv('utf-8','gbk//IGNORE',$t));
      }
    }
}