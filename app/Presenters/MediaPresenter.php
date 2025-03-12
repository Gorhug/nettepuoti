<?php

namespace App\Presenters;
// use App\Model\ContactFacade;
use Nette\Application\UI\Form;
use Naja\Guide\Application\UI\Presenters\BasePresenter;
use App\Forms\FormFactory;
use stdClass;
use Nette\Localization\Translator;
use Nette\Utils\FileSystem;
use Symfony\Component\Filesystem\Filesystem as FilesystemFilesystem;

class MediaPresenter extends BasePresenter
{
    public function __construct(
        // private ContactFacade $facade,
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
        // $form->addText('name', 'g.feedback.name')
        // 	->setRequired('g.feedback.nameRequired');
        // $form->addEmail('email', 'g.feedback.email')
        // 	->setRequired('g.feedback.emailRequired');
        // $form->addTextarea('message', 'g.feedback.message')
        // 	->setRequired('g.feedback.messageRequired');
        $form->addSubmit('send', 'g.upload.send');
        $form->onSuccess[] = [$this, 'uploadFormSucceeded'];
        return $form;
    }

    public function uploadFormSucceeded(stdClass $data): void
    {
        // $this->facade->sendMessage($data->email, $data->name, $data->message);
        $success = false;
        $error = null;
        try {
            if ($data->file->hasFile()) {
                $image = $data->file->toImage();
                $fileName = hash('xxh32', $data->file->getContents()) . '-'. $data->file->getSanitizedName();
                FileSystem::createDir($this->settings->uploadDir);
                $data->file->move(FileSystem::joinPaths( $this->settings->uploadDir, $fileName));
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
            $this->flashMessage('g.upload.sent', 'alert-success');
        }
        $this->redirect('this');
    }

}
