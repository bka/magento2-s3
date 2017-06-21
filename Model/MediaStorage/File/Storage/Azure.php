<?php
namespace Thai\S3\Model\MediaStorage\File\Storage;

use Magento\Framework\DataObject;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class Azure extends DataObject implements StorageInterface
{
    /**
     * Store media base directory path
     *
     * @var string
     */
    protected $mediaBaseDirectory = null;

    private $client = null;

    private $helper;

    /**
     * Core file storage database
     *
     * @var \Magento\MediaStorage\Helper\File\Storage\Database
     */
    private $storageHelper;

    /**
     * @var \Magento\MediaStorage\Helper\File\Media
     */
    private $mediaHelper;

    /**
     * Collect errors during sync process
     *
     * @var string[]
     */
    private $errors = [];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $objects = [];

    public function __construct(
        \Thai\S3\Helper\Data $helper,
        \Magento\MediaStorage\Helper\File\Media $mediaHelper,
        \Magento\MediaStorage\Helper\File\Storage\Database $storageHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct();

        $this->helper = $helper;
        $this->mediaHelper = $mediaHelper;
        $this->storageHelper = $storageHelper;
        $this->logger = $logger;
    }

    /**
     * Initialisation
     *
     * @return $this
     */
    public function init()
    {
        return $this;
    }

    /**
     * Return storage name
     *
     * @return \Magento\Framework\Phrase
     */
    public function getStorageName()
    {
        return __('Azure Blob Storage');
    }

    /**
     * credentialsValid
     * @return bool
     */
    public function credentialsValid()
    {
        try {
            $this->getClient();
        } catch (\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            return false;
        }
        return true;
    }

    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }
        $accountName = $this->helper->getAzureAccount();
        $accountKey = $this->helper->getAzureKey();

        $connectionString = "DefaultEndpointsProtocol=https;AccountName=".$accountName.";AccountKey=".$accountKey;
        $this->client = ServicesBuilder::getInstance()->createBlobService($connectionString);
        if (!$this->client) {
            throw new \Exception("Azure Client could not be instantiated");

        }
        return $this->client;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function loadByFilename($filename)
    {
        try {
            $result = $this->getClient()->getBlob($this->getBucket(), $filename);

            if ($result->getContentStream()) {
                $this->setData('id', $filename);
                $this->setData('filename', $filename);
                $this->setData('content', stream_get_contents($result->getContentStream()));
            }
        } catch(\MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e) {
            $this->unsetData();
        }

        return $this;
    }

    public function clear()
    {
        $blobList = $blobClient->listBlobs($this->getBucket());
        $blobs = $blobList->getBlobs();
        foreach ($blobs as $blob) {
            $blobClient->deleteBlob($this->getBucket(), $blob->getName());
        }

        return $this;
    }

    public function exportDirectories($offset = 0, $count = 100)
    {
        // TODO
        return false;
    }

    public function importDirectories(array $dirs = [])
    {
        // TODO
        return $this;
    }

    public function exportFiles($offset = 0, $count = 100)
    {
        $blobList = $this->getClient()->listBlobs("magento-assets");
        $blobs = $blobList->getBlobs();

        $slice = array_slice($blobs, $offset, $count);
        if (!$slice) {
            return false;
        }

        $files = [];
        foreach ($blobs as $blob) {
            $blobResult = $this->getClient()->getBlob($this->getBucket(), $blob->getName());

            $files[] = [
                'filename' => $blob->getName(),
                'content' => stream_get_contents($blobResult->getContentStream())
            ];
        }
        return $files;
    }

    public function importFiles(array $files = [])
    {
        foreach ($files as $file) {
            $path = $file['directory'] . "/" . $file['filename'];
            try {
                echo $this->getBucket() . ":" . $path . "\n";
                $this->getClient()->createBlockBlob($this->getBucket(), $path, $file['content']);
            } catch (ServiceException $e) {
                $code = $e->getCode();
                $error_message = $e->getMessage();
                echo $code.": ".$error_message.PHP_EOL;
            }
        }
        return $this;
    }

    public function saveFile($filename)
    {
        $filename = ltrim($filename,'/');
        $this->logger->error("saving " . $filename ." to azure cloud");
        $file = $this->mediaHelper->collectFileInfo($this->getMediaBaseDirectory(), $filename);
        $this->getClient()->createBlockBlob(
            $this->getBucket(),
            $filename,
            $file['content']
        );
        return $this;
    }

    public function fileExists($filename)
    {
        try {
            $this->getClient()->getBlob($this->getBucket(), $filename);
            return true;
        } catch (ServiceException $e) {
            return false;
        }
    }

    public function copyFile($oldFilePath, $newFilePath)
    {
        $this->logger->error("copy " . $oldFilePath . " -> " . $newFilePath);
        $this->getClient()->copyBlob(
            $this->getBucket(),
            $newFilePath,
            $this->getBucket(),
            $oldFilePath
        );
        return $this;
    }

    public function renameFile($oldFilePath, $newFilePath)
    {
        $this->logger->error("rename " . $oldFilePath . " -> " . $newFilePath);
        $this->copyFile($oldFilePath, $newFilePath);
        $this->deleteFile($oldFilePath);
        return $this;
    }

    /**
     * Delete file from Amazon S3
     *
     * @param string $path
     * @return $this
     */
    public function deleteFile($path)
    {
        $this->logger->debug("delete file " . $path ." in azure cloud");

        $this->getClient()->deleteBlob($this->getBucket(), $path);
        return $this;
    }

    public function getSubdirectories($path)
    {
        $subdirectories = [];

        $prefix = $this->storageHelper->getMediaRelativePath($path);
        $prefix = rtrim($prefix, '/') . '/';

        $options = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
        $options->setPrefix($prefix);
        $blobs = $this->getClient()->listBlobs($this->getBucket(), $options);

        foreach ($blobs as $blob) {
            $subdirectories[] = dirname($blob->getName());
        }

        return array_unique($subdirectories);
    }

    public function getDirectoryFiles($path)
    {
        $files = [];

        $prefix = $this->storageHelper->getMediaRelativePath($path);
        $prefix = rtrim($prefix, '/') . '/';

        $options = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
        $options->setPrefix($prefix);
        $blobs = $this->getClient()->listBlobs($this->getBucket(), $options);

        foreach ($blobs as $blob) {
            $files[] = $blob->getName();
        }

        return $files;
    }

    public function deleteDirectory($path)
    {
        $this->logger->debug("delete directory " . $path ." in azure cloud");
        $files = $this->getDirectoryFiles($path);
        foreach ($files as $file) {
            $this->deleteFile($file);
        }
        return $this;
    }

    public function getBucket()
    {
        return $this->helper->getAzureRegistry();
    }

    /**
     * Retrieve media base directory path
     *
     * @return string
     */
    public function getMediaBaseDirectory()
    {
        if (is_null($this->mediaBaseDirectory)) {
            $this->mediaBaseDirectory = $this->storageHelper->getMediaBaseDir();
        }
        return $this->mediaBaseDirectory;
    }
}
