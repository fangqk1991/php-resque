# 简介
PHP 异步任务，定时任务，循环任务。

本工程借鉴了 [chrisboulton / php-resque](https://github.com/chrisboulton/php-resque) 的代码实现。

### 一些设计
* 在等待任务方面，本工程完全采用 blpop 的方式，避免轮询引发的任务执行延时问题
* 采用 FCMaster -> FCLeader -> Workers 的组织方式，Master 管理若干 Leader，每个 Leader 为独立进程，在收到任务时，fork 自身成为 Worker 执行任务，待 Worker 执行完毕退出，Leader 继续运行等待下一任务。
* 对于每个 Leader，可根据需要设置 Worker 数量

### 运行要求
* PHP 5.5+
* Redis 2.2+
* [Composer](https://getcomposer.org)

### 安装
编辑 `composer.json`，将 `fang/php-resque` 加入其中

```
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/fangqk1991/php-resque"
    }
  ],
  ...
  ...
  "require": {
    "fang/php-resque": "~0.2"
  }
}

```

执行命令

```
composer install
```

### 使用
1. 建立配置文件，见 [示例](https://github.com/fangqk1991/php-resque/blob/master/demos/resque-demo.json)
2. 建立启动脚本，启动脚本支持 start / stop / restart / status 命令，见 [示例](https://github.com/fangqk1991/php-resque/blob/master/demos/launcher-demo.php)
3. 建立 Task 类，并实现 `IResqueTask` 接口，见 [示例](https://github.com/fangqk1991/php-resque/blob/master/demos/tasks/TaskDemo.php)
4. 工程中调用，见 [示例](https://github.com/fangqk1991/php-resque/blob/master/demos/application-demo.php)
5. 如需使用定时任务、循环任务，见 [示例](https://github.com/fangqk1991/php-resque/blob/master/demos/schedule-demo.php)

### 调用示例
#### launcher-demo.php

```
$launchFile = $argv[0];
$cmd = isset($argv[1]) ? $argv[1] : '';

// 配置文件中的 ${__DIR__} 代表配置文件所在的文件目录
$launcher = new FCLauncher($launchFile, __DIR__ . '/resque-demo.json');
$launcher->handle($cmd);
```

```
# 启动任务
./launcher-demo.php start
```

#### TaskDemo.php
```
class TaskDemo implements IResqueTask
{
    public function perform($params)
    {
        ...
    }
}
```

#### 常规调用
```
require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Core\Resque;
use FCResque\Demos\Tasks\TaskDemo;

// Redis 地址需要和 resque-demo.json 一致
Resque::setBackend('127.0.0.1:6379');

// 两种调用形式
Resque::enqueue('TaskQueueDemo', TaskDemo::class, ['msg' => 'Hello.']);
Resque::enqueue('TaskQueueDemo', '\FCResque\Demos\Tasks\TaskDemo', ['msg' => 'Hello again.']);

```
