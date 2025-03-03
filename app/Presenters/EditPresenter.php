<?php
namespace App\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Caching\Cache;

final class EditPresenter extends BasePresenter
{
    private const MaxBrief = 150;

    public function __construct(
        private Nette\Database\Explorer $database,
        private \App\Forms\FormFactory $factory,
        private Translator $translator,
        private Nette\Caching\Storage $storage,
    ) {
    }

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isAllowed('product')) {
            $this->error($this->translator->translate('g.edit.noRights'), 403);
        }
    }


    protected function createComponentProductForm(): Form
    {
        $form = $this->factory->create();
        $form->setTranslator($this->translator);
        $form->addText('name', 'g.edit.name')
            ->setRequired('g.edit.nameRequired');
        
        $form->addTextArea('brief', 'g.edit.brief')
            ->setRequired('g.edit.briefRequired')
            ->setMaxLength(self::MaxBrief);

        $form->addTextArea('description', 'g.edit.description')
            ->setRequired('g.edit.descriptionRequired');

        $form->addText('name_fi', 'g.edit.name_fi')
            ->setRequired('g.edit.name_fiRequired');
        $form->addTextArea('brief_fi', 'g.edit.brief_fi')
            ->setRequired('g.edit.brief_fiRequired')
            ->setMaxLength(self::MaxBrief);
        $form->addTextArea('description_fi', 'g.edit.description_fi')
            ->setRequired('g.edit.description_fiRequired');

        // ->setHtmlAttribute('hx-trigger', 'change, keyup delay:200ms changed');
        $form->addFloat('price', 'g.edit.price')
            ->setRequired('g.edit.priceRequired')
            ->setHtmlAttribute('step', 0.01)
            // ->addRule($form::Float, 'Price must be a decimal number')
            ->addRule($form::Min, 'g.edit.priceLimit', 0);
        $form->addSubmit('send', 'g.edit.save');

        // $form->addProtection();


        $form->onSuccess[] = $this->productFormSucceeded(...);

        return $form;
    }

    private function productFormSucceeded(array $data): void
    {
        $id = $this->getParameter('id');
        $redirect = 0;
        $dirty = ['home'];
        // $cache->clean([
        //     $cache::Tags => ["article/$articleId"],
        // ]);

        if ($id) {
            $product = $this->database
                ->table('products')
                ->get($id);
            $product->update($data);
            $dirty[] = "product/$id";
            $redirect = $id;
        } else {
            $product = $this->database
                ->table('products')
                ->insert($data);
            $redirect = $this->database->getInsertId();
        }
        $this->flashMessage($this->translator->translate('g.edit.success'), 'alert-success');
        $cache = new Cache($this->storage, 'Nette.Templating.Cache');
        $cache->clean([$cache::Tags => $dirty]);
        $this->redirect('Product:show', $redirect);
    }

    public function renderEdit(int $id): void
    {
        $product = $this->database
            ->table('products')
            ->get($id);

        if (!$product) {
            $this->error($this->translator->translate('g.edit.notFound'));
        }

        $this->getComponent('productForm')
            ->setDefaults($product->toArray());
    }


}