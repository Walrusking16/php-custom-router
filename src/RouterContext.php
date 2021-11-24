<?php

class RouterContext {
    public array $post = [];
    public array $query = [];
    public array $server = [];
    public array $session = [];

    public function __construct()
    {
        $this->post = $_POST;
        $this->query = $_GET;
        $this->server = $_SERVER;
        $this->session = $_SESSION;
    }
}