<?php
namespace Thai\S3\Model\MediaStorage\File\Storage\Directory\Database;

class Plugin
{
    private $helper;

    private $storageModel;

    public function __construct(
        \Thai\S3\Helper\Data $helper,
        \Magento\MediaStorage\Model\File\Storage $storage
    ) {
        $this->helper = $helper;
        $this->storageModel = $storage->getStorageModel();
    }

    public function aroundCreateRecursive($subject, $proceed, $path)
    {
        if ($this->helper->checkS3Usage()) {
            return $this;
        }
        return $proceed($path);
    }

    public function aroundGetSubdirectories($subject, $proceed, $directory)
    {
        if ($this->helper->checkS3Usage()) {
            return $this->storageModel->getSubdirectories($directory);
        }
        return $proceed($directory);
    }

    public function aroundDeleteDirectory($subject, $proceed, $path)
    {
        if ($this->helper->checkS3Usage()) {
            return $this->storageModel->deleteDirectory($path);
        }
        return $proceed($path);
    }
}
