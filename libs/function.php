<?php
require_once __DIR__ . '/../config/config.php';

// 判断GIF图片是否为动态
function isAnimatedGif($filename)
{
	$fp = fopen($filename, 'rb');
	$filecontent = fread($fp, filesize($filename));
	fclose($fp);
	return strpos($filecontent, chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0') === FALSE ? 0 : 1;
}

// 校验登录
function checkLogin()
{
	global $config;
	$md5Pwd = md5($config['password']);
	if (isset($_POST['password'])) {	// 获取登录密码
		$postPW = $_POST['password'];
		if ($md5Pwd == $postPW) {	// 登录密码正确
			setcookie('admin', $postPW, time() + 3600 * 24 * 14, '/');
			echo '
			<script> new $.zui.Messager("登录成功", {type: "success" // 定义颜色主题 
			}).show();</script>';
			header("refresh:1;"); // 1s后刷新当前页面
		} else {	// 密码错误
			echo '
			<script> new $.zui.Messager("密码错误", {type: "danger" // 定义颜色主题 
			}).show();</script>';
			//exit(include __DIR__ . '/login.php');
			exit(header("refresh:1;"));
		}
	} elseif (isset($_COOKIE['admin'])) {	// cookie正确
		if ($_COOKIE['admin'] == $md5Pwd) {
		} else {	// cookie错误
			echo '
			<script> new $.zui.Messager("密码已更改，请重新登录", {type: "special" // 定义颜色主题 
			}).show();</script>';
			//header('loction:login.php');
			exit(include __DIR__ . '/login.php');
		}
	} else {	// 无登录无cookie
		echo '
			<script> new $.zui.Messager("请登录后再上传！", {type: "danger" // 定义颜色主题 
			}).show();</script>';
		//header('loction:login.php');
		exit(include __DIR__ . '/login.php');
	}
}

// 仅允许登录后上传
function mustLogin()
{
	global $config;
	if ($config['mustLogin']) {
		checkLogin();
	}
}

// 检查配置文件中目录是否存在是否可写并创建相应目录
function config_path($path = null)
{
	global $config;
	// php5.6 兼容写法：
	$path = isset($path) ? $path : date('Y/m/d/');
	// php7.0 $path = $path ?? date('Y/m/d/');
	$img_path = $config['path'] . $path;

	if (!is_dir($img_path)) {
		@mkdir($img_path, 0755, true);
	}

	if (!is_writable($img_path)) {
		@chmod($img_path, 0755);
	}

	return $img_path;
}

// 图片命名规则
function imgName()
{
	global $config;
	$style = $config['imgName'];

	function create_guid()	// guid生成函数
	{
		if (function_exists('com_create_guid') === true) {
			return trim(com_create_guid(), '{}');
		}

		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}

	switch ($style) {
		case "date":	// 以上传时间 例：192704
			return date("His");
			break;
		case "unix":	// 以Unix时间 例：1635074840
			return time();
			break;
		case "uniqid":	// 基于以微秒计的当前时间 例：6175436c73418
			return uniqid(true);
			break;
		case "guid":	// 全球唯一标识符 例：6EDAD0CC-AB0C-4F61-BCCA-05FAD65BF0FA
			return create_guid();
			break;
		case "md5":	// md5加密时间 例：3888aa69eb321a2b61fcc63520bf6c82
			return md5(microtime());
			break;
		case "sha1":	// sha1加密微秒 例：654faac01499e0cb5fb0e9d78b21e234c63d842a
			return sha1(microtime());
			break;
		default:
			return base_convert(date('His') . mt_rand(1024, 10240), 10, 36);	// 将上传时间+随机数转换为36进制 例：vx77yu
	}
}

// 设置广告
function showAD($where)
{
	global $config;
	switch ($where) {
		case 'top':
			if ($config['ad_top']) {
				include(__DIR__ . '/../public/ad/top.html');
			}
			break;
		case 'bot':
			if ($config['ad_bot']) {
				include(__DIR__ . '/../public/ad/bottom.html');
			}
			break;
		default:
			echo '广告函数出错';
			break;
	}
}

// 静态文件CDN
function static_cdn()
{
	global $config;
	if ($config['static_cdn']) {
		echo $config['static_cdn_url'];
	} else {
		echo $config['domain'];
	}
}

// 开启tinyfilemanager图片管理
function tinyfilemanager()
{
	global $config;
	if (!$config['tinyfilemanager']) {
		header('Location: ' . $_SERVER["HTTP_REFERER"] . '?manager-closed');
		exit;
	}
}
/*
// 获取允许上传的扩展名
function getExtensions()
{
	global $config;
	$mime = '';
	for ($i = 0; $i < count($config['extensions']); $i++) {
		$mime .= $config['extensions'][$i] . ',';
	}
	return rtrim($mime, ',');
}
*/
// 获取目录大小 如果目录文件较多将很费时
function getDirectorySize($path)
{
	$bytestotal = 0;
	$path = realpath($path);
	if ($path !== false && $path != '' && file_exists($path)) {
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
			$bytestotal += $object->getSize();
		}
	}
	return $bytestotal;
}

