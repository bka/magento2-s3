<?php
namespace Thai\S3\Model\MediaStorage\Config\Source\Storage\Media\Storage;

class Plugin
{
    public function afterToOptionArray($subject, $result)
    {
        $result[] = [
            'value' => \Thai\S3\Model\MediaStorage\File\Storage::STORAGE_MEDIA_S3,
            'label' => __('Amazon S3')
        ];
        $result[] = [
            'value' => \Thai\S3\Model\MediaStorage\File\Storage::STORAGE_MEDIA_AZURE,
            'label' => __('Azure Blob Storage')
        ];
        return $result;
    }
}
