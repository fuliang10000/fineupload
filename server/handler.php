<?php
require_once '../conn/conn.php';
/**
 * Do not use or reference this directly from your client-side code.
 * Instead, this should be required via the endpoint.php or endpoint-cors.php
 * file(s).
 */

class UploadHandler {

    public $allowedExtensions = array();
    public $sizeLimit = null;
    public $inputName = 'qqfile';
    public $chunksFolder = 'chunks';

    public $chunksCleanupProbability = 0.001; // Once in 1000 requests on avg
    public $chunksExpireIn = 604800; // One week

    protected $uploadName;
    public $thumb = false;//是否生成缩略图默认不生成
    public $root = 'uploads/';
    public $saveFlag = false; //是否保存到数据库

    public function __construct()
    {
        $this->date = date("Ymd");
    }


    /**
     * Get the original filename
     */
    public function getName(){
        if (isset($_REQUEST['qqfilename']))
            return $_REQUEST['qqfilename'];

        if (isset($_FILES[$this->inputName]))
            return $_FILES[$this->inputName]['name'];
    }

    public function getInitialFiles() {
        $initialFiles = array();

        for ($i = 0; $i < 5000; $i++) {
            array_push($initialFiles, array("name" => "name" + $i, uuid => "uuid" + $i, thumbnailUrl => "/test/dev/handlers/vendor/fineuploader/php-traditional-server/fu.png"));
        }

        return $initialFiles;
    }

    /**
     * Get the name of the uploaded file
     */
    public function getUploadName(){
        return $this->uploadName;
    }

    // 合并分组文件
    public function combineChunks($uploadDirectory) {
        $uuid = $_POST['qquuid'];
        $name = $this->getName();
        $targetFolder = $this->chunksFolder.DIRECTORY_SEPARATOR.$uuid;
        $totalParts = isset($_REQUEST['qqtotalparts']) ? (int)$_REQUEST['qqtotalparts'] : 1;


        $this->uploadName = $name;
       /* $targetPath = join(DIRECTORY_SEPARATOR, array($uploadDirectory, $uuid, $name));
        if (!file_exists($targetPath)){
            mkdir(dirname($targetPath));
        }*/

        /********************原代码不满住现有项目需求修改需求zenglc 20160506 ********************/
        $pathinfo = pathinfo($name);
        $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
        $uniName = $this->getUniName();
        $targetPath = $uploadDirectory.'/'.$uniName.'.'.$ext;
        /********************原代码不满住现有项目需求修改需求zenglc 20160506 ********************/

        $target = fopen($targetPath, 'wb');
        for ($i=0; $i<$totalParts; $i++){
            $chunk = fopen($targetFolder.DIRECTORY_SEPARATOR.$i, "rb");
            stream_copy_to_stream($chunk, $target);
            fclose($chunk);
        }

        // Success
        fclose($target);

        // 成功后删除分组文件
        for ($i=0; $i<$totalParts; $i++){
            unlink($targetFolder.DIRECTORY_SEPARATOR.$i);
        }

        rmdir($targetFolder);

        if (!is_null($this->sizeLimit) && filesize($targetPath) > $this->sizeLimit) {
            unlink($targetPath);
            http_response_code(413);
            return array("success" => false, "uuid" => $uuid, "preventRetry" => true);
        }

        //缩略图的生成
        if($this->thumb){
            $thumb['big'] = $this->thumb($targetPath,$isReservedSource=true,$scale=0.8,$newname='big');
            $thumb['middle'] = $this->thumb($targetPath,$isReservedSource=true,$scale=0.5,$newname='middel');
            $thumb['small'] = $this->thumb($targetPath,$isReservedSource=true,$scale=0.3,$newname='small');
        }else{
            $thumb = null;
        }
        //文件hash值
        $fileHash = $this->getFileHashVal($targetPath);
        if ($this->saveFlag) {
            $db = new DB();
            $data = [
                'hash_name' => $fileHash,
                'file_path' => $targetPath,
            ];
            $res = $db->insertImages($data);
        }
        return array("success" => true, "uuid" => $uuid,'target'=>$targetPath,'hashVal'=>$fileHash,'thumb'=>$thumb,'save' => $res);
    }

