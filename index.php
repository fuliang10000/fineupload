<?php
require_once 'conn/conn.php';
$db = new DB();
$rows = $db->getAllList();
?>
<!DOCTYPE html>
<html>
<head>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	  <link href="fineuploader-5.0.2.css" rel="stylesheet" type="text/css"/>
      <script src="fineuploader-5.0.2.js" type="text/javascript"></script>
	  <script src="http://apps.bdimg.com/libs/jquery/1.6.4/jquery.min.js" type="text/javascript"></script>
</head>
<style>
#basic_uploader_fine{
	width:100px;
	height:30px;
	background:green;
	text-align:center;
	border-radius:5px; 
	line-height:30px;
}
#triggerUpload
 {
	width:100px;
	height:30px;
	background:red;
	text-align:center;
	border-radius:5px; 
	line-height:30px;
	margin-top:10px;
}
.buttons {
    width:100px;
	height:30px;
	margin-top:5px;
	border-radius:5px;
	background-color:#f20df8;
	text-align:center;
	line-height:30px;
}
	td {
		border:1px solid black
	}
	th {
		border:1px solid black
	}
</style>
<body>
<!--定义按钮-->
<div id="basic_uploader_fine"><i class="icon-upload icon-white"></i>选择文件</div>
<div id="triggerUpload">点击上传</div>
<!--显示信息-->
<div id="messages"></div>
<div id="cancelUpload" class="buttons">取消</div>
<div id="cancelAll" class="buttons">取消全部</div>
<div id="pauseUpload" class="buttons">暂停上传</div>
<div id="continueUpload" class="buttons">继续上传</div>
<table style="border:1px solid black">
	<thead>
	<tr>
		<th>ID</th>
		<th>文件真实名称</th>
		<th>文件hash值</th>
		<th>文件后缀名</th>
		<th>单位KB</th>
		<th>文件真实路径</th>
		<th>文件上传时间</th>
		<th>操作</th>
	</tr>
	</thead>
	<?php if ($rows) { ?>
	<?php foreach ($rows as $row) { ?>
	<tr>
		<td><?php echo $row['id']?></td>
		<td><?php echo $row['real_name']?></td>
		<td><?php echo $row['hash_name']?></td>
		<td><?php echo $row['suffix_name']?></td>
		<td><?php echo $row['file_size']?></td>
		<td><?php echo $row['file_path']?></td>
		<td><?php echo date('Y-m-d H:i:s',$row['update_time'])?></td>
		<td></td>
	</tr>
	<?php } ?>
	<?php } ?>
</table>
<script>
	//不要jquery入口
    var fub = $('#basic_uploader_fine');
	var messages = $('#messages');
	var url = 'server/endpoint.php';
	var uploader = new qq.FineUploaderBasic({
		debug: false,       // 开启调试模式
		multiple: true,    // 多文件上传
		button: fub[0],   //上传按钮
		autoUpload: false, //不自动上传则调用uploadStoredFiless方法 手动上传
		// 验证上传文件
		validation: {  
			allowedExtensions: ['jpeg', 'jpg', 'png', 'zip', 'rar', 'pdf'],
			sizeLimit: 100 * 1000 * 1000
		},
		// 远程请求地址（相对或者绝对地址）
		request: {
			endpoint: url,
			params: {
				thumb: false,//是否生成缩略图
				saveFlag: true,//是否保存到数据库
				filePath: 'member_order', /*供应商注册文件夹*/
				//文件所属信息
				relate: {
					user_id: null,    //上传者id
					user_type: null   //上传者类型
				}
			}
		},
		retry: {
		   enableAuto: false // defaults to false 自动重试
		}, 
		chunking: {
			enabled: true,
			partSize: 2000000, // 分组大小，默认为 2M
			concurrent: {
				enabled: true // 开启并发分组上传，默认并发3个
			},
			success: {
				endpoint: url + "?done" // 分组上传完成后处理
			}
		},
	  	//回调函数
		callbacks: {
			//文件开始上传
			onSubmit: function(id, fileName) {
				messages.append('<div id="file-' + id + '" class="alert" style="margin: 20px 0 0">'+fileName+'<span  onClick="cancelUploadFile('+id+')">【取消上传】</span></div><span  onClick="pauseUploadFile('+id+')">【暂停上传】</span><span  onClick="continueUploadFile('+id+')">【继续上传】</span>');
			},
			onUpload: function(id, fileName) {
				$('#file-' + id).addClass('alert-info')
						.html('<img src="loading.gif" alt="Initializing. Please hold."> ' +
						'Initializing ' +
						'“' + fileName + '”');
			},
			//进度条
			onProgress: function(id, fileName, loaded, total) {
				if (loaded < total) {
					var progress = Math.round(loaded / total * 100) + '% of ' + Math.round(total / 1024) + ' kB';
					$('#file-' + id).removeClass('alert-info')
							.html('<img src="loading.gif" width="20px" height="20px;" alt="In progress. Please hold."> ' +
							'上传文件中......' +  progress);
				} else {
					$('#file-' + id).addClass('alert-info')
							.html('<img src="loading.gif" width="20px" height="20px;" alt="Saving. Please hold."> ' +
							'上传文件中...... ');
				}
			},
			//上传完成后
			onComplete: function(id, fileName, responseJSON) {
				console.log(responseJSON);
				if (responseJSON.success) {
					var img = responseJSON['target'];
					$('#file-' + id).removeClass('alert-info')
							.addClass('alert-success')
							.html('<i class="icon-ok"></i> ' +
							'上传成功！ ' +
							'“' + fileName + '”'
					);
				} else {
					$('#file-' + id).removeClass('alert-info')
							.addClass('alert-error')
							.html('<i class="icon-exclamation-sign"></i> ' +
							'Error with ' +
							'“' + fileName + '”: ' +
							responseJSON.error);
				}
			},
			onError: function(id, name, reason, maybeXhrOrXdr) {
				console.log(id + '_' + name + '_' + reason);
			}
		}
	});

	//手动触发上传上传
	$('#triggerUpload').click(function() {
      uploader.uploadStoredFiles();
    });

	//取消所有未上传的文件
	$('#cancelAll').click(function() {
		 //单个文件上传没有作用 因为已经在上传的不能使用这个cancelAll取消上传
		 uploader.cancelAll();
    });

	//暂停上传某个文件
	$('#pauseUpload').click(function() {
	     uploader.pauseUpload(0);
    });

	// 继续上传
	$('#continueUpload').click(function() {
	     uploader.continueUpload(0);
    });

	//取消上传
	function cancelUploadFile(id)
	{
	    uploader.cancel(id);
	}

	//暂停上传某个文件
	function pauseUploadFile(id)
	{
	  uploader.pauseUpload(id);
	}

	//继续上传某个文件
	function continueUploadFile(id)
	{
	   uploader.continueUpload(id);
	}
</script>
</body>
</html>
