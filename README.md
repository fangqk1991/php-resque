# php-resque
本工程借鉴了 [chrisboulton / php-resque](https://github.com/chrisboulton/php-resque) 的代码实现。

### 一些设计
* 在等待任务方面，本工程完全采用 blpop 的方式，避免轮询引发的任务执行延时问题
* 采用 FCMaster -> FCLeader -> Workers 的组织方式，Master 管理若干 Leader，每个 Leader 为独立进程，在收到任务时，fork 自身成为 Worker 执行任务，待 Worker 执行完毕退出，Leader 继续运行等待下一任务。

### 目录 / 文件说明
* **examples**: 一些实例
* **config.local**: 本地配置目录，可根据实际情况将编辑相关配置文件内容
* **library**: 核心代码
* **php-resque.php**: php-resque 调用脚本

### 运行要求
* PHP 5.4+
* Redis 2.2+
* Composer

### 应用工程使用
#### 0. 在应用工程 composer.json 中添加 php-resque
```
{
    "require": {
        "fang/php-resque": "dev-master"
    }
}
```

#### 1. 安装依赖库
```
composer install
```

#### 2. 启动服务
* 参考 `php-resque-example.php` 建立启动脚本及相关配置文件
* 执行启动脚本

#### 3. 任务定义
* 任务文件需要继承 `IResqueTask`，参考 `SomeTask` / `SomeTask2`

#### 4. 使用案例
可参考 `MyResqueEx.php` 和 `enqueue.php` 调用

```
MyResqueEx::enqueue('TASK_1', '\FC\Example\SomeTask', array('delay' => 10));
MyResqueEx::enqueue('TASK_2', 'SomeTask2', array('arg-2' => 'arg-2'));
```