/**
 * 获取指定文件夹文件数量
 * @param $dir 传入一个路径如：/apps/web
 * @return int 返回文件数量
 */
function getFileNumber($dir)
{
	$num = 0;
	$arr = glob($dir);
	foreach ($arr as $v) {
		if (is_file($v)) {
			$num++;
		} else {
			$num += getFileNumber($v . "/*");
		}
	}
	return $num;
}

/* 
 * 图片展示页面
 * getDir()取文件夹列表，getFile()取对应文件夹下面的文件列表,二者的区别在于判断有没有“.”后缀的文件，其他都一样
 * 获取文件目录列表,该方法返回数组
 * 调用方法getDir("./dir")……
 */
function getDir($dir)
{
	$dirArray[] = NULL;
	if (false != ($handle = opendir($dir))) {
		$i = 0;
		while (false !== ($file = readdir($handle))) {
			//去掉"“.”、“..”以及带“.xxx”后缀的文件
			if ($file != "." && $file != ".." && !strpos($file, ".")) {
				$dirArray[$i] = $file;
				$i++;
			}
		}
		//关闭句柄
		closedir($handle);
	}
	return $dirArray;
}
// 获取文件列表
function getFile($dir)
{
	$fileArray[] = NULL;
	if (is_dir($dir)) {
		if (false != ($handle = opendir($dir))) {
			$i = 0;
			while (false !== ($file = readdir($handle))) {
				//去掉"“.”、“..”以及带“.xxx”后缀的文件
				if ($file != "." && $file != ".." && strpos($file, ".")) {
					$fileArray[$i] = $file;
					if ($i == 100) {
						break;
					}
					$i++;
				}
			}
			//关闭句柄
			closedir($handle);
		}
	}
	return $fileArray;
}

/* 递归函数实现遍历指定文件下的目录与文件数量
 * 用来统计一个目录下的文件和目录的个数
 * echo "目录数为:{$dirn}<br>";
 * echo "文件数为:{$filen}<br>";
 */


function getdirnum($file)
{
	$dirn = 0; //目录数
	$filen = 0; //文件数
	$dir = opendir($file);
	while ($filename = readdir($dir)) {
		if ($filename != "." && $filename != "..") {
			$filename = $file . "/" . $filename;
			if (is_dir($filename)) {
				$dirn++;
				getdirnum($filename);
				//递归，就可以查看所有子目录
			} else {
				$filen++;
			}
		}
	}
	closedir($dir);
}

/* 把文件或目录的大小转化为容易读的方式
 * disk_free_space  - 磁盘可用空间(比如填写D盘某文件夹，则会现在D盘剩余空间）
 * disk_total_space — 磁盘总空间(比如填写D盘某文件夹，则会现在D盘总空间）
 */
function getDistUsed($number)
{
	$dw = ''; // 指定文件或目录统计的单位方式
	if ($number > pow(2, 30)) {
		$dw = "GB";
		$number = round($number / pow(2, 30), 2);
	} else if ($number > pow(2, 20)) {
		$dw = "MB";
		$number = round($number / pow(2, 20), 2);
	} else if ($number > pow(2, 10)) {
		$dw = "KB";
		$number = round($number / pow(2, 10), 2);
	} else {
		$dw = "bytes";
	}
	return $number . $dw;
}

// 根据url填写active
function getActive($url)
{
	$arr = $_SERVER['PHP_SELF'];
	if (strpos($arr, $url)) {
		return 'active';
	} elseif (strpos($arr, $url)) {
		return 'active';
	} else {
		return '';
	}
}

/**
 * 加密/解密图片路径
 * @param string $data 要加密的内容
 * @param int $mode =1或0  1解密 0加密
 * 
 */