    /**
     * Process the upload.
     * @param string $uploadDirectory Target directory.
     * @param string $name Overwrites the name of the file.
     */
    public function handleUpload($uploadDirectory, $name = null){

        if (is_writable($this->chunksFolder) &&
            1 == mt_rand(1, 1/$this->chunksCleanupProbability)){

            // Run garbage collection
            $this->cleanupChunks();
        }

        // Check that the max upload size specified in class configuration does not
        // exceed size allowed by server config
        if ($this->toBytes(ini_get('post_max_size')) < $this->sizeLimit ||
            $this->toBytes(ini_get('upload_max_filesize')) < $this->sizeLimit){
            $neededRequestSize = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
            return array('error'=>"Server error. Increase post_max_size and upload_max_filesize to ".$neededRequestSize);
        }

        if ($this->isInaccessible($uploadDirectory)){
            return array('error' => "Server error. Uploads directory isn't writable");
        }

        $type = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $type = $_SERVER['HTTP_CONTENT_TYPE'];
        }

        if(!isset($type)) {
            return array('error' => "No files were uploaded.");
        } else if (strpos(strtolower($type), 'multipart/') !== 0){
            return array('error' => "Server error. Not a multipart request. Please set forceMultipart to default value (true).");
        }

        // Get size and name
        $file = $_FILES[$this->inputName];
        $size = $file['size'];
        if (isset($_REQUEST['qqtotalfilesize'])) {
            $size = $_REQUEST['qqtotalfilesize'];
        }

        if ($name === null){
            $name = $this->getName();
        }

        // Validate name
        if ($name === null || $name === ''){
            return array('error' => 'File name empty.');
        }

        // Validate file size
        if ($size == 0){
            return array('error' => 'File is empty.');
        }

        if (!is_null($this->sizeLimit) && $size > $this->sizeLimit) {
            return array('error' => 'File is too large.', 'preventRetry' => true);
        }

        // Validate file extension
        $pathinfo = pathinfo($name);
        $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';

