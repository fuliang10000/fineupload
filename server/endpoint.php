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
$method = $_SERVER["REQUEST_METHOD"];
if ($method == "POST") {
    header("Content-Type: text/plain");
    $postData = $_POST;
    $uploader = new UploadHandler();
    $uploader->thumb = $postData['thumb'];//图片是否生成缩略图
    $uploader->saveFlag = $postData['saveFlag'];//保存到数据库
    //上传目的文件夹
    $uploadDirectory = $uploader->getPathName($postData['filePath']);
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
// for delete file requests
} elseif ($method == "DELETE") {
    $result = $uploader->handleDelete($uploadDirectory);
    echo json_encode($result);
} else {
    header("HTTP/1.0 405 Method Not Allowed");
}
?>
