<?php
namespace App\Model;

use Exception;
use Nette;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;

final class MediaFacade
{
	public function __construct(
		private Nette\Database\Explorer $database,
        private \App\Settings $settings
	) {
	}

    public function addImage(FileUpload $file, $owner)
    {
        $image = $file->toImage();
        $fileName = hash('xxh32', $file->getContents()) . '-'. $file->getSanitizedName();
        if ($this->database->table('images')->where('filename', $fileName)->count('*') > 0) {
            throw new Exception('g.upload.fileExists');
        }
        $dir = FileSystem::joinPaths($this->settings->wwwDir, $this->settings->uploadDir);
        FileSystem::createDir($dir);
        $file->move(FileSystem::joinPaths( $dir, $fileName));
        $this->database->table('images')->insert([
            'filename' => $fileName,
            'owner' => $owner,
        ]);
    }

	public function getImages()
	{
		
	}
}
