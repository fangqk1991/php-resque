# php-resque
异步任务 master 的组织形式可能是

1. 每个应用各有一个 master
2. 整个操作系统只有一个 master
3. 操作系统可以存在多个 master，每个 master 管辖若干应用

实际应用中，形式 3 较为合理，因为部分应用间存在事件通信的情景不在少数。

不同 master 依赖的配置、各类数据应完全独立，如采用不同的 Redis 端口、pid 文件、log 文件。

本工程基于 [chrisboulton / php-resque](https://github.com/chrisboulton/php-resque)，在此基础上建立单 master 对多应用的调用形式，以及相关的调用脚本。

### 目录 / 文件说明
* **config.examples**: 配置示例文件目录，请将所有文件复制到 config.local 目录中，并删去 .example 后缀
* **config.local**: 本地配置目录，请根据实际情况将编辑相关配置文件内容
* **library**: 核心代码
* **php-resque-master.sh**: php-resque 调用脚本

### 运行要求
* PHP 5.3+
* Redis 2.2+
* Composer

### 使用
#### 1. 安装依赖库
```
composer install
```

#### 2. 编辑 `config.local/resque.main.config`


#### 3. 编辑 `config.local/ResqueMainJobs.php`


