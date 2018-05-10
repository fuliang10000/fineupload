<?php

class DB
{
    public $conn;
    public function __construct()
    {
        header('Content-Type:text/html;charset=utf-8');
        $this->conn = mysqli_connect('120.79.184.17','root','root', 'wxdevelopment',3306);
        mysqli_query($this->conn,'set names utf8');
    }

    public function insertImages($data) {
        $time = time();
        $deleted = 0;
        $sql = "INSERT INTO
                `sys_file`
            VALUES (
                null,
                '{$data['real_name']}',
                '{$data['hash_name']}',
                '{$data['suffix_name']}',
                '{$data['file_size']}',
                '{$data['file_path']}',
                '{$time}',
                '{$deleted}'
                )";
        $res = mysqli_query($this->conn, $sql);

        return $res;
    }

    public function getAllList()
    {
        $sql = "SELECT * FROM `sys_file` WHERE deleted = 0";
        $result = mysqli_query($this->conn, $sql);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

        return $rows;
    }
}