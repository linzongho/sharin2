<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use \Workerman\Worker;
use \Server\Utils;
use \Server\Player;
use \Server\WorldServer;

// BrowserQuest Server
//$ws_worker = new Worker('Websocket://0.0.0.0:8000');
include_once '../../../index.php';
$ws_worker = \Sharin\Library\Workman::getWorker('Websocket://0.0.0.0:8000');
$ws_worker->name = 'BrowserQuestWorker';
$ws_worker->onWorkerStart = function($ws_worker)
{
    $ws_worker->server = new \Server\Server();
    $ws_worker->config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    $ws_worker->worlds = array();
    
    foreach(range(0, $ws_worker->config['nb_worlds']-1) as $i)
    {
        $world = new WorldServer('world'. ($i+1), $ws_worker->config['nb_players_per_world'], $ws_worker);
        $world->run($ws_worker->config['map_filepath']);
        $ws_worker->worlds[] = $world;
    }
};

$ws_worker->onConnect = function($connection) use ($ws_worker)
{
    $connection->server = $ws_worker->server;
    if(isset($ws_worker->server->connectionCallback))
    {
        call_user_func($ws_worker->server->connectionCallback);
    }
    $world = Utils::detect($ws_worker->worlds, function($world)use($ws_worker) 
    {
        return $world->playerCount < $ws_worker->config['nb_players_per_world'];
    });
    $world->updatePopulation(null);
    if($world && isset($world->connectCallback))
    {
        call_user_func($world->connectCallback, new Player($connection, $world));
    }
};

// 这里使用workerman的WebServer运行Web目录。Web目录也可以用nginx/Apache等容器运行
//$web = new WebServer("http://0.0.0.0:8787");
$web = \Sharin\Library\Workman::getWebServer("http://0.0.0.0:8787");
$web->count = 6;

$web->name = 'BrowserQuestWeb';

$web->addRoot('www.your_domain.com', __DIR__.'/Web');

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
