<?php

/**
 * PHP Server-Side Example for Fine Uploader (traditional endpoint handler).
 * Maintained by Widen Enterprises.
 *
 * This example:
 *  - handles chunked and non-chunked requests
 *  - supports the concurrent chunking feature
 *  - assumes all upload requests are multipart encoded
 *  - supports the delete file feature
 *
 * Follow these steps to get up and running with Fine Uploader in a PHP environment:
 *
 * 1. Setup your client-side code, as documented on http://docs.fineuploader.com.
 *
 * 2. Copy this file and handler.php to your server.
 *
 * 3. Ensure your php.ini file contains appropriate values for
 *    max_input_time, upload_max_filesize and post_max_size.
 *
 * 4. Ensure your "chunks" and "files" folders exist and are writable.
 *    "chunks" is only needed if you have enabled the chunking feature client-side.
 *
 * 5. If you have chunking enabled in Fine Uploader, you MUST set a value for the `chunking.success.endpoint` option.
 *    This will be called by Fine Uploader when all chunks for a file have been successfully uploaded, triggering the
 *    PHP server to combine all parts into one file. This is particularly useful for the concurrent chunking feature,
 *    but is now required in all cases if you are making use of this PHP example.
 */

// Include the upload handler class
require_once "handler.php";


$uploader = new UploadHandler();

// Specify the list of valid extensions, ex. array("jpeg", "xml", "bmp")
// 文件类型限制
$uploader->allowedExtensions = array(); // all files types allowed by default

// Specify max file size in bytes.
// 文件大小限制
$uploader->sizeLimit = null;

// Specify the input name set in the javascript.
// 上传文件框
$uploader->inputName = "qqfile"; // matches Fine Uploader's default inputName value by default

// If you want to use the chunking/resume feature, specify the folder to temporarily save parts.
// 定义分组文件存放位置
$uploader->chunksFolder = "chunks";
$uploader->thumb = true;//图片是否生成缩略图

$method = $_SERVER["REQUEST_METHOD"];

//上传目的文件夹
$uploadDirectory =  $uploader->getPathName('member_avatar');
//供应商订单文件存放
//$uploadDirectory =  $uploader->getCommunityOrderPath('zlc000001','zenglingcheng','huace');
if ($method == "POST") {
    header("Content-Type: text/plain");

    // 分组上传完成后对分组进行合并
    if (isset($_GET["done"])) {
        $result = $uploader->combineChunks($uploadDirectory); // 合并分组文件

    } else {
        //开始上传文件
        $result = $uploader->handleUpload($uploadDirectory);
        // 获取上传的名称
        $result["uploadName"] = $uploader->getUploadName();

    }
    echo json_encode($result);

}


// for delete file requests
else if ($method == "DELETE") {
    $result = $uploader->handleDelete($uploadDirectory);
    echo json_encode($result);
}

else {
    header("HTTP/1.0 405 Method Not Allowed");
}

?>
