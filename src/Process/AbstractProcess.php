<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 01:41
 */

namespace EasySwoole\Component\Process;
use EasySwoole\Component\Timer;
use Swoole\Process;

abstract class AbstractProcess
{
    private $swooleProcess;
    private $processName;
    private $async = null;
    private $args = [];

    final function __construct(string $processName,array $args = [],$async = true)
    {
        $this->async = $async;
        $this->args = $args;
        $this->processName = $processName;
        $this->swooleProcess = new \swoole_process([$this,'__start']);
    }

    public function getProcess():Process
    {
        return $this->swooleProcess;
    }

    /*
     * 仅仅为了提示:在自定义进程中依旧可以使用定时器
     */
    public function addTick($ms,callable $call):?int
    {
        return Timer::getInstance()->loop(
            $ms,$call
        );
    }

    public function clearTick(int $timerId):?int
    {
        return Timer::getInstance()->clear($timerId);
    }

    public function delay($ms,callable $call):?int
    {
        return Timer::getInstance()->after($ms,$call);
    }

    /*
     * 服务启动后才能获得到pid
     */
    public function getPid():?int
    {
        if(isset($this->swooleProcess->pid)){
            return $this->swooleProcess->pid;
        }else{
            return null;
        }
    }

    function __start(Process $process)
    {
        if(PHP_OS != 'Darwin'){
            $process->name($this->getProcessName());
        }

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
        }

        Process::signal(SIGTERM,function ()use($process){
            $this->onShutDown();
            swoole_event_del($process->pipe);
            $this->swooleProcess->exit(0);
        });
        if($this->async){
            swoole_event_add($this->swooleProcess->pipe, function(){
                $msg = $this->swooleProcess->read(64 * 1024);
                $this->onReceive($msg);
            });
        }
        try{
            $this->run($this->swooleProcess);
        }catch (\Throwable $throwable){
            $this->onException($throwable);
        }
    }

    public function getArgs():array
    {
        return $this->args;
    }

    public function getArg($key)
    {
        if(isset($this->args[$key])){
            return $this->args[$key];
        }else{
            return null;
        }
    }

    public function getProcessName()
    {
        return $this->processName;
    }

    protected function onException(\Throwable $throwable){
        throw $throwable;
    }

    public abstract function run(Process $process);
    public abstract function onShutDown();
    public abstract function onReceive(string $str);
}