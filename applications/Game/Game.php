<?php
/**
 * 游戏服务器
 *
 * @auther moxiaobai
 * @since  2014/11/13
 *
 */

require_once ROOT_DIR . '/Clients/StatisticClient.php';

use \Lib\Gateway;
use \Lib\StatisticClient;
use \Protocols\GatewayProtocol;
use \Structure\PbCmd;
use \Structure\PbResult;
use \Server\Member;


class Game {

    /**
     * 当网关有客户端链接上来时触发，每个客户端只触发一次，如果不许要任何操作可以不实现此方法
     * 这里当客户端一连上来就给客户端发送输入名字的提示
     */
    public static function onGatewayConnect($client_id)
    {
        //Gateway::sendToCurrentClient(TextProtocol::encode("type in your name:"));
    }

    /**
     * 网关有消息时，判断消息是否完整
     */
    public static function onGatewayMessage($buffer)
    {
        // 已经收到的长度（字节）
        $recv_length = strlen($buffer);

        // 接收到的数据长度不够？
        if($recv_length<4)
        {
            return 4 - $recv_length;
        }


        // 读取首部4个字节，网络字节序int
        $buffer_data = unpack('Ntotal_length', $buffer);
        // 得到这次数据的整体长度（字节）
        $total_length = $buffer_data['total_length'];
        if($total_length>$recv_length)
        {
            // 还有这么多字节要接收
            return $total_length - $recv_length;
        }
        // 接收完毕
        return 0;
    }

    /**
     * 有消息时触发该方法
     * @param int $client_id 发消息的client_id
     * @param string $message 消息
     * @return void
     */
    public static function onMessage($client_id, $message)
    {

        $message = substr($message, 4);

        //解析传输的数据
        $cmd = new PbCmd();

        try {
            $cmd->parseFromString($message);
        } catch(Exception $ex) {
            return;
        }

        //接收参数
        $obj     = $cmd->getObj();
        $method  = $cmd->getMethod();
        $params  = $cmd->getParams();

        $class = substr($obj, 8);
        StatisticClient::tick($class, $method);
        try {
            $instance = new $obj();
            $buffer = $instance->$method($params);

            StatisticClient::report($class, $method, 1, 0, 'successful');
        } catch(Exception $ex) {
            $pbResult = new PbResult();

            $pbResult->setCode($ex->getCode());
            $pbResult->setMsg($ex->getMessage());
            $buffer = $pbResult->SerializeToString();
            $total_length = 4 + strlen($buffer);
            $buffer = pack('N', $total_length) . $buffer;

            StatisticClient::report($class, $method, 0, $ex->getCode(), $ex->getMessage());
        }

        echo 'Game_Worker' . PHP_EOL;

        return Gateway::sendToCurrentClient($buffer);

    }

    /**
     * 当用户断开连接时触发的方法
     * @param integer $client_id 断开连接的用户id
     * @return void
     */
    public static function onClose($client_id)
    {
        // 广播 xxx 退出了
        //GateWay::sendToAll(TextProtocol::encode("{$_SESSION['name']}[$client_id] logout"));
    }
}