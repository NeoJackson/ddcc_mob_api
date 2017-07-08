<?php
class MallModel {
    private $db;
    public function __construct() {
        $this->db = DB::getInstance();
    }
}