<?php
namespace Thai\S3\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StorageSyncCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this->setName('s3:storage:sync');
        $this->setDescription('Sync all of your media files over to S3.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storage = $objectManager->create("\Magento\MediaStorage\Model\File\Storage");

        $sourceModel = $storage->getStorageModel(\Thai\S3\Model\MediaStorage\File\Storage::STORAGE_MEDIA_FILE_SYSTEM);
        $destinationModel = $storage->getStorageModel(\Thai\S3\Model\MediaStorage\File\Storage::STORAGE_MEDIA_AZURE);

        $this->synchronize($sourceModel, $destinationModel);
    }

    public function synchronize($sourceModel, $destinationModel)
    {
        $hasErrors = false;

        $destinationModel->clear();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $mediaHelper = $objectManager->get("Magento\MediaStorage\Helper\File\Media");
        $fileStorage = $objectManager->get("Magento\MediaStorage\Model\File\Storage\File");

        $offset = 0;
        while (($dirs = $sourceModel->exportDirectories($offset)) !== false) {
            /* print_r($dirs); */
            $hasErrors = $sourceModel->hasErrors() || $destinationModel->hasErrors();
            $destinationModel->importDirectories($dirs);

            /* foreach ($dirs as $dir) { */
            /*     // wtf, magento */
            /*     if ($dir['path'] == "/") { */
            /*         $path = "/" . $dir['name']; */
            /*     } else { */
            /*         $path = "/" . $dir['path'] . "/" . $dir['name']; */

            /*     } */
            /*     /1* echo $path . "\n"; *1/ */
            /* } */
            $offset += count($dirs);
        }
        unset($dirs);

        $offset = 0;
        while (($files = $sourceModel->exportFiles($offset, 1)) !== false) {
            $hasErrors = $sourceModel->hasErrors() || $destinationModel->hasErrors();
            $destinationModel->importFiles($files);
            $offset += count($files);
        }
        unset($files);

        echo "has errors: " . print_r($hasErrors, true);

        return $this;
    }
}