function urlHash($data, $mode)
{
	global $config;
	$key =  $config['password'];
	$iv = 'sciCuBC7orQtDhTO';
	if ($mode) {
		$decode =  openssl_decrypt(base64_decode($data), "AES-128-CBC", $key, 0, $iv);
		return $decode;
	} else {
		$encode = base64_encode(openssl_encrypt($data, "AES-128-CBC", $key, 0, $iv));
		return $encode;
	}
}

// 删除指定文件
function getDel($url, $type)
{

	// url本地化
	$url = htmlspecialchars(parse_url($url)['path']);   // 过滤html 获取url path
	$url = urldecode(trim($url));

	if ($type == 'url') {
		$url = $_SERVER['DOCUMENT_ROOT'] . $url;
	}
	if ($type == 'hash') {
		$url = APP_ROOT . $url;
	}


	// 文件是否存在
	if (is_file($url)) {
		// 执行删除
		if (@unlink($url)) {
			echo '
			<script>
            new $.zui.Messager("删除成功，请刷新浏览器；如果开启了CDN，请等待缓存失效!", {type: "success" // 定义颜色主题 
            }).show();
			// 延时2s跳转			
            // window.setTimeout("window.location=\'/../ \'",3500);
            </script>
			';
		} else {
			echo '
			<script>
            new $.zui.Messager("删除失败", {type: "black" // 定义颜色主题 
            }).show();
            </script>
			';
		}
	} else {
		echo '
		<script>
		new $.zui.Messager("文件不存在", {type: "danger" // 定义颜色主题 
		}).show();
		</script>
		';
	}
	// 清除查询
	clearstatcache();
}

// 获取登录状态
function is_online()
{
	global $config;
	$md5Pwd = md5($config['password']);
	if (empty($_COOKIE['admin']) || $_COOKIE['admin'] != $md5Pwd) {
		echo false;
	} else {
		return true;
	}
}

/** 
 * 检查PHP缺少简单图床必备的扩展
 * 需检测的扩展：'fileinfo', 'iconv', 'gd', 'mbstring', 'openssl','zip',
 * zip 扩展不是必须的，但会影响tinyfilemanager文件压缩(本次不检测)。
 * 
 * 检测是否更改默认域名
 * 
 * 检测是否修改默认密码
 */
