<?php

header('Content-Type: application/json;charset=utf-8');

require "src/RouterMiddleware.php";
require "src/Uploader.php";

$aws = new Router();

$aws->prefix("/aws");

// If not in dev mode then require api key to be added to request
$aws->middleware(function (RouterContext $ctx) {
    $middleware = new RouterMiddleware();

    $middleware->pass = $_ENV["DEV_MODE"] == "true" || $_ENV["API_KEY"] === (array_key_exists("api_key", $ctx->query) ? $ctx->query["api_key"] : "");

    $middleware->onFail(function() {
        echo "Invalid API Key";
    });

    return $middleware;
});

// Upload file to aws bucket
$aws->post("/upload", function() {

    $s3 = new \Aws\S3\S3Client([
        'region'  => $_ENV["AWS_REGION"],
        'version' => 'latest',
        'credentials' => [
            'key' => $_ENV["AWS_ACCESS_KEY_ID"],
            'secret' => $_ENV["AWS_SECRET_ACCESS_KEY"]
        ]
    ]);

    $tmpfile = $_FILES['sharex']['tmp_name'];
    $ext = "." . pathinfo($_FILES['sharex']['name'], PATHINFO_EXTENSION);
    $file = str_replace(".", "", uniqid(more_entropy: true));

    $result = null;

    try {
        $result = $s3->putObject([
            'Bucket' => $_ENV["AWS_BUCKET"],
            'Key'    => $_ENV["AWS_FOLDER"] . $file . $ext,
            'Body' => fopen($tmpfile, 'r+'),
            'ACL' => 'public-read'
        ]);

    } catch (Aws\S3\Exception\S3Exception $e) {
        echo "There was an error uploading the file.\n";
        echo $e->getMessage();
    }

    unlink($tmpfile);

    echo json_encode([
        "status" => !$result ? "ERROR" : "OK",
        "url" => $result ? $file : ""
    ]);
});

return $aws;