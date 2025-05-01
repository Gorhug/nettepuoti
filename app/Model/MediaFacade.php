<?php
/*
Copyright Ilkka Forsblom.

This file is part of Nettepuoti.

Nettepuoti is free software: you can redistribute it and/or modify 
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the 
License, or (at your option) any later version.

Nettepuoti is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License 
along with Nettepuoti. If not, see <https://www.gnu.org/licenses/>. 
*/
namespace App\Model;

use Exception;
use Nette;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

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
            'width' => $image->getWidth(),
            'height' => $image->getHeight()
        ]);
        return $this->database->getInsertId();
    }

    public function getImage($id): ActiveRow
    {
        $image = $this->database->table('images')->get($id);
        if (!$image) {
            throw new Exception('g.media.notFound');
        }
        return $image;
    }

    public function updateImage($id, $data)
    {
        $image = $this->database->table('images')->get($id);
        if (!$image) {
            throw new Exception('g.media.notFound');
        }
        $image->update($data);
        
    }


	public function getImages($owner): Selection
	{
		return $this->database->table('images')->where('owner', $owner)->order(("id DESC"));
	}
}
