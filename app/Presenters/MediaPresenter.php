<?php

namespace App\Presenters;
// use App\Model\ContactFacade;
use Nette\Application\UI\Form;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use App\Forms\FormFactory;
use stdClass;
use Nette\Localization\Translator;
use Nette\Utils\FileSystem;
use App\Model\MediaFacade;

class MediaPresenter extends BasePresenter
{
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
            $this->error($this->translator->translate('g.media.noRights'), 403);
        }
    }

    protected function createComponentUploadForm(): Form
    {
        // ...
        $form = $this->formFactory->create();
        $form->setTranslator($this->translator);
        $form->addUpload('file', 'g.upload.file')
            ->addRule($form::MAX_FILE_SIZE, 'g.upload.fileSize', 1024 * 1024)
            ->addRule($form::IMAGE, 'g.upload.fileImage')
            ->setRequired('g.upload.fileRequired');
 
        $form->addSubmit('send', 'g.upload.send');
        $form->onSuccess[] = [$this, 'uploadFormSucceeded'];
        return $form;
    }

    public function uploadFormSucceeded(stdClass $data): void
    {
        // $this->facade->sendMessage($data->email, $data->name, $data->message);
        $success = false;
        $error = null;
        $user = $this->getUser();
        $userId = $user->getId();
        try {
            if ($data->file->hasFile()) {
                $this->facade->addImage($data->file, $userId);
                $success = true;
            } else {
                $error = 'g.upload.fileRequired';
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if (!$success) {
            $this->flashMessage($error, 'alert-error');
        } else {
            $this->flashMessage( 'g.upload.sent', 'alert-success');
        }
        $this->redirect('this');
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
            $this->flashMessage('g.media.altUpdated', 'alert-success');
        }
        $this->redirect('this');
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
    }
}
