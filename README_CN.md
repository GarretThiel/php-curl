 # 关于
利用php-curl内置IO事件循环实现，具备高性能、高通用性、高扩展性，尤其适合复杂业务逻辑大批量请求的应用场景。

## 需求
PHP: >=5.3

## 安装
```
composer require ares333/php-curl
```

## 联系我们
QQ群：744854777

## 特性
1. 极低的CPU、内存使用率。
1. 所有curl接口全部直接暴露以实现高通用性和高扩展性。
1. API简洁易用。
1. 支持任务中断和恢复（例如进程退出，下次启动从任务中断的位置继续执行）。
1. 支持动态任务。
1. 支持透明文件缓存。
1. 支持失败任务自动重试。
1. 支持全局配置、任务配置、回调配置三个级别，优先级由低到高。
1. 所有配置可以运行中实时修改并生效。

## 运行机制
Curl::add()添加任务到任务池，Curl::start()开始执行任务并阻塞，执行过程中产生不同事件并调用对应的回调函数，事件包括任务完成、任务失败、状态信息处理、任务池任务不足等，任务池中所有任务完成之后结束阻塞状态。

## 快速入门
#*基本使用**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => 'http://baidu.com',
            CURLOPT_RETURNTRANSFER => true
        ),
        'args' => 'This is user argument'
    ),
    function ($r, $args) {
        echo "Request success for " . $r['info']['url'] . "\n";
        echo "\nHeader info:\n";
        print_r($r['info']);
        echo "\nRaw header:\n";
        print_r($r['header']);
        echo "\nArgs:\n";
        print_r($args);
        echo "\n\nBody size:\n";
        echo strlen($r['body']) . ' bytes';
        echo "\n";
    });
$curl->start();
```
**文件下载**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$url = 'https://www.baidu.com/img/bd_logo1.png';
$file = __DIR__ . '/download.png';
// $fp is closed automatically on download finished.
$fp = fopen($file, 'w');
$curl->add(
    array(
        'opt' => array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_HEADER => false
        ),
        'args' => array(
            'file' => $file
        )
    ),
    function ($r, $args) {
        if($r['info']['http_code']==200) {
            echo "download finished successfully, file=$args[file]\n";
        }else{
            echo "download failed\n";
        }
    })->start();
```
**大量任务**

任务可以动态添加，可以参考Curl::$onTask
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
$toolkit = new Toolkit();
$toolkit->setCurl();
$curl = $toolkit->getCurl();
$curl->maxThread = 1;
$curl->onTask = function ($curl) {
    static $i = 0;
    if ($i >= 50) {
        return;
    }
    $url = 'http://www.baidu.com';
    /** @var Curl $curl */
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i ++
            )
        ));
};
$curl->start();
```
**运行状态**
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
$curl = new Curl();
$toolkit = new Toolkit();
$curl->onInfo = array(
    $toolkit,
    'onInfo'
);
$curl->maxThread = 2;
$url = 'http://www.baidu.com';
for ($i = 0; $i < 100; $i ++) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i
            )
        ));
}
$curl->start();
```
命令行执行会输出运行状态，格式如下：
```
SPD    DWN  FNH  CACHE  RUN  ACTIVE  POOL  QUEUE  TASK  FAIL  
457KB  3MB  24   0      3    3       73    0      100   0
```
回调函数会接收到所有详细数据，默认回调中只显示了一部分常用状态数据，数据含义如下：
```
SPD：下载速度
DWN：已经下载的字节数
FNH：已经完成的请求数
CACHE：缓存命中数
RUN：运行中的任务数
ACTIVE：有IO活动的任务数
POOL：任务池中排队的任务数
QUEUE：请求已经完毕等待回调处理的任务数
TASK：加入过的任务总数
FAIL：超过自动重试次数之后失败的任务数
```
**自动缓存**
```PHP
use Ares333\Curl\Toolkit;
use Ares333\Curl\Curl;
$curl = new Curl();
$toolkit = new Toolkit();
$curl->onInfo = array(
    $toolkit,
    'onInfo'
);
$curl->maxThread = 2;
$curl->cache['enable'] = true;
$curl->cache['dir'] = __DIR__ . '/output/cache';
if (! is_dir($curl->cache['dir'])) {
    mkdir($curl->cache['dir'], 0755, true);
}
$url = 'http://www.baidu.com';
for ($i = 0; $i < 20; $i ++) {
    $curl->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url . '?wd=' . $i
            )
        ));
}
$curl->start();
```
第二次运行之后输出：
```
SPD  DWN  FNH  CACHE  RUN  ACTIVE  POOL  QUEUE  TASK  FAIL  
0KB  0MB  20   20     0    0       0     0      20    0
```
说明全部使用缓存，没有任何网络活动。

