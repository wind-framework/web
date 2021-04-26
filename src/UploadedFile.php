<?php

namespace Wind\Web;

use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{

    /**
     * File info array
     *
     * @var array
     */
    protected $file;

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    public function getStream() {
        //Todo: implement
    }

    public function moveTo($targetPath) {
        if ($this->getError() != UPLOAD_ERR_OK) {
            throw new \RuntimeException('Can not moveTo because upload is error.');
        }

        //Todo: implement
    }

    public function getSize() {
        return $this->file['size'];
    }

    public function getError() {
        return $this->file['error'];
    }

    public function getClientFilename() {
        return $this->file['name'];
    }

    public function getClientMediaType() {
        return $this->file['type'];
    }

}
