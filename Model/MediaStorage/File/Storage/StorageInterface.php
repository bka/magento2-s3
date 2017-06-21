<?php
namespace Thai\S3\Model\MediaStorage\File\Storage;

interface StorageInterface
{
    /**
     * Return storage name
     *
     * @return \Magento\Framework\Phrase
     */
    public function getStorageName();

    public function init();

    public function clear();

    public function exportDirectories($offset = 0, $count = 100);

    public function importDirectories(array $dirs = []);

    /**
     * credentialsValid
     * @return bool
     */
    public function credentialsValid();

    /**
     * @param string $filename
     * @return $this
     */
    public function loadByFilename($filename);

    public function exportFiles($offset = 0, $count = 100);

    public function importFiles(array $files = []);

    public function saveFile($filename);

    public function fileExists($filename);

    public function copyFile($oldFilePath, $newFilePath);

    public function renameFile($oldFilePath, $newFilePath);

    /**
     * Delete file from Amazon S3
     *
     * @param string $path
     * @return $this
     */
    public function deleteFile($path);

    public function getSubdirectories($path);

    public function getDirectoryFiles($path);

    public function deleteDirectory($path);

    public function getBucket();
}
