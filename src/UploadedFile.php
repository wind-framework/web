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

    /**
     *  @var bool
     */
    private $moved = false;

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    /**
     * @throws \RuntimeException if is moved or not ok
     */
    private function validateActive(): void
    {
        if (\UPLOAD_ERR_OK !== $this->getError()) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    /**
     * @inheritDoc
     */
    public function getStream() {
        $this->validateActive();
        $resource = \fopen($this->file['tmp_name'], 'r');
        return Stream::create($resource);
    }

    /**
     * @inheritDoc
     */
    public function moveTo($targetPath) {
        $this->validateActive();
        $this->moved = \rename($this->file['tmp_name'], $targetPath);
        if (false === $this->moved) {
            throw new \RuntimeException(\sprintf('Uploaded file could not be moved to %s', $targetPath));
        }
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
