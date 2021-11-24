<?php


class UploadContext
{
    public array $file;
    public string $dir;
    public string $path;

    public function __construct(array $file, string $dir) {
        $this->file = $file;
        $this->dir = $dir;
        $this->path = $dir . $file["name"];
    }
}