**动态任务**
```PHP
use Ares333\Curl\Curl;
$curl = new Curl();
$url = 'http://baidu.com';
$curl->add(array(
    'opt' => array(
        CURLOPT_URL => $url
    )
), 'cb1');
echo "add $url\n";
$curl->start();

function cb1($r)
{
    echo "finish " . $r['info']['url'] . "\n";
    $url = 'http://bing.com';
    $r['curl']->add(
        array(
            'opt' => array(
                CURLOPT_URL => $url
            )
        ), 'cb2');
    echo "add $url\n";
}

function cb2($r)
{
    echo "finish " . $r['info']['url'] . "\n";
}
```
输出如下：
```
add http://baidu.com
finish https://www.baidu.com/
add http://bing.com
finish http://cn.bing.com/
```
完成的url比添加时多了结尾的/，因为Curl内部做了3xx跳转（Curl::$opt[CURLOPT_FOLLOWLOCATION]=true)。
如果有大量任务需要执行可以设置Curl::onTask回调。

## Curl (src/Curl.php 核心功能) 
```PHP
public $maxThread = 10
```
最大并发数，这个值可以运行中动态改变。

```PHP
public $maxTry = 3
```
触发curl错误事件之前最大重试次数。

```PHP
public $opt = array ()
```
全局CURLOPT_\*，可以被任务配置覆盖。

```PHP
public $cache = array(
    'enable' => false,
    'compress' => 0, //级别0-9，如果启用压缩，6是一个不错的选择
    'dir' => null, //缓存目录，必须提前创建
    'expire' => 86400, //过期缓存不会被删除而是被忽略
    'verifyPost' => false //缓存id是否使用http post的值
);
```
全局配置，缓存id使用url生成，可以被任务配置和onSuccess回调函数的返回值覆盖。

```PHP
public $taskPoolType = 'queue'
```
有两个值stack或queue，这两个选项决定任务池是深度优先还是广度优先。

```PHP
public $onTask = null
```
当并发数小于Curl::$maxThread并且任务池为空的时候会被调用，当前Curl对象句柄作为回调函数的唯一参数。

```PHP
public $onInfo = null
```
运行状态信息回调，IO事件发生时调用，但是1秒钟最多调用一次，回调函数参数如下：
1. $info数组，包含两个键，all和running，running包含每个运行中任务的Response头（curl_getinfo()返回值)，all包含全局运行信息，包含的键如下：
    + $info['all']['downloadSpeed'] 下载速度。
    + $info['all']['bodySize'] 已经下载的Response消息体大小。
    + $info['all']['headerSize'] 已经下载的Response消息头大小。
    + $info['all']['activeNum'] 有IO活动的任务数。
    + $info['all']['queueNum'] 请求已经完毕等待回调处理的任务数。
    + $info['all']['finishNum'] 已经完成的请求数。
    + $info['all']['cacheNum'] 缓存命中数。
    + $info['all']['failNum'] 超过自动重试次数之后失败的任务数。
    + $info['all']['taskNum'] 加入过的任务总数。
    + $info['all']['taskRunningNum'] 运行中的任务数。
    + $info['all']['taskPoolNum'] 任务池中排队的任务数。
    + $info['all']['taskFailNum'] 失败重试中的任务数。
2. 当前Curl实例的句柄
3. bool值，是否是最后一次调用

```PHP
public $onEvent = null
```
网络IO事件发生时被调用，当前Curl对象句柄作为回调函数的唯一参数。

```PHP
public $onFail = null
```
全局失败回调，可以被任务回调覆盖，回调函数接收两个参数：
1. 数组，键值如下：
  + errorCode CURLE_* 常量的值。
  + errorMsg 错误描述。
  + info Response头信息。
  + curl 当前Curl对象句柄。
2. 添加任务时指定的$item['args']的值。

