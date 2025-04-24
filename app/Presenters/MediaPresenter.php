<?php

namespace App\Presenters;
// use App\Model\ContactFacade;
use Contributte\Translation\Wrappers\NotTranslate;
use Nette\Application\UI\Form;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use App\Forms\FormFactory;
use Nette\Utils\Image;
use NumberFormatter;
use stdClass;
use Nette\Localization\Translator;
use Nette\Utils\FileSystem;
use App\Model\MediaFacade;
use Contributte\Translation\Wrappers\Message;
use Nette\Utils\Arrays;
use Nette\Utils\ImageType;

class MediaPresenter extends BasePresenter
{
    private const MAX_FILE_SIZE = 1024*1024;
    public function __construct(
        private MediaFacade $facade,
        private FormFactory $formFactory,
        private Translator $translator,
        private \App\Settings $settings
    ) {
    }

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isAllowed('media')) {
            $this->error($this->translator->translate('g.media.notAllowed'), 403);
        }
    }

    protected function createComponentUploadForm(): Form
    {
        // ...
        $maxFileSizeMB = self::MAX_FILE_SIZE/1024/1024;
        $form = $this->formFactory->create();
        $form->setTranslator($this->translator);
        $form->addUpload('file', 'g.upload.file')
            ->addRule($form::MaxFileSize, new Message("g.upload.fileSize", ['size' => "{$maxFileSizeMB} MB" ]), self::MAX_FILE_SIZE)
            ->addRule($form::Image, 'g.upload.fileImage')
            ->setRequired('g.upload.fileRequired');
 
        $form->addSubmit('send', 'g.upload.send');
        $form->onSuccess[] = [$this, 'uploadFormSucceeded'];
        return $form;
    }

    public function uploadFormSucceeded(stdClass $data): void
    {
        // $this->facade->sendMessage($data->email, $data->name, $data->message);
        $redirect = null;
        $error = null;
        $user = $this->getUser();
        $userId = $user->getId();
        try {
            if ($data->file->hasFile()) {
                $redirect = $this->facade->addImage($data->file, $userId);
            } else {
                $error = 'g.upload.fileRequired';
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if (!$redirect) {
            $this->flashMessage($error, 'alert-error');
            $this->redirect('this');
        } else {
            $this->flashMessage( 'g.upload.sent', 'alert-success');
            $this->redirect('edit', $redirect);
        }
        
    }
    protected function createComponentAltForm(): Form
    {
        // ...
        $form = $this->formFactory->create();
        $form->setTranslator($this->translator);
        $form->addText('alt', 'g.media.alt')
            ->setMaxLength(120)
            ->setRequired('g.media.altRequired');
        $form->addText('alt_fi', 'g.media.alt_fi')
            ->setMaxLength(120)
            ->setRequired('g.media.alt_fiRequired');
        $form->addSubmit('send', 'g.media.sendAltForm');
        $form->onSuccess[] = [$this, 'altFormSucceeded'];
        return $form;
    }

    public function altFormSucceeded(array $data): void
    {
        // $this->facade->sendMessage($data->email, $data->name, $data->message);
        $success = false;
        $error = null;
        $id = $this->getParameter('id');
        try {
            $this->facade->updateImage($id, $data);
            $success = true;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if (!$success) {
            $this->flashMessage($error, 'alert-error');
        } else {
            $this->flashMessage($this->translator->translate('g.media.altUpdated'), 'alert-success');
        }
        $this->redirect('default');
    }

    public function renderEdit(int $id): void
    {
        $image = $this->facade->getImage($id);
        $this->template->image = $image;
        $this->template->uploadDir = $this->settings->uploadDir;
        if (!$image) {
            $this->error($this->translator->translate('g.media.notFound'));
        }
        $this->getComponent('altForm')
            ->setDefaults($image->toArray());
    }

    public function renderDefault(): void
    {
        $user = $this->getUser();
        $userId = $user->getId();
        $images = $this->facade->getImages($userId);
        $this->template->images = $images;
        $this->template->uploadDir = $this->settings->uploadDir;
        bdump($this->template->baseUrl, "baseUrl");
    }
    public function renderUpload(): void
    {
        $types = Image::getSupportedTypes();
        $typeExtensions = Arrays::map($types, function($type) {
            return Image::typeToExtension($type);
        });
        $this->template->formats = implode(', ',$typeExtensions);
        $this->template->maxFileSize = self::MAX_FILE_SIZE;
        bdump($this->template->formats, "formats");
    }
}
