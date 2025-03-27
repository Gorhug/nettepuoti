<?php

declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Ublaboo\DataGrid\DataGrid;
use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use App\Model\UserFacade;
use App\Settings;

/**
 * Presenter for the dashboard view.
 * Ensures the user is logged in before access.
 */
final class DashboardPresenter extends BasePresenter
{
	// Incorporates methods to check user login status
	use RequireLoggedUser;

	private const MaxBio = 500;
	public function __construct(
		private \App\Model\UserFacade $userFacade,
		private \App\Model\ActivityPubFacade $apFacade,
		private \Nette\Localization\Translator $translator,
		private FormFactory $formFactory,
		private Settings $settings
	) {
	}
	public function createComponentSimpleGrid($name)
	{
		$grid = new DataGrid($this, $name);

		$grid->setDataSource($this->userFacade->getDataSource());
		$grid->addColumnText('username', 'Username')
			->setSortable()
			->setFilterText();
		$grid->addColumnText('email', 'Email')
			->setSortable()
			->setFilterText();
		$grid->addColumnText('role', 'Role')
			->setSortable()
			->setFilterText();
	}
	public function handleKeys()
	{
		$user = $this->getUser();
		if (!$user->isAllowed('activitypub')) {
			$this->error($this->translator->translate('g.ap.notAllowed'), \Nette\Http\IResponse::S403_Forbidden);
		}
		$this->apFacade->createKeys($user->getIdentity()->getId());
		$this->flashMessage($this->translator->translate('g.ap.keysCreated'), 'alert-success');
		$this->redirect('this');
	}

	public function renderDefault(): void
	{
		$user = $this->getUser();
		$this->template->public_key = $this->apFacade->getPublicKey($user->getIdentity()->getId());
		$this->template->details = $this->userFacade->getDetails($user->getIdentity()->getId());
		$this->template->avatar = $this->userFacade->getAvatar($user->getIdentity()->getId());
		$this->template->uploadDir = $this->settings->uploadDir;
	}

	protected function createComponentDetailsForm(): Form
    {
        // ...
        $form = $this->formFactory->create();
        $form->setTranslator($this->translator);
		$form->addText('realname', 'g.user.realname')
            ->setRequired('g.user.realnameRequired');
        $form->addTextArea('bio', 'g.user.bio')
            ->setMaxLength(self::MaxBio)
            ->setRequired('g.user.bioRequired')
			->setOption("markdown", true);
        $form->addTextArea('bio_fi', 'g.user.bio_fi')
            ->setMaxLength(self::MaxBio)
			->setOption('markdown', true)
            ->setRequired('g.user.bio_fiRequired');
        $form->addSubmit('send', 'g.user.sendDetailsForm');
        $form->onSuccess[] = [$this, 'detailsFormSucceeded'];
        return $form;
    }
	public function detailsFormSucceeded(Form $form, \App\Model\FormData\Details $data): void
	{
		$user = $this->getUser();
		$this->userFacade->updateDetails($user->getIdentity()->getId(), $data);
		$this->flashMessage($this->translator->translate('g.user.detailsUpdated'), 'alert-success');
		$this->redirect('default');
	}

	public function renderDetails(): void
	{
		$user = $this->getUser();
		$this->getComponent('detailsForm')
		->setDefaults($this->userFacade->getDetails($user->getIdentity()->getId()));
	}

	public function renderPreview(string $bio='', string $bio_fi=''): void
    {
        $this->template->markdown = $bio == '' ? $bio_fi : $bio;
    }
}