```PHP
public function add(array $item, $onSuccess = null, $onFail = null, $ahead = null)
```
添加一个任务到任务池
+ $item
    1. $item['opt']=array() 当前任务的CURLOPT_\*。
    2. $item['args'] 最终以参数形式传递给回调函数。
    3. $item['cache']=array() 任务缓存配置。
+ $onSuccess 任务正常完成后被调用
    + 回调函数的参数，一共两个：
        1. $result数组，键值如下：
            + $result['info'] Response头信息。
            + $result['curl'] 当前Curl对象句柄。
            + $result['body'] Response消息体，下载任务不包含这个键。
            + $result['header'] Response原始头信息，启用CURLOPT_HEADER之后才有这个键。
            + $result['cacheFile'] 读取的缓存数据才会包含这个键。
        2. $item['args']的值。
    + 回调函数的返回值（可选），数组形式，键值如下：
        1. cache 和Curl::$cache结构一致，控制当前任务的缓存配置。
+ $onFail 覆盖Curl::$onFail。
+ $ahead 是否加入优先队列，优先队列取完才会取普通任务池中的任务。

返回值：自身的引用。

```PHP
public function start()
```
开始进入事件循环，此方法是阻塞的。

```PHP
public function stop()
```
中断事件循环并返回未处理完成的任务。

```PHP
public function parseResponse($response)
```
从响应中解析http头和body。

```PHP
public function getCacheFile($url, $post = null)
```
生成缓存文件的相对路径。

## Toolkit (src/Toolkit.php 必要工具类) 
```PHP
function setCurl($curl = null)
```
可以通过参数传递一个自定义的Curl对象或子对象，如果不指定会自动创建一个默认对象。

默认对象会初始化Curl::$opt、Curl::onInfo、Curl::onFail，Curl::$opt初始值如下：
```PHP
array(
    CURLINFO_HEADER_OUT => true,
    CURLOPT_HEADER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_AUTOREFERER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_MAXREDIRS => 5
)
```

```PHP
function onFail($error, $args)
```
默认错误处理回调，参数详情见Curl::$onFail。

```PHP
function onInfo($info)
```
默认信息回调，以标准形式输出运行信息，参数详情见Curl::onInfo。

此方法也可以手动调用，传递一个字符串参，字符串会被加入输出缓冲，和直接输出相比的好处是可以避免shell控制字符的影响。

```PHP
function htmlEncode($html, $in = null, $out = 'UTF-8', $mode = 'auto')
```
强力的全自动转码函数，可以自动获取当前编码，转码后自动修改\<head\>\</head\>中的编码。
参数：
+ $html 完整的html字符串。
+ $in 如果确定当前编码则指定。
+ $out 需要转换的目标编码。
+ $mode auto|iconv|mb_convert_encoding，auto自动选择转码函数，否则手动指定一个。

返回值：
转码后的html字符串。

```PHP
function isUrl($url)
```
是否是一个绝对的url，返回bool类型。

```PHP
function formatUrl($url)
```
替换空格为+号，去除空白，协议、主机名转换成小些形式，去除$url中的锚点。

```PHP
function buildUrl(array $parse)
```
parse_url()的反函数。

```PHP
function uri2url($uri, $urlCurrent)
```
根据当前页面url获取当前页中的相对uri对应的绝对url，$urlCurrent应该是经过3xx重定向之后的值。

```PHP
function url2uri($url, $urlCurrent)
```
根据当前页面url获取当前页中的绝对url对应的相对uri，$url应该是经过3xx重定向之后的值。

```PHP
function url2dir($url)
```
绝对url对应的目录，$url应该是经过3xx重定向之后的值。

```PHP
function url2absolute($url, $urlCurrent)
```
合并基础url和相对url为绝对url。

```PHP
function urlRemoveDotSegments($path)
```
过滤url中的"."和".."。

```PHP
function getCurl()
```
返回核心类的实例句柄。

## 捐赠

![wechat](https://raw.githubusercontent.com/ares333/php-curl/master/wechat.jpg)
![alipay](https://raw.githubusercontent.com/ares333/php-curl/master/alipay.jpg)

|  日期   | 金额  |  名称   | 方式  |
|  ----  | ----  |  ----  | ----  |
| 2020-03-06  | ¥100 | *禹  | 微信 |
| 2020-01-10  | ¥200 | **J  | 支付宝 |
 