        if($this->allowedExtensions && !in_array(strtolower($ext), array_map("strtolower", $this->allowedExtensions))){
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => 'File has an invalid extension, it should be one of '. $these . '.');
        }

        // Save a chunk
        $totalParts = isset($_REQUEST['qqtotalparts']) ? (int)$_REQUEST['qqtotalparts'] : 1;

        $uuid = $_REQUEST['qquuid'];
        if ($totalParts > 1){
        # chunked upload

            $chunksFolder = $this->chunksFolder;
            $partIndex = (int)$_REQUEST['qqpartindex'];

            if (!is_writable($chunksFolder) && !is_executable($uploadDirectory)){
                return array('error' => "Server error. Chunks directory isn't writable or executable.");
            }

            $targetFolder = $this->chunksFolder.DIRECTORY_SEPARATOR.$uuid;

            if (!file_exists($targetFolder)){
                mkdir($targetFolder);
            }

            $target = $targetFolder.'/'.$partIndex;
            $success = move_uploaded_file($_FILES[$this->inputName]['tmp_name'], $target);

            return array("success" => true, "uuid" => $uuid,'target'=>$target);

        }
        else {
        # non-chunked upload

           /* $target = join(DIRECTORY_SEPARATOR, array($uploadDirectory, $uuid, $name));

            if ($target){
                $this->uploadName = basename($target);

                if (!is_dir(dirname($target))){
                    mkdir(dirname($target));
                }
                if (move_uploaded_file($file['tmp_name'], $target)){
                    return array('success'=> true, "uuid" => $uuid);
                }
            }*/

            //原代码不满足需求修改 20160505 zenglc
            $uniName = $this->getUniName();
            $target = $uploadDirectory.'/'.$uniName.'.'.$ext;

            if ($target){
                $this->uploadName = basename($target);
                if (move_uploaded_file($file['tmp_name'], $target)){
                    //缩略图的生成
                    if($this->thumb){
                        $thumb['big'] = $this->thumb($target,$isReservedSource=true,$scale=0.8,$newname='big');
                        $thumb['middle'] = $this->thumb($target,$isReservedSource=true,$scale=0.5,$newname='middel');
                        $thumb['small'] = $this->thumb($target,$isReservedSource=true,$scale=0.3,$newname='small');
                    }else{
                        $thumb = null;
                    }
                    //文件hash值
                     $fileHash = $this->getFileHashVal($target);
                    if ($this->saveFlag) {
                        $db = new DB();
                        $data = [
                            'hash_name' => $fileHash,
                            'file_path' => $target,
                        ];
                        $res = $db->insertImages($data);
                    }
                    return array('success'=> true, "uuid" => $uuid,'trueName'=>$name,'target'=>$target,'hashVal'=>$fileHash,'thumb'=>$thumb, 'save' => $res);
                }
            }
            return array('error'=> 'Could not save uploaded file.' .
                'The upload was cancelled, or server error encountered');

        }
    }

    /**
     * Process a delete.
     * @param string $uploadDirectory Target directory.
     * @params string $name Overwrites the name of the file.
     *
     */
    public function handleDelete($uploadDirectory, $name=null)
    {
       // file_put_contents('a.txt',$uploadDirectory);
        if ($this->isInaccessible($uploadDirectory)) {
            return array('error' => "Server error. Uploads directory isn't writable" . ((!$this->isWindows()) ? " or executable." : "."));
        }

        $targetFolder = $uploadDirectory;
        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        file_put_contents('a.txt',$url);
        $tokens = explode('/', $url);
        $uuid = $tokens[sizeof($tokens)-1];

        $target = join(DIRECTORY_SEPARATOR, array($targetFolder, $uuid));

        if (is_dir($target)){
            $this->removeDir($target);
            return array("success" => true, "uuid" => $uuid);
        } else {
            return array("success" => false,
                "error" => "File not found! Unable to delete.".$url,
                "path" => $uuid
            );
        }

    }

    /**
     * Returns a path to use with this upload. Check that the name does not exist,
     * and appends a suffix otherwise.
     * @param string $uploadDirectory Target directory
     * @param string $filename The name of the file to use.
     */
    protected function getUniqueTargetPath($uploadDirectory, $filename)
    {
        // Allow only one process at the time to get a unique file name, otherwise
        // if multiple people would upload a file with the same name at the same time
        // only the latest would be saved.

        if (function_exists('sem_acquire')){
            $lock = sem_get(ftok(__FILE__, 'u'));
            sem_acquire($lock);
        }

        $pathinfo = pathinfo($filename);
        $base = $pathinfo['filename'];
        $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
        $ext = $ext == '' ? $ext : '.' . $ext;

        $unique = $base;
        $suffix = 0;

        // Get unique file name for the file, by appending random suffix.

        while (file_exists($uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext)){
            $suffix += rand(1, 999);
            $unique = $base.'-'.$suffix;
        }

        $result =  $uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext;

        // Create an empty target file
        if (!touch($result)){
            // Failed
            $result = false;
        }

        if (function_exists('sem_acquire')){
            sem_release($lock);
        }

        return $result;
    }

    /**
     * Deletes all file parts in the chunks folder for files uploaded
     * more than chunksExpireIn seconds ago
     */
    protected function cleanupChunks(){
        foreach (scandir($this->chunksFolder) as $item){
            if ($item == "." || $item == "..")
                continue;

            $path = $this->chunksFolder.DIRECTORY_SEPARATOR.$item;

            if (!is_dir($path))
                continue;

            if (time() - filemtime($path) > $this->chunksExpireIn){
                $this->removeDir($path);
            }
        }
    }

    /**
     * Removes a directory and all files contained inside
     * @param string $dir
     */
    protected function removeDir($dir){
        foreach (scandir($dir) as $item){
            if ($item == "." || $item == "..")
                continue;

            if (is_dir($item)){
                $this->removeDir($item);
            } else {
                unlink(join(DIRECTORY_SEPARATOR, array($dir, $item)));
            }

        }
        rmdir($dir);
    }

    /**
     * Converts a given size with units to bytes.
     * @param string $str
     */
    protected function toBytes($str){
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }

    /**
     * Determines whether a directory can be accessed.
     *
     * is_executable() is not reliable on Windows prior PHP 5.0.0
     *  (http://www.php.net/manual/en/function.is-executable.php)
     * The following tests if the current OS is Windows and if so, merely
     * checks if the folder is writable;
     * otherwise, it checks additionally for executable status (like before).
     *
     * @param string $directory The target directory to test access
     */
    protected function isInaccessible($directory) {
        $isWin = $this->isWindows();
        $folderInaccessible = ($isWin) ? !is_writable($directory) : ( !is_writable($directory) && !is_executable($directory) );
        return $folderInaccessible;
    }

    /**
     * Determines is the OS is Windows or not
     *
     * @return boolean
     */

    protected function isWindows() {
    	$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    	return $isWin;
    }
    /**
     * @name 根据文件存放规则获取文件夹
     * @memo 【不包括供应商订单生产文件夹的生成】
     * @param $str
     * @return string
     */
    public function getPathName($str)
    {
        switch($str){
            //会员头像
            case 'member_avatar':
                $path = 'member_files/avatar/'.$this->date;
                break;
            //会员注册认证相关资料
            case 'member_register':
                $path = 'member_files/register/'.$this->date;
                break;
            //会员素材
            case 'member_material':
                $path = 'member_files/material/'.$this->date;
                break;
            //会员订单文件
            case 'member_order':
                $path = 'order_files/'.$this->date;
                break;
            //会员模板文件
            case 'member_templates':
                $path = 'member_files/templates/'.$this->date;
                break;
            //会员其他文件
            case 'member_others':
                $path = 'member_files/others/'.$this->date;
                break;
            //供应商用户头像
            case 'community_avatar':
                $path = 'community_files/avatar/'.$this->date;
                break;
            /*//供应商订单生产文件
            case 'community_produce_order':
                $path = $this->getCommunityProduceOrder();
                break;*/
            //供应商注册文件
            case 'community_register':
                $path = 'community_files/register/'.$this->date;
                break;
            //供应商其他文件
            case 'community_others':
                $path = 'community_files/others/'.$this->date;
                break;
            //商家用户头像
            case 'business_avatar':
                $path = 'business_files/avatar/'.$this->date;
                break;
            //商家注册
            case 'business_register':
                $path = 'business_files/register/'.$this->date;
                break;
            //平台模板文件
            case 'system_templates':
                $path = 'system_files/templates/'.$this->date;
                break;
            //平台素材文件
            case 'system_material':
                $path = 'system_files/material/'.$this->date;
                break;
            //平台其他文件
            case 'system_others':
                $path = 'system_files/others/'.$this->date;
                break;
            //平台产品图
            case 'system_product_goods':
                $path = 'system_files/product/goods_img/'.$this->date;
                break;
            //平台产品资源图
            case 'system_product_source':
                $path = 'system_files/product/source_img/'.$this->date;
                break;
            //平台产品缩略图
            case 'system_product_thumb':
                $path = 'system_files/product/thumb_img/'.$this->date;
                break;
            //平台产品缩略图
            case 'system_product_thumb':
                $path = 'system_files/product/thumb_img/'.$this->date;
                break;
            //平台产品材料图
            case 'system_product_material':
                $path = 'system_files/product/material_img/'.$this->date;
                break;
            //平台产品材料图
            case 'system_product_process':
                $path = 'system_files/product/process_img/'.$this->date;
                break;
            default:
                $path='';
                break;

        }
        $path = $this->root.$path;
        if (!is_dir($path)) {
            mkdir($path, 0777,true);
        }
        return $path;
    }


    /**
     * @name 根据供应商文件存放规则获取文件夹
     * @param $bus_sn 供应商编号
     * @param $community_code 供应商名称拼音
     * @param $album 产品品类编码
     * @return string
     */
    public function getCommunityOrderPath($bus_sn,$community_code,$album)
    {
      if ( $bus_sn && $community_code && $album ){
          $path = $this->root.'/community_files/'.$bus_sn.'_'.strtoupper($community_code).'/'.$this->date.'/'.$album;
          if (!is_dir($path)) {
              mkdir($path, 0777,true);
          }
      } else {
          $path = '';
      }
        return $path;

    }

    /**
     * 生成唯一字符串
     * @return string
     */
    public function getUniName(){
        return md5(uniqid(microtime(true),true));
    }

    /**
     * @param $filename 文件名称
     * @param bool $isReservedSource 是否删除原文件 默认删除
     * @param float $scale 默认缩放比例
     * @param null $newname 新文件部门标识名称 如big,middel,small
     * @param null $dst_w 指定缩放宽度
     * @param null $dst_h 指定缩放高度
     * @return string
     */
    public function thumb($filename,$isReservedSource=false,$scale=0.5,$newname=null,$dst_w=null,$dst_h=null)
    {
        if ($filename) {
            $info = getimagesize($filename);
            $infoExt = str_replace("image/", "", $info['mime']);

            if(in_array($infoExt,['jpeg','gif','png','wbmp'])){

                list($src_w, $src_h, $imagetype) = getimagesize($filename);
                if (is_null($dst_w) || is_null($dst_h)) {
                    $dst_w = ceil($src_w * $scale);
                    $dst_h = ceil($src_h * $scale);
                }

                $mime = image_type_to_mime_type($imagetype);
                //获取创建画布函数
                $createFun = str_replace("/", "createfrom", $mime);
                //获取创建输出图像函数
                $outFun = str_replace("/", null, $mime);
                $src_image = $createFun($filename);
                $dst_image = imagecreatetruecolor($dst_w, $dst_h);
                imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
                if(!$newname){
                    $newname = $dst_w.'x'.$dst_h;
                }
                $pathinfo = pathinfo($filename);
                $dstFilename = $pathinfo['dirname'].'/'.$pathinfo['filename'].'_'.$newname.'.'.$pathinfo['extension'];
                $outFun($dst_image, $dstFilename);
                imagedestroy($src_image);
                imagedestroy($dst_image);
                if (!$isReservedSource) {
                    //unlink($filename);
                }
                return $dstFilename;
            }else{
                return '该格式无法生成缩略图！';
            }
        } else {
            return '没有传入文件！';
        }

    }

    /**
     * @name 获取文件hash值
     * @memo 根据所有hash算法的效率，选择md4算法。
     * @author zenglc
     * @date 20160506
     * @param $filename
     * @return string
     */
    public function getFileHashVal($filename)
    {
        if( $filename ){
            return hash_file('md4', $filename);
        }

    }

    /**
     * @name 该函数可以实现打包压缩文件的功能
     * @memo 为在文件下载后可以解压，统一使用zip包
     * @author zenglc
     * @date 20160506
     * @param $filename 输出的文件名称zip后缀
     * @param $fileArr 需要打包压缩的文件名称数组
     * @return mixed
     */
    public function zipFile($filename,$fileArr,$bool=true)
    {
        $zip = new ZipArchive;
        $pathinfo = pathinfo($filename);
        $ext = $pathinfo['extension'];
        if( $ext == 'zip' && count($fileArr) > 0){
            if ($zip->open($filename, ZipArchive::OVERWRITE) === TRUE)
            {
                foreach($fileArr as $file){
                    $zip->addFile($file);
                    if($bool){
                       unlink($file);
                    }
                }
                $zip->close();
                return $filename;
            }
        }


    }
}
