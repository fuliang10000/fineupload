<?php 
error_reporting(E_ALL);
//$filename = "server\uploads\member_files\avatar\20160506\5b089c23ccc9045e2cb45d3798ca88a2.png";
 $filename = "uploads/member_files/avatar/20160506/c3e31f11a00a8650f8a7b56bf4ae811b.png";

    function getFileHashVal($filename)
    {echo  $filename;
        if( file_exists( $filename ) ){
            return hash_file('md4', $filename);
        }else{
			echo 'no_file';
		}
    }
	echo $a = getFileHashVal($filename);
?>