function checkEnv($mode)
{
	global $config;
	if ($mode) {
		// 扩展检测
		$expand = array('fileinfo', 'iconv', 'gd', 'mbstring', 'openssl',);
		foreach ($expand as $val) {
			if (!extension_loaded($val)) {
				echo '
			<script>
			new $.zui.Messager("扩展：' . $val . '- 未安装,可能导致图片上传失败！请尽快修复。", {type: "black" // 定义颜色主题 
			}).show();
			</script>
		';
			}
		}
		// 检测是否更改默认域名
		$url = preg_replace('#^(http(s?))?(://)#', '', 'http://192.168.2.100');
		if (strstr($url, $_SERVER['HTTP_HOST'])) {
			echo '
		<script>
		new $.zui.Messager("请修改默认域名，可能会导致图片访问异常！", {type: "black" // 定义颜色主题 
		}).show();
		</script>
		';
		}
		// 检测是否修改默认密码
		if ($config['password'] === 'admin@123') {
			echo '
		<script>
		new $.zui.Messager("请修改默认密码，否则会有泄露风险！", {type: "warning" // 定义颜色主题 
		}).show();
		</script>
		';
		}
		// 检查环境配置
		if (!is_file(APP_ROOT . '/config/EasyIamge.lock')) {
			echo '
		<div class="modal fade" id="myModal-1">
			<div class="modal-dialog">
				<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title">
					<i class="icon icon-heart">	</i><a href="https://www.545141.com/846.html" target="_blank">简单图床-EasyImage2.0</a> 安装环境检测</h4>
				</div>
				<div class="modal-body">
					<h4>说明：</h4>
					<h5>1. 建议使用
					<font color="red">PHP7.0</font>及以上版本；</h5>
					<h5>2. 上传失败大部分是由于
					<font color="red">upload_max_filesize、post_max_size、文件权限</font>设置不正确；</h5>
					<h5>3. 本程序用到
					<font color="red">Fileinfo、iconv、zip、mbstring、openssl</font>扩展,如果缺失会导致无法访问管理面板以及上传/删除图片。</h5>
					<h5>4.
					<font color="red">zip</font>扩展不是必须的，但会影响文件压缩(不会在首页中检测)。</h5>
					<h5>5. 上传后必须修改
					<font color="red">config.php</font>的位置：
					<font color="red">domain</font>当前网站域名，
					<font color="red">imgurl</font>当前图片域名，
					<font color="red">password</font>登录管理密码！</h5>
					<hr />
					<h4>EasyImage2.0 基础检测：</h4>
					当前PHP版本：<font color="green">' . phpversion() . '</font>';
			$quanxian = substr(base_convert(fileperms("file.php"), 10, 8), 3);
			if (!is_executable('file.php') || $quanxian != '755') {
				echo '
					<br/>
					<font color="red">上传文件权限错误（当前权限：' . $quanxian . '），<br />
					<b>windows可以无视，linux使用 chmod -R 0755 /所在目录/* 赋予权限</font>';
			} else {
				echo '
					<br/>
					<font color="green">当前文件可执行</font>';
			}
			echo '
					<br />
					<font color="green">upload_max_filesize</font>PHP上传最大值：' . ini_get('upload_max_filesize');
			echo '
					<br />
					<font color="green">post_max_size</font>PHP POST上传最大值：' . ini_get('post_max_size') . '
					<br />';
			$expand = array('fileinfo', 'iconv', 'gd', 'zip', 'mbstring', 'openssl',);
			foreach ($expand as $val) {
				if (extension_loaded($val)) {
					echo '
					<font color="green">' . $val . "</font>- 已安装
					<br />";
				} else {
					echo "
					<script language='javascript'>alert('$val - 未安装')</script>";
					echo '
					<font color="red">' . $val . "- 未安装</font>
					<br />";
				}
			}
			echo '
					<hr/>以下是当前PHP所有已安装扩展：
					<br/>';
			foreach (get_loaded_extensions() as $val) {
				echo '
					<font color="green">' . $val . '</font>，';
			}
			echo '</div>
				<div class="modal-footer">
				<p>安装环境检测弹窗只会第一次打开时展示，会在config目录下自动生成EasyIamge.lock，如需再次展示或更换空间请自行删除EasyIamge.lock！刷新后此提示框消失。</p>
				</div>
			</div>
		</div>
	</div>
			<script>$("#myModal-1").modal({
				keyboard: true,
				moveable: true,
				backdrop: "static",//点击空白处不关闭对话框
				show: true
			})
			alert("初次打开会检测环境配置，请仔细看!!");
			</script>
		';
			file_put_contents(APP_ROOT . '/config/EasyIamge.lock', '安装环境检测锁定文件，如需再次展示请删除此文件！', FILE_APPEND | LOCK_EX);
			clearstatcache();
		}
	}
}


// 前端改变图片长宽
function imgRatio()
{
	global $config;
	if ($config['imgRatio']) {
		$image_x =  $config['image_x'];
		$image_y =  $config['image_y'];
		echo '
		resize:{
			width: ' . $image_x . ',
			height: ' . $image_y . ',
			preserve_headers: false,	// 是否保留图片的元数据
		},
		';
	} else {
		return null;
	}
}

/**
 * 定时获取GitHub 最新版本
 */

function getVersion()
{
	global $config;

	if ($config['checkEnv']) {
		require_once APP_ROOT . '/libs/class.version.php';
		// 获取版本地址
		$url = "https://api.github.com/repositories/188228357/releases/latest";
		$getVersion = new getVerson($url);

		$now = date('dH'); // 当前日期时间
		$get_ver_day = array('1006', '2501');   // 检测日期的时间

		foreach ($get_ver_day as $day) {
			if (empty($getVersion->readJson())) { // 不存在就下载
				$getVersion->downJson();
			} else if ($day == $now) { // 是否在需要更新的日期
				$getVersion->downJson();
				/*
			} elseif ($config['version'] == $getVersion->readJson()) { // 版本相同不提示
				return null;
			*/
			} else { // 返回版本
				return $getVersion->readJson();
			}
		}
	} else {
		return null;
	}
}

// 删除非空目录
function deldir($dir)
{
	if (file_exists($dir)) {
		$files = scandir($dir);
		foreach ($files as $file) {
			if ($file != '.' && $file != '..') {
				$path = $dir . '/' . $file;
				if (is_dir($path)) {
					deldir($path);
				} else {
					unlink($path);
				}
			}
		}
		rmdir($dir);
		return true;
	} else {
		return false;
	}
}
