<?php

require "UploadContext.php";

class Uploader {
	protected string $baseDirectory = "";
	protected string $directory = "";
	protected array $allowedTypes = [];
	protected array $files = [];
	protected bool $failUpload = false;
	protected array $errors = [];
	protected bool $shouldDelete = false;
	protected array $onUploaded = [];
	protected array $onFailed = [];

	public function __construct(string $file = "file", string $dir = "uploads/") {
		$files = $_FILES[$file];
		$this->baseDirectory = $dir;
		$this->directory = __DIR__ . "/../$dir";

		if (is_array($files["name"])) {
            for ($i = 0; $i < count($files["name"]); $i++) {
                if (count_chars($files["name"][$i]) <= 0) continue;

                $this->files[$i] = [
                    "name" => $files["name"][$i],
                    "type" => $files["type"][$i],
                    "tmp_name" => $files["tmp_name"][$i],
                    "error" => $files["error"][$i],
                    "size" => $files["size"][$i],
                ];
            }
        } else {
            $this->files[0] = [
                "name" => $files["name"],
                "type" => $files["type"],
                "tmp_name" => $files["tmp_name"],
                "error" => $files["error"],
                "size" => $files["size"],
            ];
        }
	}

	protected function sizeToBytes(int $size, string $mSize) : int {
		$bytes = 0;

		switch($mSize) {
			case "b": {
				$bytes = $size;
				break;
			}

			case "kb": {
				$bytes = 1000 * $size;
				break;
			}

			case "mb": {
				$bytes = 1000 * 1000 * $size;
				break;
			}

			case "gb": {
				$bytes = 1000 * 1000 * 1000 * $size;
				break;
			}
		}

		return $bytes;
	}

	protected function bytesToSize(int $bytes, string $size = "mb") : string {
		$string = "";

		switch($size) {
			case "b":{
				$string = $bytes;
				break;
			}

			case "kb": {
				$string = $bytes / 1000;
				break;
			}

			case "mb": {
				$string = $bytes / 1000 / 1000;
				break;
			}

			case "gb": {
				$string = $bytes / 1000 / 1000 / 1000;
				break;
			}
		}

		return "$string$size";
	}

	public function maxSize(int $size = 0, string $mSize = "mb") {
		if ($size <= 0) return;

		foreach($this->files as $file) {
			$fSize = $file["size"];
			$lSize = $this->sizeToBytes($size, $mSize);

			if ($fSize > $lSize) {
				$this->addError($file["name"], [
					"message" => "File size to large",
					"file_size" => $this->bytesToSize($fSize, $mSize),
					"max_size" => $this->bytesToSize($lSize, $mSize)
				]);
			}
		}
	}

	protected function addError(string $file, array|string $error = "") {
		$this->failUpload = true;

		if(array_key_exists($file, $this->errors) && !is_array($this->errors[$file])) {
			$this->errors[$file] = [];
		}

		$this->errors[$file][] = $error;
	}

	protected function checkFileTypes() {
		if (count($this->allowedTypes) <= 0) return;

		foreach($this->allowedTypes as $type) {
			switch($type) {
				case "image/*": {
					foreach ($this->files as $file) {
						if (!str_contains($file["type"], "image/")) {
							$this->addError($file["name"], [
								"message" => "Unsupported File Type",
								"file_type" => $file["type"],
								"allowed_types" => $this->allowedTypes,
							]);
						}
					}
					break;
				}

				case "video/*": {
					foreach ($this->files as $file) {
						if (!str_contains($file["type"], "video/")) {
							$this->addError($file["name"], [
								"message" => "Unsupported File Type",
								"file_type" => $file["type"],
								"allowed_types" => $this->allowedTypes,
							]);
						}
					}
					break;
				}

				default: {
					foreach ($this->files as $file) {
						if (!str_contains($file["type"], $type)) {
							$this->addError($file["name"], [
								"message" => "Unsupported File Type",
								"file_type" => $file["type"],
								"allowed_types" => $this->allowedTypes,
							]);
						}
					}
					break;
				}
			}
		}
	}

	protected function checkFiles() {
		if($this->shouldDelete) {
			foreach($this->files as $file) {
				if (file_exists($this->directory . $file["name"])) {
					unlink($this->directory . $file["name"]);
				}
			}
		} else {
			foreach($this->files as $file) {
				if (file_exists($this->directory . $file["name"])) {
					$this->addError($file["name"], [
						"message" => "File already exists",
						"directory" => $this->baseDirectory . $file["name"],
					]);
				}
			}
		}
	}

	public function onUploaded(callable $function) {
	    $this->onUploaded[] = $function;
    }

    public function onUploadFail(callable $function) {
        $this->onFailed[] = $function;
    }

	public function randomizeNames() : Uploader {
		foreach($this->files as $key => $file) {
			$this->files[$key]["baseName"] = $file["name"];

			$ext = "." . pathinfo($file["name"], PATHINFO_EXTENSION);

            $this->files[$key]["extension"] = $ext;

            $name = str_replace(".", "", uniqid(more_entropy: true)) . $ext;

			while (file_exists($this->directory . $name)) {
                $name = str_replace(".", "", uniqid(more_entropy: true)) . $ext;
			}

            $this->files[$key]["name"] = $name;

			foreach($this->errors as $ekey => $error) {
				if ($ekey == $file["baseName"]) {
					unset($this->errors[$ekey]);
					$this->errors[$file["name"]] = $error;
				}
			}
		}

		return $this;
	}

	public function deleteIfExists() : Uploader {
		$this->shouldDelete = true;

		return $this;
	}

	public function upload() : array {
		$this->checkFileTypes();
		$this->checkFiles();

		if (!$this->failUpload) {
			foreach($this->files as $file) {
			    if (move_uploaded_file($file["tmp_name"], $this->directory . $file["name"])) {
			        foreach ($this->onUploaded as $uploaded) {
			            $uploaded(new UploadContext($file, $this->directory));
                    }
                } else {
                    $this->failUpload = true;
                    $this->addError($file["name"], [
                        "message" => "Failed to move file to directory.",
                        "directory" => $this->baseDirectory . $file["name"],
                    ]);
                }
			}
		} else {
//            foreach ($this->onFailed as $failed) {
//                $failed(new UploadContext($file, $this->directory));
//            }
        }

		return [
            "error" => $this->failUpload,
            "response" => $this->failUpload ? $this->errors : $this->files
        ];
	}

	public function imageOnly() : Uploader {
		$this->allow("image/*");

		return $this;
	}

	public function videosOnly() : Uploader {
		$this->allow("video/*");

		return $this;
	}

	public function zipOnly() : Uploader {
		$this->allow("application/x-zip-compressed");

		return $this;
	}

	public function allow(...$types) : Uploader {
		$this->allowedTypes = $types;

		return $this;
	}
}